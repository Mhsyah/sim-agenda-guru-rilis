<?php

namespace App\Controllers;

use App\Libraries\Attendance;
use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Libraries\RecapService;
use App\Models\AgendaModel;
use App\Models\AssessmentModel;
use App\Models\CalendarEventModel;
use App\Models\ScoreModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/** Laporan siap cetak & ekspor CSV. Semua laporan hanya untuk pengguna terautentikasi. */
class Reports extends BaseController
{
    private const TYPES = [
        'journal'    => ['Buku Jurnal Agenda Guru', 'Format buku agenda sekolah ber-kop: sampul & kesan kepala sekolah, jadwal mengajar, tabel agenda (a/s/i), tanda tangan.', 'book'],
        'agenda'     => ['Agenda/Jurnal', 'Jurnal mengajar per tanggal, kelas, mapel, dan semester.', 'pen'],
        'attendance' => ['Rekap Kehadiran', 'Jumlah hadir, sakit, izin, dan alpa per siswa dari daftar hadir agenda.', 'check'],
        'students'   => ['Daftar Siswa', 'Daftar siswa per kelas pada tahun ajaran aktif.', 'users'],
        'scores'     => ['Nilai per Asesmen', 'Daftar nilai satu asesmen, termasuk yang belum diinput.', 'chart'],
        'recap'      => ['Rekap Nilai', 'Rekap Tugas, UH, ATS, AAS dan nilai akhir sesuai bobot.', 'layers'],
        'incomplete' => ['Nilai Belum Lengkap', 'Siswa yang belum memiliki nilai pada asesmen semester ini.', 'alert'],
        'calendar'   => ['Kalender Pendidikan', 'Daftar kegiatan dan hari libur tahun ajaran aktif.', 'calendar'],
    ];

    public function index()
    {
        return $this->render('reports/index', ['title' => 'Laporan', 'types' => self::TYPES]);
    }

    public function show(string $type)
    {
        if (! isset(self::TYPES[$type])) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        if ($type === 'recap') {
            return redirect()->to(site_url('recap'));
        }

        $f    = $this->request->getGet();
        // Isi cache pilihan lebih dulu: query lain di tengah query ber-alias merusak awalan tabel CI.
        Lookup::teachingSubjectIds();
        Lookup::subjects(false);
        Lookup::teachingSubjects();
        $data = $this->{'report' . ucfirst($type)}($f);
        $data += ['type' => $type, 'meta' => self::TYPES[$type], 'title' => 'Laporan ' . self::TYPES[$type][0], 'f' => $f,
            'classes' => Lookup::teachingClasses(), 'subjects' => Lookup::teachingSubjects(), 'semesters' => Lookup::semesters()];

        if (($f['format'] ?? '') === 'csv') {
            Audit::log('export', 'report', null, null, ['type' => $type, 'rows' => count($data['csv'][1])]);

            return csv_response($type . '-' . date('Ymd') . '.csv', $data['csv'][0], $data['csv'][1]);
        }

        return $this->render('reports/show', $data);
    }

    private function reportAgenda(array $f): array
    {
        $m = (new AgendaModel())
            ->select('teaching_agendas.*, cg.name AS class_name, s.name AS subject_name, lo.code AS tp_code, sm.name AS semester_name')
            ->join('class_groups cg', 'cg.id = teaching_agendas.class_group_id')
            ->join('subjects s', 's.id = teaching_agendas.subject_id')
            ->join('semesters sm', 'sm.id = teaching_agendas.semester_id')
            ->join('learning_objectives lo', 'lo.id = teaching_agendas.learning_objective_id', 'left')
            ->where('sm.academic_year_id', Period::yearId())
            ->where('teaching_agendas.user_id', Auth::id());
        if (($taught = Lookup::teachingSubjectIds()) !== null) {
            $m->whereIn('teaching_agendas.subject_id', $taught ?: [0]); // hanya mapel yang diajarkan
        }
        foreach (['class_group_id', 'subject_id', 'semester_id'] as $k) {
            if (! empty($f[$k])) {
                $m->where('teaching_agendas.' . $k, (int) $f[$k]);
            }
        }
        if (! empty($f['from'])) {
            $m->where('agenda_date >=', $f['from']);
        }
        if (! empty($f['to'])) {
            $m->where('agenda_date <=', $f['to']);
        }
        $rows   = $m->orderBy('agenda_date', 'ASC')->orderBy('teaching_agendas.id', 'ASC')->findAll();
        $absent = Attendance::absentees(array_map('intval', array_column($rows, 'id')));
        $absTxt = static fn ($r) => implode(', ', array_map(static fn ($n) => $n[0] . ' (' . $n[1] . ')', $absent[(int) $r['id']] ?? [])) ?: '—';

        return [
            'filters' => ['class_group_id', 'subject_id', 'semester_id', 'from', 'to'],
            'head'    => ['Tanggal', 'Kelas', 'Mapel', 'TP', 'Topik', 'Kegiatan', 'Refleksi', 'Tindak lanjut', 'Tidak hadir'],
            'body'    => array_map(static fn ($r) => [fmt_date($r['agenda_date'], false, true), $r['class_name'], $r['subject_name'], $r['tp_code'] ?? '—', $r['topic'], $r['activities'], $r['reflection'], $r['follow_up'], $absTxt($r)], $rows),
            'csv'     => [['Tanggal', 'Semester', 'Kelas', 'Mapel', 'TP', 'Topik', 'Kegiatan', 'Refleksi', 'Tindak lanjut', 'Status', 'Tidak hadir'],
                array_map(static fn ($r) => [$r['agenda_date'], $r['semester_name'], $r['class_name'], $r['subject_name'], $r['tp_code'], $r['topic'], $r['activities'], $r['reflection'], $r['follow_up'], $r['status'], $absTxt($r)], $rows)],
        ];
    }

    private function reportAttendance(array $f): array
    {
        $db      = db_connect();
        $classes = Lookup::teachingClasses();
        $classId = (int) ($f['class_group_id'] ?? 0) ?: (int) array_key_first($classes);
        $at = $db->prefixTable('attendances');
        $ag = $db->prefixTable('teaching_agendas');
        $st = $db->prefixTable('students');

        $where = ['a.deleted_at IS NULL', "a.status <> 'batal'", 'a.class_group_id = ?', 'a.user_id = ?'];
        if (($taught = Lookup::teachingSubjectIds()) !== null) {
            $where[] = 'a.subject_id IN (' . implode(',', array_map('intval', $taught ?: [0])) . ')'; // angka saja, aman
        }
        $bind  = [$classId, Auth::id()];
        if (! empty($f['subject_id'])) {
            $where[] = 'a.subject_id = ?';
            $bind[]  = (int) $f['subject_id'];
        }
        $sem = $f['semester_id'] ?? (string) Period::semesterId();
        if ($sem !== '') {
            $where[] = 'a.semester_id = ?';
            $bind[]  = (int) $sem;
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (! empty($f[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $where[] = "a.agenda_date {$op} ?";
                $bind[]  = $f[$k];
            }
        }
        // Agregasi bersyarat (CASE) portabel untuk MariaDB/MySQL dan PostgreSQL.
        $sql = "SELECT s.id, s.nis, s.full_name,
                    SUM(CASE WHEN x.status = 'H' THEN 1 ELSE 0 END) AS h,
                    SUM(CASE WHEN x.status = 'S' THEN 1 ELSE 0 END) AS s,
                    SUM(CASE WHEN x.status = 'I' THEN 1 ELSE 0 END) AS i,
                    SUM(CASE WHEN x.status = 'A' THEN 1 ELSE 0 END) AS a,
                    COUNT(*) AS total
                FROM {$at} x
                JOIN {$ag} a ON a.id = x.teaching_agenda_id
                JOIN {$st} s ON s.id = x.student_id
                WHERE " . implode(' AND ', $where) . '
                GROUP BY s.id, s.nis, s.full_name
                ORDER BY s.full_name';
        $rows = $db->query($sql, $bind)->getResultArray();

        $body = [];
        foreach ($rows as $n => $r) {
            $pct    = (int) $r['total'] > 0 ? (int) $r['h'] / (int) $r['total'] * 100 : 0;
            $body[] = [$n + 1, $r['nis'], $r['full_name'], (int) $r['h'], (int) $r['s'], (int) $r['i'], (int) $r['a'], (int) $r['total'], fmt_num($pct, 1) . '%'];
        }
        $head = ['No', 'NIS', 'Nama', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Pertemuan', '% Hadir'];

        return [
            'filters'      => ['class_group_id', 'subject_id', 'semester_id', 'from', 'to'],
            'subtitle'     => 'Kelas ' . ($classes[$classId] ?? '—') . ' · dihitung dari daftar hadir agenda yang terlaksana.',
            'requireClass' => true,
            'head'         => $head,
            'body'         => $body,
            'empty'        => 'Belum ada daftar hadir untuk filter ini. Isi daftar hadir saat menulis agenda.',
            'csv'          => [$head, $body],
        ];
    }

    private function reportStudents(array $f): array
    {
        $db = db_connect();
        $b  = $db->table('student_enrollments en')
            ->select('s.nis, s.nisn, s.full_name, s.gender, s.status, cg.name AS class_name')
            ->join('students s', 's.id = en.student_id')
            ->join('class_groups cg', 'cg.id = en.class_group_id')
            ->where('en.academic_year_id', Period::yearId())
            ->where('s.deleted_at', null);
        if (! empty($f['class_group_id'])) {
            $b->where('en.class_group_id', (int) $f['class_group_id']);
        }
        $rows = $b->orderBy('cg.name', 'ASC')->orderBy('s.full_name', 'ASC')->get()->getResultArray();
        $body = [];
        foreach ($rows as $i => $r) {
            $body[] = [$i + 1, $r['nis'], $r['nisn'], $r['full_name'], $r['gender'], $r['class_name'], student_statuses()[$r['status']] ?? $r['status']];
        }

        return [
            'filters' => ['class_group_id'],
            'head'    => ['No', 'NIS', 'NISN', 'Nama', 'L/P', 'Kelas', 'Status'],
            'body'    => $body,
            'csv'     => [['No', 'NIS', 'NISN', 'Nama', 'L/P', 'Kelas', 'Status'], $body],
        ];
    }

    private function reportScores(array $f): array
    {
        $list = (new AssessmentModel())
            ->select('assessments.id, assessments.title, assessments.assessment_date, t.code, cg.name AS class_name, s.name AS subject_name')
            ->join('assessment_types t', 't.id = assessments.assessment_type_id')
            ->join('class_groups cg', 'cg.id = assessments.class_group_id')
            ->join('subjects s', 's.id = assessments.subject_id')
            ->where('assessments.semester_id', (int) ($f['semester_id'] ?? Period::semesterId()))
            ->where('assessments.user_id', Auth::id())
            ->whereIn('assessments.subject_id', Lookup::teachingSubjectIds() ?? array_map('intval', array_keys(Lookup::subjects(false))) ?: [0])
            ->orderBy('assessment_date', 'DESC')->findAll();
        $opts = [];
        foreach ($list as $a) {
            $opts[$a['id']] = $a['code'] . ' · ' . $a['title'] . ' · ' . $a['class_name'] . ' · ' . $a['subject_name'];
        }
        $aid  = (int) ($f['assessment_id'] ?? array_key_first($opts));
        $aid  = isset($opts[$aid]) ? $aid : (int) array_key_first($opts); // asesmen di luar mapel yang diajarkan tidak bisa dibuka
        $body = [];
        $a    = $aid ? (new AssessmentModel())->where('user_id', Auth::id())->find($aid) : null;
        if ($a) {
            $scores = array_column((new ScoreModel())->where('assessment_id', $aid)->findAll(), null, 'student_id');
            foreach (RecapService::enrolledStudents((int) $a['class_group_id']) as $i => $st) {
                $sc     = $scores[$st['id']] ?? null;
                $body[] = [$i + 1, $st['nis'], $st['full_name'], $sc ? ((int) ($sc['is_missing'] ?? 0) ? 'Tidak kumpul' . (($a['missing_policy'] ?? 'nol') === 'nol' ? ' (0)' : '') : fmt_num($sc['score'])) : 'Belum diinput', $sc['notes'] ?? ''];
            }
        }

        return [
            'filters'      => ['semester_id', 'assessment_id'],
            'assessmentOptions' => $opts,
            'empty'        => ! $opts ? 'Belum ada asesmen untuk mapel yang Anda ajarkan pada semester ini.'
                : ($a && ! $body ? 'Kelas asesmen ini belum memiliki siswa aktif. Tempatkan siswa melalui Data Siswa (admin).' : null),
            'subtitle'     => $a ? ($opts[$aid] ?? '') . ' · Maks. ' . fmt_num($a['max_score']) : 'Belum ada asesmen pada semester ini.',
            'head'         => ['No', 'NIS', 'Nama', 'Nilai', 'Catatan'],
            'body'         => $body,
            'csv'          => [['No', 'NIS', 'Nama', 'Nilai', 'Catatan'], $body],
        ];
    }

    private function reportIncomplete(array $f): array
    {
        $m = (new AssessmentModel())
            ->select('assessments.*, t.code, cg.name AS class_name, s.name AS subject_name')
            ->join('assessment_types t', 't.id = assessments.assessment_type_id')
            ->join('class_groups cg', 'cg.id = assessments.class_group_id')
            ->join('subjects s', 's.id = assessments.subject_id')
            ->where('assessments.semester_id', (int) ($f['semester_id'] ?? Period::semesterId()))
            ->where('assessments.user_id', Auth::id());
        if (($taught = Lookup::teachingSubjectIds()) !== null) {
            $m->whereIn('assessments.subject_id', $taught ?: [0]);
        }
        foreach (['class_group_id', 'subject_id'] as $k) {
            if (! empty($f[$k])) {
                $m->where('assessments.' . $k, (int) $f[$k]);
            }
        }
        $body   = [];
        $list   = $m->orderBy('assessment_date', 'ASC')->findAll();
        $scored = [];
        if ($list) {
            foreach ((new ScoreModel())->select('assessment_id, student_id')->whereIn('assessment_id', array_column($list, 'id'))->findAll() as $s) {
                $scored[$s['assessment_id']][$s['student_id']] = true;
            }
        }
        $rosters = [];
        foreach ($list as $a) {
            $graded = $scored[$a['id']] ?? [];
            $rosters[$a['class_group_id']] ??= RecapService::enrolledStudents((int) $a['class_group_id']);
            foreach ($rosters[$a['class_group_id']] as $st) {
                if (! isset($graded[$st['id']])) {
                    $body[] = [fmt_date($a['assessment_date']), $a['code'] . ' · ' . $a['title'], $a['class_name'], $a['subject_name'], $st['nis'], $st['full_name']];
                }
            }
        }

        return [
            'filters' => ['semester_id', 'class_group_id', 'subject_id'],
            'head'    => ['Tanggal', 'Asesmen', 'Kelas', 'Mapel', 'NIS', 'Nama siswa'],
            'body'    => $body,
            'empty'   => 'Semua nilai sudah lengkap.',
            'csv'     => [['Tanggal', 'Asesmen', 'Kelas', 'Mapel', 'NIS', 'Nama siswa'], $body],
        ];
    }

    private function reportCalendar(array $f): array
    {
        $m = (new CalendarEventModel())->where('academic_year_id', Period::yearId());
        if (! empty($f['event_type'])) {
            $m->where('event_type', $f['event_type']);
        }
        $rows = $m->orderBy('start_date', 'ASC')->findAll();
        $body = array_map(static fn ($r) => [fmt_date($r['start_date']), fmt_date($r['end_date']), $r['title'], event_types()[$r['event_type']] ?? $r['event_type'], $r['notes']], $rows);

        return [
            'filters' => ['event_type'],
            'head'    => ['Mulai', 'Selesai', 'Kegiatan', 'Jenis', 'Catatan'],
            'body'    => $body,
            'csv'     => [['Mulai', 'Selesai', 'Kegiatan', 'Jenis', 'Catatan'], array_map(static fn ($r) => [$r['start_date'], $r['end_date'], $r['title'], $r['event_type'], $r['notes']], $rows)],
        ];
    }
}
