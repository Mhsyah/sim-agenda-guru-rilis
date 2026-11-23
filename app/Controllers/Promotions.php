<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Period;
use Throwable;

/**
 * Admin: kelulusan (kelas akhir) dan kenaikan kelas ke tahun pelajaran berikutnya.
 * Riwayat kelas di tahun lama TIDAK diubah (nilai & rekap tetap utuh):
 * - lulus  → status siswa menjadi "Lulus";
 * - naik   → penempatan baru di kelas tujuan pada T.P. tujuan;
 * - tinggal kelas / tidak lulus → penempatan baru di kelas bernama sama pada T.P. tujuan.
 */
class Promotions extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $db     = db_connect();
        $source = $db->table('academic_years')->where('id', Period::yearId())->get()->getRowArray();
        $later  = $db->table('academic_years')->where('start_date >', $source['start_date'])->orderBy('start_date', 'ASC')->get()->getResultArray();
        $target = null;
        $want   = (int) $this->request->getGet('ke');
        foreach ($later as $y) {
            if (! $target || (int) $y['id'] === $want) {
                $target = $y;
            }
            if ((int) $y['id'] === $want) {
                break;
            }
        }

        $plan = self::plan((int) $source['id'], $target ? (int) $target['id'] : null);

        return $this->render('promotions/index', [
            'title'  => 'Kelulusan & Kenaikan Kelas',
            'source' => $source,
            'later'  => $later,
            'target' => $target,
        ] + $plan);
    }

    public function process()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $db       = db_connect();
        $sourceId = (int) Period::yearId();
        $targetId = (int) $this->request->getPost('target_year_id') ?: null;
        if ($targetId && ! $db->table('academic_years')->where('id', $targetId)->countAllResults()) {
            return redirect()->back()->with('error', 'Tahun pelajaran tujuan tidak ditemukan.');
        }
        $plan    = self::plan($sourceId, $targetId);
        $except  = array_flip(array_map('intval', (array) ($this->request->getPost('except') ?? [])));
        $mapPost = (array) ($this->request->getPost('map') ?? []);
        $create  = (bool) $this->request->getPost('create_classes');
        $res     = ['lulus' => 0, 'naik' => 0, 'tinggal' => 0, 'lewat' => 0, 'kelas_baru' => 0];

        try {
            $db->transException(true)->transStart();
            foreach ($plan['classes'] as $c) {
                $isFinal = $c['final'];
                $dest    = $isFinal ? null : self::resolveClass($targetId, (string) ($mapPost[$c['id']] ?? $c['target_name']), $c['grade'] + 1, $create, $res);
                $stay    = $targetId ? self::resolveClass($targetId, $c['name'], $c['grade'], $create, $res) : null;
                foreach ($c['students'] as $s) {
                    $sid     = (int) $s['id'];
                    $excepts = isset($except[$sid]);
                    if ($isFinal && ! $excepts) {
                        if ($s['status'] !== 'lulus') {
                            $db->table('students')->where('id', $sid)->update(['status' => 'lulus', 'updated_at' => date('Y-m-d H:i:s')]);
                            Audit::log('update', 'student', $sid, ['status' => $s['status']], ['status' => 'lulus', 'keterangan' => 'Lulus dari ' . $c['name']]);
                            $res['lulus']++;
                        } else {
                            $res['lewat']++;
                        }

                        continue;
                    }
                    if (! $targetId) {
                        $res['lewat']++; // tanpa T.P. tujuan: hanya kelulusan yang diproses

                        continue;
                    }
                    $to = $excepts ? $stay : $dest;
                    if (! $to) {
                        $res['lewat']++;

                        continue;
                    }
                    if ($db->table('student_enrollments')->where('student_id', $sid)->where('academic_year_id', $targetId)->countAllResults()) {
                        $res['lewat']++; // sudah punya kelas di T.P. tujuan

                        continue;
                    }
                    $db->table('student_enrollments')->insert(['student_id' => $sid, 'class_group_id' => $to, 'academic_year_id' => $targetId, 'status' => 'aktif',
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    $res[$excepts ? 'tinggal' : 'naik']++;
                }
            }
            Audit::log('process', 'promotion', $targetId, ['tahun_asal' => $sourceId], $res + ['tahun_tujuan' => $targetId]);
            $db->transComplete();
        } catch (Throwable $e) {
            log_message('error', 'Kelulusan/kenaikan gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Proses gagal dan dibatalkan seluruhnya: ' . $e->getMessage());
        }

        $msg = sprintf('Selesai: %d siswa lulus, %d naik kelas, %d tinggal kelas/tidak lulus', $res['lulus'], $res['naik'], $res['tinggal']);
        $msg .= $res['kelas_baru'] ? ', ' . $res['kelas_baru'] . ' kelas baru dibuat' : '';
        $msg .= $res['lewat'] ? ' (' . $res['lewat'] . ' dilewati karena sudah diproses).' : '.';

        return redirect()->to(site_url('promotions' . ($targetId ? '?ke=' . $targetId : '')))->with('success', $msg);
    }

    /**
     * Rencana per kelas di T.P. asal: siswa aktif, kelas akhir/bukan, dan kelas tujuan bawaan.
     *
     * @return array{classes: list<array>, finalGrade: int, targetClasses: array<int, string>, counts: array}
     */
    public static function plan(int $sourceId, ?int $targetId): array
    {
        $db      = db_connect();
        $classes = $db->table('class_groups')->where('academic_year_id', $sourceId)->where('deleted_at', null)
            ->orderBy('grade_level', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();
        $grades  = array_filter(array_map(static fn ($c) => self::gradeOf($c), $classes));
        $final   = $grades ? max($grades) : 0;
        $tClass  = $targetId ? array_column($db->table('class_groups')->select('id, name')->where('academic_year_id', $targetId)->where('deleted_at', null)
            ->orderBy('grade_level', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray(), 'name', 'id') : [];
        $inTarget = $targetId ? array_flip(array_map('intval', array_column($db->table('student_enrollments')->select('student_id')->where('academic_year_id', $targetId)->get()->getResultArray(), 'student_id'))) : [];

        $out    = [];
        $counts = ['students' => 0, 'final' => 0, 'promote' => 0, 'done' => 0];
        foreach ($classes as $c) {
            $students = $db->table('student_enrollments e')->select('s.id, s.full_name, s.nis, s.nisn, s.gender, s.status')
                ->join('students s', 's.id = e.student_id')
                ->where('e.class_group_id', $c['id'])->where('e.academic_year_id', $sourceId)->where('e.status', 'aktif')->where('s.deleted_at', null)
                ->whereIn('s.status', ['aktif', 'lulus'])
                ->orderBy('s.full_name', 'ASC')->get()->getResultArray();
            if (! $students) {
                continue;
            }
            $g       = self::gradeOf($c);
            $isFinal = $g === $final;
            $tName   = $isFinal ? '' : self::nextName($c['name'], $g);
            foreach ($students as &$s) {
                $s['done'] = $isFinal ? $s['status'] === 'lulus' : isset($inTarget[(int) $s['id']]);
                $counts['done'] += (int) $s['done'];
            }
            unset($s);
            $counts['students'] += count($students);
            $counts[$isFinal ? 'final' : 'promote'] += count($students);
            $out[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'grade' => $g, 'final' => $isFinal, 'students' => $students,
                'target_name' => $tName, 'target_exists' => in_array($tName, $tClass, true)];
        }

        return ['classes' => $out, 'finalGrade' => $final, 'targetClasses' => $tClass, 'counts' => $counts];
    }

    /** Tingkat kelas: kolom grade_level, atau dari nama ("VIII B" → 8, "7A" → 7). */
    private static function gradeOf(array $c): int
    {
        if ((int) ($c['grade_level'] ?? 0) > 0) {
            return (int) $c['grade_level'];
        }
        $first = preg_match('/^\s*([IVXLC]+|\d+)/i', (string) $c['name'], $m) ? $m[1] : '';

        return ctype_digit($first) ? (int) $first : roman_to_int($first);
    }

    /** "VII A" → "VIII A", "7A" → "8A". */
    public static function nextName(string $name, int $grade): string
    {
        if (preg_match('/^\s*(\d+)(.*)$/', $name, $m)) {
            return ($grade + 1) . $m[2];
        }
        if (preg_match('/^\s*([IVXLC]+)(\b.*)$/i', $name, $m)) {
            return int_to_roman($grade + 1) . $m[2];
        }

        return $name;
    }

    /** Cari kelas bernama $name di T.P. tujuan; buat bila belum ada dan diizinkan. */
    private static function resolveClass(?int $yearId, string $name, int $grade, bool $create, array &$res): ?int
    {
        if (! $yearId || trim($name) === '') {
            return null;
        }
        $db  = db_connect();
        $row = $db->table('class_groups')->where('academic_year_id', $yearId)->where('name', $name)->where('deleted_at', null)->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
        if (! $create) {
            return null;
        }
        $db->table('class_groups')->insert(['academic_year_id' => $yearId, 'name' => $name, 'grade_level' => $grade, 'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $res['kelas_baru']++;

        return (int) $db->insertID();
    }
}
