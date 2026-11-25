<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\GradeCalculator;
use App\Libraries\Period;
use App\Libraries\RecapService;
use App\Models\AgendaModel;
use App\Models\AssessmentModel;
use App\Models\CalendarEventModel;
use App\Models\EnrollmentModel;
use App\Models\GradingConfigItemModel;
use App\Models\GradingConfigModel;

class Dashboard extends BaseController
{
    public function index()
    {
        if (Auth::isAdmin()) {
            return $this->adminDashboard();
        }

        $yid   = Period::yearId();
        $sid   = Period::semesterId();
        $today = date('Y-m-d');
        $dow   = (int) date('N');

        $data = ['title' => 'Dashboard', 'today' => $today, 'setup' => [], 'todaySchedule' => [], 'upcoming' => [], 'incomplete' => [], 'invalidConfigs' => [], 'stats' => [], 'absentToday' => [], 'weeks' => [], 'recent' => [], 'recentTotal' => 0];

        // Data induk dikelola admin teknis; guru hanya diberi tahu bila belum lengkap.
        if (! $yid) {
            $data['setup'][] = ['Minta admin teknis membuat tahun pelajaran', site_url('settings/profile')];
        } else {
            if (! db_connect()->table('class_groups')->where('academic_year_id', $yid)->where('deleted_at', null)->countAllResults()) {
                $data['setup'][] = ['Minta admin teknis menambahkan kelas/rombel', site_url('students')];
            }
            if (! (new EnrollmentModel())->where('academic_year_id', $yid)->countAllResults()) {
                $data['setup'][] = ['Minta admin teknis mengimpor data siswa', site_url('students')];
            }
            if (! (new \App\Models\ScheduleModel())->where('user_id', Auth::id())->where('academic_year_id', $yid)->countAllResults()) {
                $data['setup'][] = ['Isi jadwal mengajar Anda', site_url('schedules/new')];
            }

            // Jadwal hari ini + status agenda.
            $uid      = Auth::id();
            $schedule = Schedules::grouped($yid, $dow, $uid)[$dow] ?? [];
            $written  = (new AgendaModel())->select('class_group_id, subject_id')->where('user_id', $uid)->where('agenda_date', $today)->findAll();
            $done     = [];
            foreach ($written as $w) {
                $done[$w['class_group_id'] . '-' . $w['subject_id']] = true;
            }
            foreach ($schedule as &$s) {
                $s['done'] = isset($done[$s['class_group_id'] . '-' . $s['subject_id']]);
            }
            unset($s);
            $data['todaySchedule'] = $schedule;
            $data['holiday']       = Calendar::holidayOn($today);
            $data['activity']      = \App\Libraries\ActivityFeed::forUser((int) $uid, 8);
            $data['dutyToday']     = array_values(array_filter(\App\Libraries\DutyRoster::forUser((int) $uid, Period::yearId()), static fn ($p) => $p['day'] === (int) date('N', strtotime($today))));

            $myClasses = array_values(array_unique(array_merge(
                array_column((new \App\Models\ScheduleModel())->select('class_group_id')->where('user_id', $uid)->where('academic_year_id', $yid)->findAll(), 'class_group_id'),
                array_column($written, 'class_group_id')
            )));
            $data['absentToday'] = (new \App\Models\AttendanceModel())
                ->select('attendances.status, attendances.notes, s.full_name, cg.name AS class_name, sj.name AS subject_name')
                ->join('teaching_agendas a', 'a.id = attendances.teaching_agenda_id')
                ->join('students s', 's.id = attendances.student_id')
                ->join('class_groups cg', 'cg.id = a.class_group_id')
                ->join('subjects sj', 'sj.id = a.subject_id')
                ->where('a.agenda_date', $today)->where('a.deleted_at', null)
                ->whereIn('a.class_group_id', $myClasses ?: [0])
                ->where('attendances.status !=', 'H')
                ->orderBy('cg.name', 'ASC')->orderBy('s.full_name', 'ASC')->findAll(30);

            $data['upcoming'] = (new CalendarEventModel())->where('end_date >=', $today)
                ->where('start_date <=', date('Y-m-d', strtotime('+21 days')))
                ->orderBy('start_date', 'ASC')->findAll(6);

            // Asesmen dengan nilai belum lengkap.
            if ($sid) {
                $sc = db_connect()->prefixTable('assessment_scores');
                $en = db_connect()->prefixTable('student_enrollments');
                $as = db_connect()->prefixTable('assessments');
                $st = db_connect()->prefixTable('students');
                $rows = (new AssessmentModel())
                    ->select('assessments.id, assessments.title, t.code, cg.name AS class_name, s.name AS subject_name')
                    ->select("(SELECT COUNT(*) FROM {$sc} x WHERE x.assessment_id = {$as}.id) AS graded,
                        (SELECT COUNT(*) FROM {$en} e JOIN {$st} st ON st.id = e.student_id AND st.deleted_at IS NULL WHERE e.class_group_id = {$as}.class_group_id AND e.status = 'aktif') AS enrolled", false)
                    ->join('assessment_types t', 't.id = assessments.assessment_type_id')
                    ->join('class_groups cg', 'cg.id = assessments.class_group_id')
                    ->join('subjects s', 's.id = assessments.subject_id')
                    ->where('assessments.semester_id', $sid)->where('assessments.user_id', $uid)
                    ->orderBy('assessments.assessment_date', 'DESC')->findAll(30);
                $data['incomplete'] = array_slice(array_values(array_filter($rows, static fn ($r) => (int) $r['graded'] < (int) $r['enrolled'])), 0, 5);

                foreach ((new GradingConfigModel())->where('user_id', $uid)->where('academic_year_id', $yid)->where('semester_id', $sid)->where('is_active', 1)->findAll() as $c) {
                    $check = GradeCalculator::validateWeights(RecapService::weightsOf($c));
                    if (! $check['valid']) {
                        $data['invalidConfigs'][] = $c + ['sum' => $check['sum']];
                    }
                }
            }

            // Tren 8 minggu: jumlah agenda & persentase kehadiran.
            $weeks = self::weeklySeries($uid, 8);
            $cur   = $weeks[count($weeks) - 1];
            $prev  = $weeks[count($weeks) - 2];
            $pct   = static fn ($w) => $w['total'] > 0 ? $w['present'] / $w['total'] * 100 : null;
            $myStudents = $myClasses ? (new EnrollmentModel())->whereIn('class_group_id', $myClasses)->where('status', 'aktif')->countAllResults() : 0;

            $data['weeks'] = $weeks;
            $data['stats'] = [
                ['label' => 'Agenda minggu ini', 'value' => (string) $cur['agendas'], 'icon' => 'pen', 'url' => site_url('agendas'),
                    'trend' => self::trend($cur['agendas'], $prev['agendas'], false), 'note' => 'vs ' . $prev['agendas'] . ' minggu lalu'],
                ['label' => 'Kehadiran siswa', 'value' => $pct($cur) === null ? '—' : fmt_num($pct($cur), 1) . '%', 'icon' => 'check', 'url' => site_url('reports/attendance'),
                    'trend' => ($pct($cur) !== null && $pct($prev) !== null) ? self::trend($pct($cur), $pct($prev), true) : null,
                    'note' => $cur['total'] ? $cur['present'] . ' dari ' . $cur['total'] . ' kehadiran' : 'belum ada daftar hadir'],
                ['label' => 'Siswa yang diajar', 'value' => number_format($myStudents, 0, ',', '.'), 'icon' => 'users', 'url' => site_url('students'),
                    'trend' => null, 'note' => count($myClasses) . ' kelas dari jadwal Anda'],
                ['label' => 'Nilai belum lengkap', 'value' => (string) count($data['incomplete']), 'icon' => 'alert', 'url' => site_url('reports/incomplete'),
                    'trend' => null, 'note' => $sid ? (new AssessmentModel())->where('user_id', $uid)->where('semester_id', $sid)->countAllResults() . ' asesmen semester ini' : '—', 'warn' => count($data['incomplete']) > 0],
            ];

            // Agenda terbaru (tabel)
            $at = db_connect()->prefixTable('attendances');
            $ag = db_connect()->prefixTable('teaching_agendas');
            $data['recent'] = (new AgendaModel())
                ->select('teaching_agendas.id, teaching_agendas.agenda_date, teaching_agendas.topic, teaching_agendas.status, teaching_agendas.lesson_hours, cg.name AS class_name, s.name AS subject_name')
                ->select("(SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = {$ag}.id) AS att_total,
                    (SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = {$ag}.id AND x.status = 'H') AS att_present", false)
                ->join('class_groups cg', 'cg.id = teaching_agendas.class_group_id')
                ->join('subjects s', 's.id = teaching_agendas.subject_id')
                ->where('teaching_agendas.user_id', $uid)
                ->orderBy('agenda_date', 'DESC')->orderBy('teaching_agendas.id', 'DESC')->findAll(6);
            $data['recentTotal'] = (new AgendaModel())->where('user_id', $uid)->countAllResults();
        }

        return $this->render('dashboard/index', $data);
    }

    /** Riwayat aktivitas lengkap milik pengguna yang sedang login. */
    public function activity()
    {
        $uid   = (int) Auth::id();
        $per   = 30;
        $total = \App\Libraries\ActivityFeed::countForUser($uid);
        $pages = max(1, (int) ceil($total / $per));
        $page  = min($pages, max(1, (int) $this->request->getGet('page')));

        return $this->render('dashboard/activity', [
            'title' => 'Riwayat Aktivitas',
            'items' => \App\Libraries\ActivityFeed::forUser($uid, $per, ($page - 1) * $per),
            'page'  => $page, 'pages' => $pages, 'total' => $total,
        ]);
    }

    /**
     * Seri mingguan (Senin–Minggu) untuk grafik: jumlah agenda + kehadiran.
     *
     * @return list<array{label: string, start: string, agendas: int, present: int, total: int}>
     */
    public static function weeklySeries(?int $userId, int $n = 8): array
    {
        $db    = db_connect();
        $start = date('Y-m-d', strtotime('monday this week -' . ($n - 1) . ' weeks'));
        $weeks = [];
        for ($i = 0; $i < $n; $i++) {
            $ws         = date('Y-m-d', strtotime($start . ' +' . $i . ' weeks'));
            $weeks[$ws] = ['label' => (int) substr($ws, 8, 2) . ' ' . id_months(true)[(int) substr($ws, 5, 2)], 'start' => $ws, 'agendas' => 0, 'present' => 0, 'total' => 0];
        }
        $ag  = $db->prefixTable('teaching_agendas');
        $at  = $db->prefixTable('attendances');
        $w   = 'a.deleted_at IS NULL AND a.agenda_date >= ? AND a.agenda_date <= ?' . ($userId ? ' AND a.user_id = ?' : '');
        $b   = $userId ? [$start, date('Y-m-d'), $userId] : [$start, date('Y-m-d')];
        $rows = $db->query("SELECT a.id, a.agenda_date,
                SUM(CASE WHEN x.status = 'H' THEN 1 ELSE 0 END) AS present, COUNT(x.id) AS total
            FROM {$ag} a LEFT JOIN {$at} x ON x.teaching_agenda_id = a.id
            WHERE {$w} GROUP BY a.id, a.agenda_date", $b)->getResultArray();
        foreach ($rows as $r) {
            $ws = date('Y-m-d', strtotime('monday this week', strtotime($r['agenda_date'])));
            if (isset($weeks[$ws])) {
                $weeks[$ws]['agendas']++;
                $weeks[$ws]['present'] += (int) $r['present'];
                $weeks[$ws]['total']   += (int) $r['total'];
            }
        }

        return array_values($weeks);
    }

    /** Badge tren: arah & teks perubahan (persen relatif, atau poin untuk nilai persentase). */
    public static function trend(float $now, float $before, bool $points): array
    {
        $diff = $points ? $now - $before : ($before > 0 ? ($now - $before) / $before * 100 : ($now > 0 ? 100 : 0));
        $dir  = abs($diff) < 0.05 ? 'flat' : ($diff > 0 ? 'up' : 'down');

        return ['dir' => $dir, 'text' => fmt_num(abs($diff), 1) . ($points ? ' poin' : '%')];
    }

    /** Dashboard admin teknis: kelengkapan data induk, ringkasan sekolah, kesehatan sistem. */
    private function adminDashboard()
    {
        $db      = db_connect();
        $yid     = Period::yearId();
        $profile = self::schoolProfile() ?? [];
        $count   = static fn (string $t, array $w = []) => $db->table($t)->where($w)->countAllResults();

        $teachers = $count('users', ['role' => 'teacher', 'is_active' => 1]);
        $subjects = $db->table('subjects')->where('deleted_at', null)->where('is_active', 1)->countAllResults();
        $classes  = $yid ? $db->table('class_groups')->where('academic_year_id', $yid)->where('deleted_at', null)->countAllResults() : 0;
        $students = $yid ? $db->table('student_enrollments e')->join('students s', 's.id = e.student_id')
            ->where('e.academic_year_id', $yid)->where('e.status', 'aktif')->where('s.deleted_at', null)->countAllResults() : 0;
        $noClass  = $yid ? $db->table('students s')->join('student_enrollments e', 'e.student_id = s.id AND e.academic_year_id = ' . (int) $yid, 'left')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')->where('e.id', null)->countAllResults() : 0;

        $setup = [
            ['Lengkapi profil & kop sekolah', ! empty($profile['school_address']) && ! empty($profile['principal_name']), site_url('settings/school')],
            ['Unggah logo sekolah', ! empty($profile['school_logo']), site_url('settings/school')],
            ['Tahun pelajaran aktif', (bool) $yid, site_url('imports#years')],
            ['Mata pelajaran', $subjects > 0, site_url('imports#subjects')],
            ['Kelas/rombel tahun ini', $classes > 0, site_url('imports#classes')],
            ['Akun guru', $teachers > 0, site_url('imports#teachers')],
            ['Siswa ditempatkan di kelas', $students > 0, site_url('imports#students')],
        ];

        // Aktivitas guru minggu ini (jumlah saja; isi agenda tidak ditampilkan ke admin).
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $ag        = $db->prefixTable('teaching_agendas');
        $us        = $db->prefixTable('users');
        $ka        = $db->prefixTable('teacher_activities');
        $activity  = $db->query(
            "SELECT u.id, u.name, u.last_login_at,
                (SELECT COUNT(*) FROM {$ag} a WHERE a.user_id = u.id AND a.deleted_at IS NULL AND a.agenda_date >= ?) AS week_agendas,
                (SELECT COUNT(*) FROM {$ka} k WHERE k.user_id = u.id AND k.activity_date >= ?) AS week_acts
             FROM {$us} u WHERE u.role = 'teacher' AND u.is_active = 1 ORDER BY week_agendas DESC, u.name ASC LIMIT 10",
            [$weekStart, $weekStart]
        )->getResultArray();

        $semId = (int) Period::semesterId();

        return $this->render('dashboard/admin', [
            'title'    => 'Dashboard',
            'quality'  => $semId ? \App\Libraries\DataQuality::forSemester($semId) : null,
            'qtrend'   => $semId ? \App\Libraries\DataQuality::trend($semId) : [],
            'setup'    => $setup,
            'done'     => count(array_filter($setup, static fn ($s) => $s[1])),
            'tiles'    => [
                ['Guru aktif', $teachers, 'users', site_url('users?role=teacher')],
                ['Siswa aktif', $students, 'users', site_url('students')],
                ['Kelas/rombel', $classes, 'grid', site_url('classes')],
                ['Mata pelajaran', $subjects, 'book', site_url('subjects')],
            ],
            'noClass'  => $noClass,
            'activity' => $activity,
            'weeks'    => self::weeklySeries(null, 8),
            'neverLoggedIn' => $db->table('users')->where('role', 'teacher')->where('is_active', 1)->where('last_login_at', null)->countAllResults(),
            'sys'      => SystemStatus::collect(),
        ]);
    }
}
