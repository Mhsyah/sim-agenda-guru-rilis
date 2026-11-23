<?php

namespace App\Libraries;

use App\Controllers\TeacherSchedules;
use RuntimeException;

/**
 * Bahan penyusun jadwal: sesi kelas (Pagi/Siang), struktur jam & kegiatan, beban JP, serta draf
 * (hasil proses otomatis / upload PDF) yang baru disimpan setelah disetujui admin.
 */
final class ScheduleKit
{
    public const SHIFTS = ['pagi' => 'Pagi', 'siang' => 'Siang'];

    /** Label kegiatan non-mengajar yang disarankan. */
    public const ACTIVITIES = ['Pembiasaan', 'Upacara', 'Istirahat', 'MBG', 'Literasi', 'Sholat Dhuha', 'Kokurikuler/P5', 'Ekstrakurikuler', 'Rapat Guru'];

    // ------------------------------------------------------------------ jadwal saat ini

    /** Baris jadwal T.P. lengkap dengan nama kelas, mapel & guru (format yang dipakai tabel keseluruhan). */
    public static function scheduleRows(int $yearId): array
    {
        return db_connect()->table('teaching_schedules ts')
            ->select('ts.*, cg.name AS class_name, cg.grade_level, s.name AS subject_name, s.code AS subject_code, u.name AS teacher_name')
            ->join('class_groups cg', 'cg.id = ts.class_group_id')
            ->join('subjects s', 's.id = ts.subject_id')
            ->join('users u', 'u.id = ts.user_id', 'left')
            ->where('ts.academic_year_id', $yearId)
            ->orderBy('ts.day_of_week', 'ASC')->orderBy('ts.start_time', 'ASC')->get()->getResultArray();
    }

    /** Sesi tiap kelas: dari jadwal yang ada (mulai < 12.00 = pagi), selain itu ikut mayoritas tingkat yang sama. @return array<int, string> */
    public static function classShifts(int $yearId): array
    {
        $first = [];
        foreach (self::scheduleRows($yearId) as $r) {
            $c = (int) $r['class_group_id'];
            $first[$c] = min($first[$c] ?? '99:99:99', $r['start_time']);
        }
        $classes = db_connect()->table('class_groups')->select('id, name, grade_level')->where('academic_year_id', $yearId)->where('deleted_at', null)->get()->getResultArray();
        $out     = [];
        $byGrade = [];
        foreach ($classes as $c) {
            if (isset($first[(int) $c['id']])) {
                $out[(int) $c['id']] = $first[(int) $c['id']] < '12:00:00' ? 'pagi' : 'siang';
                $byGrade[(int) $c['grade_level']][$out[(int) $c['id']]] = ($byGrade[(int) $c['grade_level']][$out[(int) $c['id']]] ?? 0) + 1;
            }
        }
        foreach ($classes as $c) {
            if (! isset($out[(int) $c['id']])) {
                $g = $byGrade[(int) $c['grade_level']] ?? [];
                arsort($g);
                $out[(int) $c['id']] = (string) (array_key_first($g) ?? 'pagi');
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ struktur jam & kegiatan

    /** @return array<int, list<array>> [hari] => slot berurutan */
    public static function slots(int $yearId, string $shift): array
    {
        $out = [];
        foreach (db_connect()->table('time_slots')->where('academic_year_id', $yearId)->where('shift', $shift)
            ->orderBy('day_of_week', 'ASC')->orderBy('start_time', 'ASC')->get()->getResultArray() as $s) {
            $out[(int) $s['day_of_week']][] = $s;
        }

        return $out;
    }

    /** Ganti struktur jam satu sesi. @param list<array{day: int, start: string, end: string, kind: string, lesson_no: ?int, label: ?string}> $slots */
    public static function saveSlots(int $yearId, string $shift, array $slots): int
    {
        $db = db_connect();
        $db->table('time_slots')->where('academic_year_id', $yearId)->where('shift', $shift)->delete();
        $rows = [];
        foreach ($slots as $s) {
            $rows[] = ['academic_year_id' => $yearId, 'shift' => $shift, 'day_of_week' => (int) $s['day'], 'start_time' => self::t($s['start']), 'end_time' => self::t($s['end']),
                'kind' => $s['kind'] === 'kegiatan' ? 'kegiatan' : 'pelajaran', 'lesson_no' => $s['kind'] === 'kegiatan' ? null : ($s['lesson_no'] ?? null),
                'label' => $s['kind'] === 'kegiatan' ? mb_substr((string) ($s['label'] ?? 'Kegiatan'), 0, 80) : null];
        }
        if ($rows) {
            $db->table('time_slots')->insertBatch($rows);
        }

        return count($rows);
    }

    /**
     * Struktur jam dari jadwal yang sudah ada: jam pelajaran per hari + jeda di antaranya sebagai "Istirahat".
     *
     * @return array<string, list<array>> [sesi] => slot
     */
    public static function slotsFromSchedule(int $yearId): array
    {
        $rows     = self::scheduleRows($yearId);
        $teachers = db_connect()->table('users')->select('id, name, nip, photo, gender, teacher_code')->where('role', 'teacher')->get()->getResultArray();
        $classes  = Lookup::classes($yearId, false);
        $ov       = TeacherSchedules::overall($rows, $teachers, $classes);
        $out      = [];
        foreach ($ov['shifts'] as $shift => $sh) {
            foreach ($sh['days'] as $d => $day) {
                foreach ($day['rows'] as $r) {
                    [$a, $z] = array_map('trim', explode('-', $r['pukul']));
                    $out[$shift][] = $r['cells']
                        ? ['day' => $d, 'start' => $a, 'end' => $z, 'kind' => 'pelajaran', 'lesson_no' => (int) $r['jam'] ?: null, 'label' => null]
                        : ['day' => $d, 'start' => $a, 'end' => $z, 'kind' => 'kegiatan', 'lesson_no' => null, 'label' => 'Istirahat'];
                }
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ beban JP

    /** @return array<int, array<int, int>> [subject_id][tingkat] => JP */
    public static function loads(int $yearId): array
    {
        $out = [];
        foreach (db_connect()->table('subject_loads')->where('academic_year_id', $yearId)->get()->getResultArray() as $r) {
            $out[(int) $r['subject_id']][(int) $r['grade_level']] = (int) $r['jp'];
        }

        return $out;
    }

    public static function saveLoads(int $yearId, array $map): int
    {
        $db = db_connect();
        $db->table('subject_loads')->where('academic_year_id', $yearId)->delete();
        $rows = [];
        foreach ($map as $sid => $grades) {
            foreach ((array) $grades as $g => $jp) {
                $jp = max(0, min(20, (int) $jp));
                if ($jp > 0) {
                    $rows[] = ['academic_year_id' => $yearId, 'subject_id' => (int) $sid, 'grade_level' => (int) $g, 'jp' => $jp];
                }
            }
        }
        if ($rows) {
            $db->table('subject_loads')->insertBatch($rows);
        }

        return count($rows);
    }

    /** Beban JP dari jadwal yang ada: JP terbanyak per mapel per kelas, dirangkum per tingkat. */
    public static function loadsFromSchedule(int $yearId): array
    {
        $per = [];
        foreach (self::scheduleRows($yearId) as $r) {
            $k = $r['subject_id'] . '|' . $r['class_group_id'];
            $per[$k] = ['sid' => (int) $r['subject_id'], 'g' => (int) $r['grade_level'], 'jp' => ($per[$k]['jp'] ?? 0) + TeacherSchedules::jp($r['lesson_hours'])];
        }
        $votes = [];
        foreach ($per as $p) {
            $votes[$p['sid']][$p['g']][$p['jp']] = ($votes[$p['sid']][$p['g']][$p['jp']] ?? 0) + 1;
        }
        $out = [];
        foreach ($votes as $sid => $grades) {
            foreach ($grades as $g => $jps) {
                arsort($jps);
                $out[$sid][$g] = (int) array_key_first($jps);
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ draf

    private static function draftDir(): string
    {
        $d = WRITEPATH . 'schedule-drafts' . DIRECTORY_SEPARATOR;
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        foreach (glob($d . '*.json') ?: [] as $f) { // bersihkan draf > 1 hari
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }

        return $d;
    }

    /** Simpan draf & catat pemiliknya di sesi. */
    public static function saveDraft(array $draft): string
    {
        $token = bin2hex(random_bytes(10));
        file_put_contents(self::draftDir() . $token . '.json', json_encode($draft + ['created_at' => date('Y-m-d H:i:s'), 'user_id' => Auth::id()], JSON_UNESCAPED_UNICODE));
        session()->set('schedule_drafts', array_slice(array_merge((array) session('schedule_drafts'), [$token]), -10));

        return $token;
    }

    public static function loadDraft(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{20}$/', $token) || ! in_array($token, (array) session('schedule_drafts'), true)) {
            return null;
        }
        $f = self::draftDir() . $token . '.json';

        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }

    public static function dropDraft(string $token): void
    {
        if (preg_match('/^[a-f0-9]{20}$/', $token)) {
            @unlink(self::draftDir() . $token . '.json');
        }
        session()->set('schedule_drafts', array_values(array_diff((array) session('schedule_drafts'), [$token])));
    }

    /** Draf → baris berformat jadwal (untuk pratinjau dengan tabel keseluruhan). */
    public static function draftRows(array $draft): array
    {
        $db       = db_connect();
        $classes  = array_column($db->table('class_groups')->select('id, name')->get()->getResultArray(), 'name', 'id');
        $subjects = [];
        foreach ($db->table('subjects')->select('id, code, name')->get()->getResultArray() as $s) {
            $subjects[(int) $s['id']] = $s;
        }
        $users = array_column($db->table('users')->select('id, name')->get()->getResultArray(), 'name', 'id');
        $out   = [];
        foreach ($draft['blocks'] as $i => $b) {
            $out[] = ['id' => $i + 1, 'class_group_id' => (int) $b['class_group_id'], 'subject_id' => (int) $b['subject_id'], 'user_id' => (int) $b['user_id'],
                'day_of_week' => (int) $b['day'], 'start_time' => self::t($b['start']), 'end_time' => self::t($b['end']), 'lesson_hours' => (string) $b['lesson_hours'],
                'class_name' => $classes[$b['class_group_id']] ?? '?', 'subject_name' => $subjects[$b['subject_id']]['name'] ?? '?',
                'subject_code' => $subjects[$b['subject_id']]['code'] ?? '', 'teacher_name' => $users[$b['user_id']] ?? '—'];
        }

        return $out;
    }

    /**
     * Simpan draf yang disetujui: backup dulu, lalu ganti jadwal kelas-kelas pada sesi ini saja.
     *
     * @return array{blocks: int, backup: string, slots: int, piket: int}
     */
    public static function apply(array $draft, bool $withPiket = false): array
    {
        $yearId  = (int) $draft['year_id'];
        $classes = array_map('intval', $draft['class_ids']);
        if (! $classes) {
            throw new RuntimeException('Draf tidak memuat kelas.');
        }
        $backup = (new BackupService())->store('sebelum-jadwal');
        $db     = db_connect();
        $db->transException(true)->transStart();
        $db->table('teaching_schedules')->where('academic_year_id', $yearId)->whereIn('class_group_id', $classes)->delete();
        $now  = date('Y-m-d H:i:s');
        $rows = array_map(static fn ($b) => ['academic_year_id' => $yearId, 'class_group_id' => (int) $b['class_group_id'], 'subject_id' => (int) $b['subject_id'],
            'user_id' => (int) $b['user_id'], 'day_of_week' => (int) $b['day'], 'start_time' => self::t($b['start']), 'end_time' => self::t($b['end']),
            'lesson_hours' => (string) $b['lesson_hours'], 'created_at' => $now, 'updated_at' => $now], $draft['blocks']);
        foreach (array_chunk($rows, 200) as $chunk) {
            $db->table('teaching_schedules')->insertBatch($chunk);
        }
        $slots = ! empty($draft['slots']) ? self::saveSlots($yearId, $draft['shift'], $draft['slots']) : 0;
        $piket = 0;
        if ($withPiket && ! empty($draft['piket'])) {
            $map = DutyRoster::all($yearId);
            $ids = [];
            foreach ($map as $sh => $days) {
                foreach ($days as $d => $ps) {
                    $ids[$sh][$d] = array_column($ps, 'id');
                }
            }
            $ids[$draft['shift']] = [];
            foreach ($draft['piket'] as $d => $uids) {
                $ids[$draft['shift']][(int) $d] = array_values(array_filter(array_map('intval', $uids)));
                $piket += count($ids[$draft['shift']][(int) $d]);
            }
            DutyRoster::save($yearId, $ids);
        }
        Audit::log('apply', 'schedule_draft', $yearId, null, ['sumber' => $draft['source'], 'sesi' => $draft['shift'], 'blok' => count($rows), 'backup' => $backup]);
        $db->transComplete();

        return ['blocks' => count($rows), 'backup' => $backup, 'slots' => $slots, 'piket' => $piket];
    }

    /** "7.10" / "07:10" / "07:10:00" → "07:10:00" */
    public static function t(string $s): string
    {
        $s = str_replace('.', ':', trim($s));
        [$h, $m] = array_pad(explode(':', $s), 2, '0');

        return sprintf('%02d:%02d:00', (int) $h, (int) $m);
    }

    /** Gabungkan jam berurutan (kelas, mapel, guru, hari sama) menjadi blok "a-b". @param list<array{class_group_id:int,subject_id:int,user_id:int,day:int,start:string,end:string,lesson_no:?int}> $cells */
    public static function mergeBlocks(array $cells): array
    {
        usort($cells, static fn ($a, $b) => [$a['class_group_id'], $a['day'], $a['start']] <=> [$b['class_group_id'], $b['day'], $b['start']]);
        $out = [];
        foreach ($cells as $c) {
            $i = count($out) - 1;
            if ($i >= 0 && $out[$i]['class_group_id'] === $c['class_group_id'] && $out[$i]['day'] === $c['day'] && $out[$i]['subject_id'] === $c['subject_id']
                && $out[$i]['user_id'] === $c['user_id'] && self::t($out[$i]['end']) === self::t($c['start'])) {
                $out[$i]['end'] = $c['end'];
                $out[$i]['j1']  = $c['lesson_no'] ?? $out[$i]['j1'];
                continue;
            }
            $out[] = $c + ['j0' => $c['lesson_no'] ?? null, 'j1' => $c['lesson_no'] ?? null];
        }
        foreach ($out as &$b) {
            $b['lesson_hours'] = $b['j0'] === null ? '' : ($b['j0'] === $b['j1'] ? (string) $b['j0'] : $b['j0'] . '-' . $b['j1']);
            unset($b['j0'], $b['j1'], $b['lesson_no']);
        }

        return $out;
    }
}
