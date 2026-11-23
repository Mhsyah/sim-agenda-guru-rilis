<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\DutyRoster;
use App\Libraries\Lookup;
use App\Libraries\Period;

/**
 * Admin: lihat jadwal mengajar semua guru (ringkasan, kisi per guru, kisi per kelas). Hanya baca & cetak;
 * jadwal tetap dikelola oleh masing-masing guru di menu Jadwal.
 */
class TeacherSchedules extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $req     = $this->request;
        $mode    = in_array($req->getGet('mode'), ['guru', 'kelas'], true) ? $req->getGet('mode') : 'semua';
        $guruId  = (int) $req->getGet('guru');
        $kelasId = (int) $req->getGet('kelas');
        $q       = trim((string) $req->getGet('q'));
        $yearId  = (int) Period::yearId();
        $db      = db_connect();

        $teachers = $db->table('users')->select('id, name, nip, photo, gender, teacher_code')
            ->where('role', 'teacher')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
        $classes  = Lookup::classes();

        $rows = $db->table('teaching_schedules ts')
            ->select('ts.*, cg.name AS class_name, s.name AS subject_name, s.code AS subject_code, u.name AS teacher_name')
            ->join('class_groups cg', 'cg.id = ts.class_group_id')
            ->join('subjects s', 's.id = ts.subject_id')
            ->join('users u', 'u.id = ts.user_id', 'left')
            ->where('ts.academic_year_id', $yearId)
            ->orderBy('ts.day_of_week', 'ASC')->orderBy('ts.start_time', 'ASC')
            ->get()->getResultArray();

        // Ringkasan per guru: jumlah JP/minggu, kelas & mapel
        $sum = [];
        foreach ($rows as $s) {
            $u = (int) $s['user_id'];
            $sum[$u] ??= ['jp' => 0, 'blocks' => 0, 'classes' => [], 'subjects' => []];
            $sum[$u]['jp'] += self::jp($s['lesson_hours']);
            $sum[$u]['blocks']++;
            $sum[$u]['classes'][$s['class_name']]  = true;
            $sum[$u]['subjects'][$s['subject_name']] = true;
        }
        $list = [];
        foreach ($teachers as $t) {
            if ($q !== '' && stripos($t['name'] . ' ' . $t['nip'], $q) === false) {
                continue;
            }
            $x      = $sum[(int) $t['id']] ?? ['jp' => 0, 'blocks' => 0, 'classes' => [], 'subjects' => []];
            $cls    = array_keys($x['classes']);
            usort($cls, static fn ($a, $b) => self::classSort($a) <=> self::classSort($b));
            $list[] = $t + ['jp' => $x['jp'], 'blocks' => $x['blocks'], 'classes' => $cls, 'subjects' => array_keys($x['subjects'])];
        }

        // Kisi jadwal untuk guru / kelas yang dipilih
        $grid     = null;
        $selected = null;
        $sel      = [];
        if ($mode === 'guru' && $guruId) {
            $selected = current(array_filter($teachers, static fn ($t) => (int) $t['id'] === $guruId)) ?: null;
            $sel      = $selected ? array_filter($rows, static fn ($s) => (int) $s['user_id'] === $guruId) : [];
        } elseif ($mode === 'kelas' && $kelasId && isset($classes[$kelasId])) {
            $selected = ['id' => $kelasId, 'name' => $classes[$kelasId]];
            $sel      = array_filter($rows, static fn ($s) => (int) $s['class_group_id'] === $kelasId);
        }
        $dayPiket = [];
        if ($selected) {
            $grid = self::grid($sel, $mode);
            if ($mode === 'guru') {
                foreach (DutyRoster::forUser((int) $selected['id'], $yearId) as $p) {
                    $dayPiket[$p['day']][] = 'Piket ' . (DutyRoster::SHIFTS[$p['shift']] ?? $p['shift']);
                }
            } elseif ($sel) {
                $shift = min(array_column($sel, 'start_time')) < '12:00:00' ? 'pagi' : 'siang';
                foreach (DutyRoster::all($yearId)[$shift] ?? [] as $d => $ps) {
                    $dayPiket[$d] = array_column($ps, 'name');
                }
            }
        }

        $overall = $mode === 'semua' ? self::overall($rows, $teachers, $classes) : null;

        return $this->render('teacher_schedules/index', [
            'overall'  => $overall,
            'dayPiket' => $dayPiket,
            'ownKop'   => true, // kop dicetak di dalam lembar jadwal masing-masing
            'kopCfg'   => (\App\Libraries\KopSettings::useRequest() ?? \App\Libraries\KopSettings::get(self::schoolProfile())), // ?print[paper]=F4 dsb.; jangan 'kop': bentrok dgn partial kop
            'piket'    => $overall ? DutyRoster::all($yearId) : [],
            'title'    => 'Jadwal Mengajar Guru',
            'mode'     => $mode,
            'q'        => $q,
            'teachers' => $teachers,
            'classes'  => $classes,
            'list'     => $list,
            'grid'     => $grid,
            'selected' => $selected,
            'stats'    => [
                'teachers'  => count(array_filter($teachers, static fn ($t) => isset($sum[(int) $t['id']]))),
                'total'     => count($teachers),
                'jp'        => array_sum(array_column($sum, 'jp')),
                'classes'   => count(array_unique(array_column($rows, 'class_group_id'))),
                'blocks'    => count($rows),
            ],
            'selJp'    => array_sum(array_map(static fn ($s) => self::jp($s['lesson_hours']), $sel)),
            'selDays'  => count(array_unique(array_column($sel, 'day_of_week'))),
        ]);
    }

    /** Form atur guru piket per hari (Pagi & Siang). */
    public function piket()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $teachers = db_connect()->table('users')->select('id, name, teacher_code')->where('role', 'teacher')->where('is_active', 1)
            ->orderBy('name', 'ASC')->get()->getResultArray();

        return $this->render('teacher_schedules/piket', [
            'title'    => 'Atur Guru Piket',
            'teachers' => $teachers,
            'piket'    => DutyRoster::all(Period::yearId()),
        ]);
    }

    public function savePiket()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $yearId = (int) Period::yearId();
        $map    = (array) ($this->request->getPost('piket') ?? []);
        $valid  = array_map('intval', array_column(db_connect()->table('users')->select('id')->where('role', 'teacher')->get()->getResultArray(), 'id'));
        foreach ($map as $shift => $days) {
            foreach ((array) $days as $day => $ids) {
                $map[$shift][$day] = array_values(array_filter((array) $ids, static fn ($id) => in_array((int) $id, $valid, true)));
            }
        }
        $old = DutyRoster::all($yearId);
        $n   = DutyRoster::save($yearId, $map);
        Audit::log('update', 'duty_roster', $yearId, $old, DutyRoster::all($yearId));

        return redirect()->to(site_url('teacher-schedules'))->with('success', 'Jadwal piket disimpan (' . $n . ' petugas).');
    }

    /** Nama singkat untuk sel jadwal: tanpa gelar; kata pertama, atau dua kata bila kata pertama dipakai guru lain. */
    public static function shortNames(array $teachers): array
    {
        $first = [];
        $base  = [];
        foreach ($teachers as $t) {
            $w = preg_split('/\s+/', trim(explode(',', (string) $t['name'])[0])) ?: [''];
            $w = array_values(array_filter($w, static fn ($x) => ! preg_match('/^(Hj?|Dra?|Drs|Ir)\.?$/i', $x)));
            $base[(int) $t['id']] = $w;
            $k = mb_strtolower($w[0] ?? '');
            $first[$k] = ($first[$k] ?? 0) + 1;
        }
        $out = [];
        foreach ($base as $id => $w) {
            $out[$id] = ($first[mb_strtolower($w[0] ?? '')] ?? 0) > 1 && isset($w[1]) ? $w[0] . ' ' . $w[1] : ($w[0] ?? '');
        }

        return $out;
    }

    /**
     * Tabel jadwal keseluruhan seperti jadwal sekolah: dibagi Pagi & Siang (otomatis: kelas yang mulai sebelum 12.00 = Pagi).
     * Baris = hari × jam pelajaran (jeda tanpa pelajaran = Istirahat), kolom = kelas, sel = kode guru + mapel + nama singkat.
     */
    public static function overall(array $rows, array $teachers, array $classes): array
    {
        $short = self::shortNames($teachers);
        $code  = array_column($teachers, 'teacher_code', 'id');
        $first = [];
        foreach ($rows as $s) {
            $c = (int) $s['class_group_id'];
            $first[$c] = min($first[$c] ?? '99:99:99', $s['start_time']);
        }
        $shifts = ['pagi' => [], 'siang' => []];
        foreach ($classes as $id => $name) {
            if (isset($first[$id])) {
                $shifts[$first[$id] < '12:00:00' ? 'pagi' : 'siang'][$id] = $name;
            }
        }
        $out = [];
        foreach ($shifts as $key => $cls) {
            if (! $cls) {
                continue;
            }
            uksort($cls, static fn ($a, $b) => self::classSort($cls[$a]) <=> self::classSort($cls[$b]));
            $days = [];
            $min  = '99:99';
            $max  = '00:00';
            foreach (array_slice(day_names(), 0, 6, true) as $d => $dn) {
                $blocks = array_values(array_filter($rows, static fn ($s) => (int) $s['day_of_week'] === $d && isset($cls[(int) $s['class_group_id']])));
                if (! $blocks) {
                    continue;
                }
                // Waktu tiap "jam ke" diambil dari semua blok hari & sesi ini: blok "a-b" memberi awal jam a & akhir jam b;
                // awal jam k = akhir jam k-1 (bila tidak ada jeda). Yang tetap tak diketahui dibagi rata dalam bloknya.
                $span = [];
                $S    = [];
                $E    = [];
                foreach ($blocks as $b) {
                    if (! preg_match('/^\s*(\d+)\s*(?:[-–]\s*(\d+))?/', (string) $b['lesson_hours'], $m)) {
                        continue;
                    }
                    $j0 = (int) $m[1];
                    $j1 = (int) ($m[2] ?? '' ?: $j0);
                    $span[$b['id']] = [$j0, $j1];
                    $S[$j0] ??= $b['start_time'];
                    $E[$j1] ??= $b['end_time'];
                }
                if (! $span) {
                    continue;
                }
                $maxJ = max(array_column($span, 1));
                for ($pass = 0; $pass < 3; $pass++) {
                    for ($k = 1; $k <= $maxJ; $k++) {
                        if (! isset($E[$k]) && isset($S[$k + 1])) {
                            $E[$k] = $S[$k + 1];
                        }
                        if (! isset($S[$k]) && isset($E[$k - 1])) {
                            $S[$k] = $E[$k - 1];
                        }
                    }
                }
                // sisa: bagi rata sisa waktu blok mulai dari jam yang awalnya sudah diketahui
                $ts = static fn (string $t): int => (int) strtotime('2000-01-01 ' . $t);
                foreach ($blocks as $b) {
                    [$j0, $j1] = $span[$b['id']] ?? [0, 0];
                    for ($k = $j0; $j0 && $k <= $j1; $k++) {
                        $S[$k] ??= $k === $j0 ? $b['start_time'] : ($E[$k - 1] ?? null);
                        if (! isset($E[$k]) && isset($S[$k])) {
                            $end   = $E[$j1] ?? $b['end_time'];
                            $E[$k] = date('H:i:s', (int) round(($ts($S[$k]) + ($ts($end) - $ts($S[$k])) / ($j1 - $k + 1)) / 60) * 60);
                        }
                    }
                }
                $list = [];
                $prev = null;
                for ($k = 1; $k <= $maxJ; $k++) {
                    if (! isset($S[$k], $E[$k])) {
                        continue;
                    }
                    if ($prev !== null && $S[$k] > $prev) { // jeda tanpa pelajaran
                        $list[] = ['pukul' => substr($prev, 0, 5) . ' - ' . substr($S[$k], 0, 5), 'jam' => '', 'cells' => []];
                    }
                    $cells = [];
                    foreach ($blocks as $b) {
                        [$j0, $j1] = $span[$b['id']] ?? [0, 0];
                        if ($k >= $j0 && $k <= $j1) {
                            $cells[(int) $b['class_group_id']] = [
                                'uid'  => (int) $b['user_id'],
                                'kd'   => (string) ($code[$b['user_id']] ?? ''),
                                'mp'   => (string) ($b['subject_code'] ?: $b['subject_name']),
                                'clr'  => subject_color($b['subject_code']),
                                'name' => $short[(int) $b['user_id']] ?? ($b['teacher_name'] ?? ''),
                                'full' => $b['subject_name'] . ' · ' . ($b['teacher_name'] ?? ''),
                            ];
                        }
                    }
                    $list[] = ['pukul' => substr($S[$k], 0, 5) . ' - ' . substr($E[$k], 0, 5), 'jam' => (string) $k, 'cells' => $cells];
                    $prev   = $E[$k];
                    $min    = min($min, substr($S[$k], 0, 5));
                    $max    = max($max, substr($E[$k], 0, 5));
                }
                $days[$d] = ['name' => $dn, 'rows' => $list];
            }
            $out[$key] = ['title' => 'Jadwal ' . ucfirst($key), 'classes' => $cls, 'days' => $days, 'range' => $min . ' – ' . $max];
        }

        // Panel guru: kode, nama, JP & jadwal ringkas
        $panel = [];
        foreach ($teachers as $t) {
            $mine    = array_values(array_filter($rows, static fn ($s) => (int) $s['user_id'] === (int) $t['id']));
            $panel[] = [
                'id'    => (int) $t['id'],
                'kd'    => (string) ($t['teacher_code'] ?? ''),
                'name'  => $t['name'],
                'short' => $short[(int) $t['id']] ?? '',
                'jp'    => array_sum(array_map(static fn ($s) => self::jp($s['lesson_hours']), $mine)),
                'mapel' => implode(', ', array_unique(array_map(static fn ($s) => $s['subject_code'] ?: $s['subject_name'], $mine))),
                'items' => array_map(static fn ($s) => [
                    'd' => mb_substr(day_names()[(int) $s['day_of_week']] ?? '', 0, 3), 't' => fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']),
                    'j' => (string) ($s['lesson_hours'] ?? ''), 'c' => $s['class_name'], 'm' => $s['subject_code'] ?: $s['subject_name'],
                ], $mine),
            ];
        }
        usort($panel, static fn ($a, $b) => [$a['kd'] === '' ? 1 : 0, (int) $a['kd'], $a['name']] <=> [$b['kd'] === '' ? 1 : 0, (int) $b['kd'], $b['name']]);

        $legend = [];
        foreach ($rows as $s) {
            $legend[$s['subject_code'] ?: $s['subject_name']] = ['name' => $s['subject_name'], 'clr' => subject_color($s['subject_code'])];
        }
        ksort($legend);

        return ['shifts' => $out, 'panel' => $panel, 'legend' => $legend];
    }

    /** "1-2" → 2 JP, "3" → 1 JP, kosong → 1 JP. */
    public static function jp(?string $h): int
    {
        if (preg_match('/^\s*(\d+)\s*[-–]\s*(\d+)\s*$/', (string) $h, $m)) {
            return max(1, (int) $m[2] - (int) $m[1] + 1);
        }

        return 1;
    }

    /** Urutan kelas: VII A < VII B < VIII A < IX A. */
    public static function classSort(string $n): string
    {
        $g = roman_to_int(strtok($n, ' ') ?: '') ?: 99;

        return sprintf('%02d', $g) . $n;
    }

    /**
     * Jadwal mingguan per hari (kolom = hari, isi = blok berurutan menurut jam).
     * Isi per guru: "Kelas · Mapel"; per kelas: "Mapel · Guru".
     *
     * @return array<int, list<array{pukul: string, jam: string, main: string, sub: string}>>
     */
    private static function grid(array $rows, string $mode): array
    {
        usort($rows, static fn ($a, $b) => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']]);
        $out  = [];
        $last = [];
        foreach ($rows as $s) {
            $d = (int) $s['day_of_week'];
            // per kelas: jeda tanpa pelajaran di antara dua jam = istirahat
            if ($mode === 'kelas' && isset($last[$d]) && $last[$d] < $s['start_time']) {
                $out[$d][] = ['break' => true, 'pukul' => fmt_time($last[$d]) . '–' . fmt_time($s['start_time'])];
            }
            $last[$d] = max($last[$d] ?? '00:00:00', $s['end_time']);
            $out[$d][] = [
                'clr'   => subject_color($s['subject_code']),
                'pukul' => fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']),
                'jam'   => (string) ($s['lesson_hours'] ?? ''),
                'main'  => $mode === 'guru' ? $s['class_name'] : $s['subject_name'],
                'sub'   => $mode === 'guru' ? $s['subject_name'] : ($s['teacher_name'] ?? '—'),
            ];
        }

        return $out;
    }
}
