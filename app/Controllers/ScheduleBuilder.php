<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Libraries\ScheduleGenerator;
use App\Libraries\ScheduleKit;
use App\Libraries\SchedulePdfImporter;
use Throwable;

/**
 * Admin: penyusun jadwal — beban JP & struktur jam, proses otomatis, upload PDF jadwal jadi.
 * Semua hasil masuk draf + pratinjau; jadwal baru disimpan hanya setelah disetujui admin.
 */
class ScheduleBuilder extends BaseController
{
    // ------------------------------------------------------------------ Beban JP & struktur jam

    public function setup()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yid    = (int) Period::yearId();
        $grades = array_values(array_unique(array_filter(array_map('intval', array_column(db_connect()->table('class_groups')->select('grade_level')
            ->where('academic_year_id', $yid)->where('deleted_at', null)->get()->getResultArray(), 'grade_level')))));
        sort($grades);
        $shift = $this->request->getGet('sesi') === 'siang' ? 'siang' : 'pagi';

        return $this->render('schedule_builder/setup', [
            'title'    => 'Beban JP & Struktur Jam',
            'subjects' => db_connect()->table('subjects')->where('deleted_at', null)->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray(),
            'grades'   => $grades ?: [7, 8, 9],
            'loads'    => ScheduleKit::loads($yid),
            'shift'    => $shift,
            'slots'    => ScheduleKit::slots($yid, $shift),
            'counts'   => array_map(static fn ($s) => array_sum(array_map('count', ScheduleKit::slots($yid, $s))), ScheduleKit::SHIFTS),
        ]);
    }

    public function saveLoads()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yid = (int) Period::yearId();
        $map = $this->request->getPost('auto') ? ScheduleKit::loadsFromSchedule($yid) : (array) ($this->request->getPost('jp') ?? []);
        $n   = ScheduleKit::saveLoads($yid, $map);
        Audit::log('update', 'subject_load', $yid, null, ['isian' => $n]);

        return redirect()->to(site_url('schedule-builder/setup'))->with('success', 'Beban JP disimpan (' . $n . ' isian)' . ($this->request->getPost('auto') ? ', diambil dari jadwal yang ada.' : '.'));
    }

    public function saveSlots()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yid = (int) Period::yearId();
        if ($this->request->getPost('auto')) {
            $n = 0;
            foreach (ScheduleKit::slotsFromSchedule($yid) as $sh => $sl) {
                $n += ScheduleKit::saveSlots($yid, $sh, $sl);
            }
            Audit::log('update', 'time_slot', $yid, null, ['dari_jadwal' => $n]);

            return redirect()->to(site_url('schedule-builder/setup'))->with('success', $n . ' slot jam & kegiatan diambil dari jadwal yang ada (Pagi & Siang). Tambahkan kegiatan seperti Pembiasaan/MBG bila perlu.');
        }
        $shift = $this->request->getPost('shift') === 'siang' ? 'siang' : 'pagi';
        $rows  = [];
        $err   = [];
        foreach ((array) ($this->request->getPost('slot') ?? []) as $s) {
            if (! empty($s['hapus']) || ($s['start'] ?? '') === '' || ($s['end'] ?? '') === '') {
                continue;
            }
            $day = (int) ($s['day'] ?? 0);
            if ($day < 1 || $day > 6 || ! preg_match('/^\d{1,2}[:.]\d{2}$/', $s['start']) || ! preg_match('/^\d{1,2}[:.]\d{2}$/', $s['end'])
                || ScheduleKit::t($s['end']) <= ScheduleKit::t($s['start'])) {
                $err[] = trim(($s['start'] ?? '') . '–' . ($s['end'] ?? ''));
                continue;
            }
            $kind   = ($s['kind'] ?? '') === 'kegiatan' ? 'kegiatan' : 'pelajaran';
            $rows[] = ['day' => $day, 'start' => $s['start'], 'end' => $s['end'], 'kind' => $kind,
                'lesson_no' => $kind === 'pelajaran' ? ((int) ($s['lesson_no'] ?? 0) ?: null) : null,
                'label' => $kind === 'kegiatan' ? (trim((string) ($s['label'] ?? '')) ?: 'Kegiatan') : null];
        }
        if ($err) {
            return redirect()->back()->withInput()->with('error', 'Jam tidak valid: ' . implode(', ', array_slice($err, 0, 5)) . '. Gunakan format 07:10 dan jam selesai setelah jam mulai.');
        }
        $n = ScheduleKit::saveSlots((int) Period::yearId(), $shift, $rows);
        Audit::log('update', 'time_slot', (int) Period::yearId(), null, ['sesi' => $shift, 'slot' => $n]);

        return redirect()->to(site_url('schedule-builder/setup?sesi=' . $shift))->with('success', 'Struktur jam sesi ' . ScheduleKit::SHIFTS[$shift] . ' disimpan (' . $n . ' slot).');
    }

    // ------------------------------------------------------------------ Proses otomatis

    public function generate()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yid = (int) Period::yearId();

        return $this->render('schedule_builder/generate', [
            'title'   => 'Proses Jadwal Otomatis',
            'classes' => Lookup::classes(),
            'shifts'  => ScheduleKit::classShifts($yid),
            'ready'   => [
                'loads' => count(ScheduleKit::loads($yid)),
                'slots' => array_map(static fn ($s) => array_sum(array_map(static fn ($d) => count(array_filter($d, static fn ($x) => $x['kind'] === 'pelajaran')), ScheduleKit::slots($yid, $s))), ScheduleKit::SHIFTS),
                'teach' => db_connect()->table('user_teaching')->countAllResults(),
            ],
        ]);
    }

    public function runGenerate()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yid     = (int) Period::yearId();
        $shift   = $this->request->getPost('shift') === 'siang' ? 'siang' : 'pagi';
        $valid   = array_keys(Lookup::classes());
        $classes = array_values(array_intersect(array_map('intval', (array) ($this->request->getPost('classes') ?? [])), $valid));
        if (! $classes) {
            return redirect()->back()->with('error', 'Pilih minimal satu kelas untuk sesi ' . ScheduleKit::SHIFTS[$shift] . '.');
        }
        $res = (new ScheduleGenerator())->run($yid, $shift, $classes, 200);
        if (! $res['blocks']) {
            return redirect()->back()->with('error', implode(' ', array_slice($res['issues'], 0, 3)));
        }
        $issues = array_map(static fn ($t) => ['level' => str_starts_with($t, 'Tidak muat') ? 'error' : 'warning', 'where' => '', 'text' => $t], $res['issues']);
        $token  = ScheduleKit::saveDraft(['source' => 'generate', 'year_id' => $yid, 'shift' => $shift, 'class_ids' => $classes, 'blocks' => $res['blocks'],
            'slots' => [], 'piket' => [], 'issues' => $issues, 'stats' => $res['stats']]);

        return redirect()->to(site_url('schedule-builder/preview/' . $token));
    }

    // ------------------------------------------------------------------ Upload PDF jadwal jadi

    public function upload()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return $this->render('schedule_builder/upload', ['title' => 'Upload Jadwal (PDF)']);
    }

    public function runUpload()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $file = $this->request->getFile('pdf');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Pilih file PDF jadwal terlebih dahulu.');
        }
        if ($file->getSize() > 10 * 1024 * 1024 || strtolower($file->getClientExtension()) !== 'pdf' || ! str_starts_with((string) file_get_contents($file->getTempName(), false, null, 0, 5), '%PDF')) {
            return redirect()->back()->with('error', 'File harus PDF (maks. 10 MB).');
        }
        try {
            $res = (new SchedulePdfImporter())->parse($file->getTempName(), (int) Period::yearId(), (bool) $this->request->getPost('suggest'));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        if (! $res['draft']['blocks']) {
            return redirect()->back()->with('error', 'Tidak ada jadwal yang terbaca dari PDF ini.');
        }
        $token = ScheduleKit::saveDraft($res['draft'] + ['stats' => $res['summary'], 'file' => $file->getClientName()]);

        return redirect()->to(site_url('schedule-builder/preview/' . $token));
    }

    // ------------------------------------------------------------------ Pratinjau & simpan

    public function preview(string $token)
    {
        $draft = ScheduleKit::loadDraft($token);
        if (! $draft) {
            return redirect()->to(site_url('schedule-builder/generate'))->with('error', 'Draf tidak ditemukan atau sudah kedaluwarsa. Silakan proses ulang.');
        }
        $db       = db_connect();
        $teachers = $db->table('users')->select('id, name, nip, photo, gender, teacher_code')->where('role', 'teacher')->get()->getResultArray();
        $classes  = array_intersect_key(Lookup::classes((int) $draft['year_id'], false), array_flip($draft['class_ids']));
        $ov       = TeacherSchedules::overall(ScheduleKit::draftRows($draft), $teachers, $classes);
        $current  = $db->table('teaching_schedules')->where('academic_year_id', $draft['year_id'])->whereIn('class_group_id', $draft['class_ids'])->countAllResults();

        return $this->render('schedule_builder/preview', [
            'title'   => 'Pratinjau Jadwal',
            'token'   => $token,
            'draft'   => $draft,
            'sheet'   => $ov['shifts'][$draft['shift']] ?? (array_values($ov['shifts'])[0] ?? null),
            'legend'  => $ov['legend'],
            'current' => $current,
            'names'   => array_column($teachers, 'name', 'id'),
            'classes' => $classes,
        ]);
    }

    public function apply(string $token)
    {
        $draft = ScheduleKit::loadDraft($token);
        if (! $draft) {
            return redirect()->to(site_url('schedule-builder/generate'))->with('error', 'Draf tidak ditemukan atau sudah kedaluwarsa.');
        }
        if (! $this->request->getPost('slots')) {
            $draft['slots'] = [];
        }
        try {
            $res = ScheduleKit::apply($draft, (bool) $this->request->getPost('piket'));
        } catch (Throwable $e) {
            log_message('error', 'Simpan draf jadwal gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Jadwal gagal disimpan dan tidak ada yang berubah: ' . $e->getMessage());
        }
        ScheduleKit::dropDraft($token);
        $msg = 'Jadwal sesi ' . ScheduleKit::SHIFTS[$draft['shift']] . ' disimpan: ' . $res['blocks'] . ' blok untuk ' . count($draft['class_ids']) . ' kelas.'
            . ($res['slots'] ? ' Struktur jam diperbarui (' . $res['slots'] . ' slot).' : '')
            . ($res['piket'] ? ' Piket diperbarui (' . $res['piket'] . ' petugas).' : '')
            . ' Backup sebelumnya: ' . $res['backup'] . '.';

        return redirect()->to(site_url('teacher-schedules'))->with('success', $msg);
    }

    public function discard(string $token)
    {
        ScheduleKit::dropDraft($token);

        return redirect()->to(site_url('schedule-builder/generate'))->with('success', 'Draf dibatalkan; jadwal tidak berubah.');
    }
}
