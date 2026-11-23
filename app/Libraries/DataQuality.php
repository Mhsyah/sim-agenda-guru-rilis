<?php

namespace App\Libraries;

use App\Controllers\TeacherSchedules;

/**
 * Indikator kualitas data "Manajemen Agenda Guru" (mirip IKD Dapodik).
 * Tiap entitas dinilai pada 3 dimensi (0–100%): Kelengkapan, Validitas, Mutakhir. null = belum ada data (tidak dihitung).
 * Total = rata-rata dimensi yang ada. Data induk memakai kondisi saat ini; jadwal, agenda & penilaian mengikuti semester.
 */
final class DataQuality
{
    public const DIMS = ['k' => 'Kelengkapan', 'v' => 'Validitas', 'm' => 'Mutakhir'];

    public const ENTITIES = ['sekolah' => 'Sekolah', 'guru' => 'Guru (PTK)', 'siswa' => 'Peserta Didik', 'rombel' => 'Rombel',
        'jadwal' => 'Jadwal', 'agenda' => 'Agenda/Jurnal', 'nilai' => 'Penilaian'];

    /** @return array{entities: array<string, array{k: ?float, v: ?float, m: ?float}>, dims: array<string, ?float>, total: ?float, semester: array} */
    public static function forSemester(int $semesterId): array
    {
        return cache()->remember('dq_' . $semesterId . '_' . date('YmdH') . intdiv((int) date('i'), 10), 600, static fn () => self::compute($semesterId));
    }

    /** Tren skor total per semester (maks. $n semester terakhir s.d. semester aktif). */
    public static function trend(int $upToSemesterId, int $n = 7): array
    {
        $db  = db_connect();
        $cur = $db->table('semesters')->where('id', $upToSemesterId)->get()->getRowArray();
        if (! $cur) {
            return [];
        }
        $sems = $db->table('semesters s')->select('s.id, s.name, ay.name AS year_name')->join('academic_years ay', 'ay.id = s.academic_year_id')
            ->where('s.start_date <=', $cur['start_date'])->orderBy('s.start_date', 'DESC')->limit($n)->get()->getResultArray();
        $out = [];
        foreach (array_reverse($sems) as $s) {
            $q     = self::forSemester((int) $s['id']);
            $out[] = ['label' => $s['year_name'] . ' ' . $s['name'], 'total' => $q['total'], 'dims' => $q['dims']];
        }

        return $out;
    }

    private static function compute(int $semesterId): array
    {
        $db  = db_connect();
        $sem = $db->table('semesters s')->select('s.*, ay.name AS year_name')->join('academic_years ay', 'ay.id = s.academic_year_id')->where('s.id', $semesterId)->get()->getRowArray();
        if (! $sem) {
            return ['entities' => [], 'dims' => array_fill_keys(array_keys(self::DIMS), null), 'total' => null, 'semester' => []];
        }
        $yid   = (int) $sem['academic_year_id'];
        $today = date('Y-m-d');
        $e     = [];

        // ---------------- Sekolah
        $p = $db->table('school_profiles')->get()->getRowArray() ?: [];
        $fields = ['school_name', 'school_address', 'npsn', 'school_city', 'school_phone', 'school_email', 'principal_name', 'principal_nip', 'school_logo'];
        $checks = [];
        if (($p['npsn'] ?? '') !== '') {
            $checks[] = (bool) preg_match('/^\d{8}$/', trim($p['npsn']));
        }
        if (($p['principal_nip'] ?? '') !== '') {
            $checks[] = (bool) preg_match('/^\d{18}$/', preg_replace('/\s+/', '', $p['principal_nip']));
        }
        if (($p['school_email'] ?? '') !== '') {
            $checks[] = (bool) filter_var($p['school_email'], FILTER_VALIDATE_EMAIL);
        }
        $e['sekolah'] = [
            'k' => $p ? self::pct(count(array_filter($fields, static fn ($f) => trim((string) ($p[$f] ?? '')) !== '')), count($fields)) : 0.0,
            'v' => $checks ? self::pct(count(array_filter($checks)), count($checks)) : null,
            'm' => $p ? (strtotime((string) ($p['updated_at'] ?? $p['created_at'] ?? '2000-01-01')) >= strtotime('-365 days') ? 100.0 : 0.0) : null,
        ];

        // ---------------- Guru
        $teachers = $db->table('users')->where('role', 'teacher')->where('is_active', 1)->get()->getResultArray();
        $hasSubj  = array_flip(array_map('intval', array_column($db->table('user_subjects')->select('user_id')->distinct()->get()->getResultArray(), 'user_id')));
        $hasTeach = array_flip(array_map('intval', array_column($db->table('user_teaching')->select('user_id')->distinct()->get()->getResultArray(), 'user_id')));
        $codes    = array_count_values(array_filter(array_map(static fn ($t) => (string) $t['teacher_code'], $teachers)));
        $k = $v = $m = 0;
        foreach ($teachers as $t) {
            $id = (int) $t['id'];
            $k += self::pct((int) (trim((string) $t['nip']) !== '') + (int) in_array($t['gender'], ['L', 'P'], true) + (int) (trim((string) $t['teacher_code']) !== '')
                + (int) isset($hasSubj[$id]) + (int) isset($hasTeach[$id]), 5);
            $nipOk  = trim((string) $t['nip']) === '' || preg_match('/^\d{18}$/', preg_replace('/\s+/', '', (string) $t['nip']));
            $codeOk = trim((string) $t['teacher_code']) === '' || ($codes[(string) $t['teacher_code']] ?? 0) === 1;
            $v += ($nipOk && $codeOk && filter_var($t['email'], FILTER_VALIDATE_EMAIL)) ? 1 : 0;
            $m += ($t['last_login_at'] && strtotime($t['last_login_at']) >= strtotime('-30 days')) ? 1 : 0;
        }
        $n = count($teachers);
        $e['guru'] = ['k' => $n ? round($k / $n, 2) : null, 'v' => $n ? self::pct($v, $n) : null, 'm' => $n ? self::pct($m, $n) : null];

        // ---------------- Peserta didik (aktif)
        $students = $db->table('students')->select('id, nis, nisn, gender, birth_date, full_name')->where('deleted_at', null)->where('status', 'aktif')->get()->getResultArray();
        $enrolled = array_flip(array_map('intval', array_column($db->table('student_enrollments')->select('student_id')->where('academic_year_id', $yid)->get()->getResultArray(), 'student_id')));
        $dupKey   = array_count_values(array_map(static fn ($s) => mb_strtolower(trim($s['full_name'])) . '|' . $s['birth_date'], array_filter($students, static fn ($s) => $s['birth_date'])));
        $k = $v = $m = 0;
        foreach ($students as $s) {
            $in = isset($enrolled[(int) $s['id']]);
            $k += self::pct((int) ($s['nis'] !== null && $s['nis'] !== '') + (int) ($s['nisn'] !== null && $s['nisn'] !== '') + (int) in_array($s['gender'], ['L', 'P'], true)
                + (int) ! empty($s['birth_date']) + (int) $in, 5);
            $v += ((! $s['nisn'] || preg_match('/^\d{10}$/', (string) $s['nisn'])) && (! $s['birth_date'] || ($dupKey[mb_strtolower(trim($s['full_name'])) . '|' . $s['birth_date']] ?? 0) < 2)) ? 1 : 0;
            $m += $in ? 1 : 0;
        }
        $n = count($students);
        $e['siswa'] = ['k' => $n ? round($k / $n, 2) : null, 'v' => $n ? self::pct($v, $n) : null, 'm' => $n ? self::pct($m, $n) : null];

        // ---------------- Rombel
        $classes = $db->table('class_groups')->where('academic_year_id', $yid)->where('deleted_at', null)->get()->getResultArray();
        $size    = [];
        foreach ($db->table('student_enrollments e')->select('e.class_group_id, COUNT(*) AS n')->join('students s', 's.id = e.student_id')
            ->where('e.academic_year_id', $yid)->where('e.status', 'aktif')->where('s.deleted_at', null)->groupBy('e.class_group_id')->get()->getResultArray() as $r) {
            $size[(int) $r['class_group_id']] = (int) $r['n'];
        }
        $schedCls = array_flip(array_map('intval', array_column($db->table('teaching_schedules')->select('class_group_id')->where('academic_year_id', $yid)->distinct()->get()->getResultArray(), 'class_group_id')));
        $agCls    = array_flip(array_map('intval', array_column($db->table('teaching_agendas')->select('class_group_id')->where('semester_id', $semesterId)->where('deleted_at', null)->distinct()->get()->getResultArray(), 'class_group_id')));
        $names    = array_count_values(array_map(static fn ($c) => mb_strtolower(trim($c['name'])), $classes));
        $k = $v = $m = 0;
        foreach ($classes as $c) {
            $id = (int) $c['id'];
            $k += self::pct((int) ((int) $c['grade_level'] > 0) + (int) (($size[$id] ?? 0) > 0) + (int) isset($schedCls[$id]), 3);
            $v += (($size[$id] ?? 0) <= 40 && ($names[mb_strtolower(trim($c['name']))] ?? 0) === 1) ? 1 : 0;
            $m += isset($agCls[$id]) ? 1 : 0;
        }
        $n = count($classes);
        $e['rombel'] = ['k' => $n ? round($k / $n, 2) : null, 'v' => $n ? self::pct($v, $n) : null, 'm' => $n ? self::pct($m, $n) : null];

        // ---------------- Jadwal (T.P. semester ini)
        $rows = $db->table('teaching_schedules')->where('academic_year_id', $yid)->get()->getResultArray();
        if ($rows && $teachers) {
            $tWith = count(array_intersect(array_map('intval', array_column($teachers, 'id')), array_map('intval', array_column($rows, 'user_id'))));
            $cWith = count(array_intersect(array_map('intval', array_column($classes, 'id')), array_keys($schedCls)));
            $clash = 0;
            $by    = [];
            foreach ($rows as $r) {
                $by['t' . $r['user_id'] . '-' . $r['day_of_week']][] = $r;
                $by['c' . $r['class_group_id'] . '-' . $r['day_of_week']][] = $r;
            }
            $bad = [];
            foreach ($by as $list) {
                usort($list, static fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));
                for ($i = 1; $i < count($list); $i++) {
                    if ($list[$i]['start_time'] < $list[$i - 1]['end_time']) {
                        $bad[$list[$i]['id']] = $bad[$list[$i - 1]['id']] = true;
                    }
                }
            }
            $loads = ScheduleKit::loads($yid);
            $jpm   = null;
            if ($loads) {
                $need = [];
                foreach ($classes as $c) {
                    $need[(int) $c['id']] = array_sum(array_map(static fn ($x) => $x[(int) $c['grade_level']] ?? 0, $loads));
                }
                $got = [];
                foreach ($rows as $r) {
                    $got[(int) $r['class_group_id']] = ($got[(int) $r['class_group_id']] ?? 0) + TeacherSchedules::jp($r['lesson_hours']);
                }
                $ok  = count(array_filter($need, static fn ($v2, $cid) => $v2 > 0 && ($got[$cid] ?? 0) === $v2, ARRAY_FILTER_USE_BOTH));
                $jpm = self::pct($ok, max(1, count(array_filter($need))));
            }
            $e['jadwal'] = ['k' => round((self::pct($tWith, count($teachers)) + self::pct($cWith, max(1, count($classes)))) / 2, 2),
                'v' => self::pct(count($rows) - count($bad), count($rows)), 'm' => $jpm];
        } else {
            $e['jadwal'] = ['k' => $teachers ? 0.0 : null, 'v' => null, 'm' => null];
        }

        // ---------------- Agenda/Jurnal (semester): pertemuan terjadwal s.d. hari ini yang sudah ada agendanya
        $end      = min($today, $sem['end_date']);
        $holidays = [];
        foreach ($db->table('school_calendar_events')->where('event_type', 'libur')->where('end_date >=', $sem['start_date'])->where('start_date <=', $end)->get()->getResultArray() as $h) {
            for ($t = strtotime($h['start_date']); $t <= strtotime($h['end_date']); $t += 86400) {
                $holidays[date('Y-m-d', $t)] = true;
            }
        }
        $dates = [];
        for ($t = strtotime($sem['start_date']); $sem['start_date'] <= $end && $t <= strtotime($end); $t += 86400) {
            $d = date('Y-m-d', $t);
            if (! isset($holidays[$d])) {
                $dates[(int) date('N', $t)][] = $d;
            }
        }
        $expected = 0;
        foreach ($rows as $r) {
            $expected += count($dates[(int) $r['day_of_week']] ?? []);
        }
        $ag = $db->table('teaching_agendas a')->select('a.id, a.agenda_date, a.created_at, a.learning_objective_id, (SELECT COUNT(*) FROM ' . $db->prefixTable('attendances') . ' x WHERE x.teaching_agenda_id = a.id) AS att', false)
            ->where('a.semester_id', $semesterId)->where('a.deleted_at', null)->get()->getResultArray();
        $nAg = count($ag);
        $e['agenda'] = [
            'k' => $expected ? min(100.0, self::pct($nAg, $expected)) : ($nAg ? 100.0 : null),
            'v' => $nAg ? round((self::pct(count(array_filter($ag, static fn ($a) => (int) $a['att'] > 0)), $nAg) + self::pct(count(array_filter($ag, static fn ($a) => $a['learning_objective_id'])), $nAg)) / 2, 2) : null,
            'm' => $nAg ? self::pct(count(array_filter($ag, static fn ($a) => strtotime(substr((string) $a['created_at'], 0, 10)) <= strtotime($a['agenda_date'] . ' +2 days'))), $nAg) : null,
        ];

        // ---------------- Penilaian (semester)
        $sc = $db->prefixTable('assessment_scores');
        $en = $db->prefixTable('student_enrollments');
        $st = $db->prefixTable('students');
        $as = $db->prefixTable('assessments');
        $asm = $db->table('assessments')->select("{$as}.id, {$as}.assessment_date, {$as}.max_score,
                (SELECT COUNT(*) FROM {$sc} x WHERE x.assessment_id = {$as}.id) AS graded,
                (SELECT COUNT(*) FROM {$sc} x WHERE x.assessment_id = {$as}.id AND (x.score < 0 OR x.score > {$as}.max_score)) AS bad,
                (SELECT MIN(x.created_at) FROM {$sc} x WHERE x.assessment_id = {$as}.id) AS first_at,
                (SELECT COUNT(*) FROM {$en} e JOIN {$st} s2 ON s2.id = e.student_id AND s2.deleted_at IS NULL WHERE e.class_group_id = {$as}.class_group_id AND e.status = 'aktif') AS enrolled", false)
            ->where('assessments.semester_id', $semesterId)->where('assessments.deleted_at', null)->get()->getResultArray();
        $nA = count($asm);
        if ($nA) {
            $kSum = array_sum(array_map(static fn ($a) => (int) $a['enrolled'] ? min(100, (int) $a['graded'] / (int) $a['enrolled'] * 100) : 100, $asm));
            $graded = array_sum(array_column($asm, 'graded'));
            $vScore = $graded ? self::pct($graded - array_sum(array_column($asm, 'bad')), $graded) : 100.0;
            $cfgs   = $db->table('grading_configs')->where('semester_id', $semesterId)->where('is_active', 1)->get()->getResultArray();
            $cfgOk  = 0;
            foreach ($cfgs as $c) {
                $sum = (float) ($db->table('grading_config_items')->selectSum('weight_percent')->where('grading_config_id', $c['id'])->where('is_enabled', 1)->get()->getRowArray()['weight_percent'] ?? 0);
                $cfgOk += abs($sum - 100) < 0.01 ? 1 : 0;
            }
            $withScores = array_filter($asm, static fn ($a) => $a['first_at']);
            $e['nilai'] = [
                'k' => round($kSum / $nA, 2),
                'v' => $cfgs ? round(($vScore + self::pct($cfgOk, count($cfgs))) / 2, 2) : $vScore,
                'm' => $withScores ? self::pct(count(array_filter($withScores, static fn ($a) => strtotime(substr((string) $a['first_at'], 0, 10)) <= strtotime($a['assessment_date'] . ' +7 days'))), count($withScores)) : null,
            ];
        } else {
            $e['nilai'] = ['k' => null, 'v' => null, 'm' => null];
        }

        $dims = [];
        foreach (array_keys(self::DIMS) as $d) {
            $vals      = array_values(array_filter(array_column($e, $d), static fn ($x) => $x !== null));
            $dims[$d]  = $vals ? round(array_sum($vals) / count($vals), 2) : null;
        }
        $have  = array_values(array_filter($dims, static fn ($x) => $x !== null));

        return ['entities' => $e, 'dims' => $dims, 'total' => $have ? round(array_sum($have) / count($have), 2) : null,
            'semester' => ['id' => (int) $sem['id'], 'label' => $sem['year_name'] . ' ' . $sem['name']]];
    }

    private static function pct(int|float $a, int|float $b): float
    {
        return $b > 0 ? round($a / $b * 100, 2) : 0.0;
    }
}
