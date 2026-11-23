<?php

namespace App\Libraries;

use App\Controllers\Students;
use Throwable;

/**
 * Sinkronisasi data dengan Dapodik (Dapodikdasmen).
 *
 * Sumber data (dinormalisasi ke bentuk yang sama):
 * 1) Web Service Dapodik — aktifkan di aplikasi Dapodik (menu Pengaturan → Web Service), isi IP server Agenda Guru
 *    dan salin token. Alamat bawaan: http://IP-SERVER-DAPODIK:5774. Hanya dapat diakses dari jaringan sekolah.
 * 2) Berkas ekspor Dapodik (.xlsx/.csv) — untuk aplikasi yang berjalan di hosting internet.
 *
 * Alur: tarik/unggah → bandingkan dengan data Agenda Guru (baru · berubah · sama · tidak ada di Dapodik) →
 * admin memilih baris yang diterapkan. Data di Agenda Guru TIDAK pernah dihapus otomatis.
 * Hanya kolom yang dibutuhkan yang diambil (tanpa NIK/alamat/data orang tua) sesuai prinsip minimisasi data (UU PDP).
 */
final class Dapodik
{
    public const KINDS = [
        'gtk'     => ['label' => 'GTK (guru)', 'method' => 'getGtk'],
        'siswa'   => ['label' => 'Peserta didik', 'method' => 'getPesertaDidik'],
        'rombel'  => ['label' => 'Rombel (kelas)', 'method' => 'getRombonganBelajar'],
    ];
    public const FIELDS = [
        'gtk'    => ['name' => 'Nama', 'nip' => 'NIP', 'gender' => 'L/P'],
        'siswa'  => ['name' => 'Nama', 'nis' => 'NIS', 'nisn' => 'NISN', 'gender' => 'L/P', 'birth_date' => 'Tgl lahir', 'class' => 'Rombel'],
        'rombel' => ['name' => 'Nama rombel', 'grade' => 'Tingkat'],
    ];

    /** Untuk pengujian: respons Web Service palsu per metode (tanpa jaringan). */
    public static array $fake = [];

    // ---------------------------------------------------------------- Pengaturan & rahasia

    public static function dir(): string
    {
        $d = WRITEPATH . 'dapodik' . DIRECTORY_SEPARATOR;
        if (! is_dir($d)) {
            mkdir($d, 0750, true);
            file_put_contents($d . 'index.html', '');
            file_put_contents($d . '.htaccess', "Require all denied\n");
        }

        return $d;
    }

    public static function settings(): array
    {
        $f = self::dir() . 'settings.json';
        $s = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;

        return (is_array($s) ? $s : []) + ['url' => '', 'npsn' => '', 'token' => '', 'last' => []];
    }

    public static function saveSettings(array $s): void
    {
        file_put_contents(self::dir() . 'settings.json', json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** Kunci rahasia aplikasi: encryption.key bila ada, jika tidak berkas acak di writable (tidak ikut backup database). */
    private static function key(): string
    {
        $k = (string) (config('Encryption')->key ?? '');
        if ($k !== '') {
            return hash('sha256', $k, true);
        }
        $f = WRITEPATH . '.app-secret';
        if (! is_file($f)) {
            file_put_contents($f, bin2hex(random_bytes(32)));
            @chmod($f, 0600);
        }

        return hash('sha256', trim((string) file_get_contents($f)), true);
    }

    public static function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $enc = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $enc);
    }

    public static function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $out === false ? '' : $out;
    }

    // ---------------------------------------------------------------- Ambil data

    /** @return list<array>|string baris ternormalisasi atau pesan galat */
    public static function fetch(string $kind)
    {
        $s = self::settings();
        $method = self::KINDS[$kind]['method'];
        if (isset(self::$fake[$method])) {
            $json = self::$fake[$method];
        } else {
            $token = self::decrypt((string) $s['token']);
            if ($s['url'] === '' || $s['npsn'] === '' || $token === '') {
                return 'Isi alamat Web Service, NPSN, dan token Dapodik terlebih dahulu.';
            }
            try {
                $res = service('curlrequest', ['timeout' => 25, 'connect_timeout' => 6, 'http_errors' => false])
                    ->get(rtrim($s['url'], '/') . '/WebService/' . $method, [
                        'query'   => ['npsn' => $s['npsn']],
                        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
                    ]);
            } catch (Throwable $e) {
                return 'Tidak dapat terhubung ke Dapodik (' . mb_substr($e->getMessage(), 0, 120) . '). Pastikan aplikasi Dapodik menyala dan komputer ini satu jaringan dengan server Dapodik.';
            }
            if ($res->getStatusCode() === 401 || $res->getStatusCode() === 403) {
                return 'Token Dapodik ditolak (HTTP ' . $res->getStatusCode() . '). Periksa token & IP yang didaftarkan di menu Web Service Dapodik.';
            }
            if ($res->getStatusCode() !== 200) {
                return 'Dapodik mengembalikan HTTP ' . $res->getStatusCode() . '.';
            }
            $json = json_decode((string) $res->getBody(), true);
        }
        if (! is_array($json)) {
            return 'Respons Dapodik bukan JSON yang valid.';
        }
        $rows = $json['rows'] ?? $json['data'] ?? (array_is_list($json) ? $json : []);
        if (! is_array($rows)) {
            return 'Format respons Dapodik tidak dikenali.';
        }

        return array_values(array_filter(array_map(static fn ($r) => is_array($r) ? self::normalize($kind, $r) : null, $rows)));
    }

    /** Baris Web Service / berkas → bentuk standar. Nama kolom Dapodik dicoba beberapa variasi. */
    public static function normalize(string $kind, array $r): ?array
    {
        $r   = array_change_key_case($r, CASE_LOWER);
        $get = static function (array $keys) use ($r): string {
            foreach ($keys as $k) {
                if (isset($r[$k]) && trim((string) $r[$k]) !== '') {
                    return trim((string) $r[$k]);
                }
            }

            return '';
        };
        $name = preg_replace('/\s+/', ' ', $get(['nama', 'nama_lengkap', 'name', 'nama_rombel']));
        if ($name === '') {
            return null;
        }
        $g = strtoupper(substr($get(['jenis_kelamin', 'jk', 'l/p', 'gender']), 0, 1));
        $g = in_array($g, ['L', 'P'], true) ? $g : '';
        if ($kind === 'gtk') {
            $type = mb_strtolower($get(['jenis_ptk_id_str', 'jenis_ptk', 'jenis ptk']));
            if ($type !== '' && ! str_contains($type, 'guru') && ! str_contains($type, 'kepala sekolah')) {
                return null; // tenaga kependidikan non-guru tidak dibuatkan akun
            }

            return ['name' => $name, 'nip' => preg_replace('/\D/', '', $get(['nip'])), 'gender' => $g];
        }
        if ($kind === 'rombel') {
            $grade = $get(['tingkat_pendidikan_id', 'tingkat', 'tingkat_pendidikan', 'kelas']);
            $grade = ctype_digit($grade) ? (int) $grade : (roman_to_int(strtoupper(preg_replace('/^kelas\s*/i', '', $grade))) ?: null);

            return ['name' => $name, 'grade' => $grade ? (string) $grade : ''];
        }
        $birth = $get(['tanggal_lahir', 'tgl_lahir', 'tanggal lahir']);
        $birth = SimpleXlsx::excelDate($birth) ?? (MasterImporter::normDate(substr($birth, 0, 10)) ?? '');

        return [
            'name' => $name, 'nisn' => preg_replace('/\D/', '', $get(['nisn'])), 'nis' => $get(['nipd', 'nis', 'no_induk']),
            'gender' => $g, 'birth_date' => $birth, 'class' => preg_replace('/\s+/', ' ', $get(['nama_rombel', 'rombel_saat_ini', 'rombel', 'kelas'])),
        ];
    }

    /** Berkas ekspor Dapodik (.xlsx/.csv): baris judul dilewati, baris header dicari otomatis. */
    public static function parseFile(string $kind, string $path, string $clientName)
    {
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $rows = SimpleXlsx::rows($path);
            if (is_string($rows)) {
                return $rows;
            }
        } elseif (in_array($ext, ['csv', 'txt'], true)) {
            $text  = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
            $lines = array_values(array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", $text)), static fn ($l) => trim($l) !== ''));
            $delim = $lines && substr_count($lines[0] . ($lines[1] ?? ''), ';') > substr_count($lines[0] . ($lines[1] ?? ''), ',') ? ';' : ',';
            $rows  = array_map(static fn ($l) => array_map('trim', str_getcsv($l, $delim, '"', '')), $lines);
        } else {
            return 'Gunakan berkas .xlsx atau .csv hasil ekspor Dapodik (berkas .xls lama: buka di Excel lalu simpan sebagai .xlsx).';
        }
        $alias = [
            'nama' => 'nama', 'nama lengkap' => 'nama', 'nama rombel' => 'nama_rombel', 'nisn' => 'nisn', 'nipd' => 'nipd', 'nis' => 'nis',
            'jk' => 'jk', 'l/p' => 'jk', 'jenis kelamin' => 'jk', 'tanggal lahir' => 'tanggal_lahir', 'tgl lahir' => 'tanggal_lahir',
            'rombel saat ini' => 'rombel_saat_ini', 'rombel' => 'rombel', 'kelas' => 'kelas', 'nip' => 'nip', 'jenis ptk' => 'jenis_ptk',
            'tingkat' => 'tingkat', 'tingkat pendidikan' => 'tingkat_pendidikan',
        ];
        $head = null;
        $out  = [];
        foreach ($rows as $row) {
            $norm = array_map(static fn ($c) => trim(mb_strtolower(preg_replace('/\s+/', ' ', (string) $c))), $row);
            if ($head === null) {
                $hits = array_intersect($norm, array_keys($alias));
                if (in_array('nama', $norm, true) || in_array('nama rombel', $norm, true)) {
                    if (count($hits) >= 2) {
                        $head = array_map(static fn ($c) => $alias[$c] ?? null, $norm);
                    }
                }

                continue;
            }
            $assoc = [];
            foreach ($head as $i => $k) {
                if ($k && ! isset($assoc[$k])) {
                    $assoc[$k] = (string) ($row[$i] ?? '');
                }
            }
            if (($n = self::normalize($kind, $assoc)) !== null) {
                $out[] = $n;
            }
        }
        if ($head === null) {
            return 'Baris judul kolom (Nama, NISN/NIP, JK, …) tidak ditemukan. Pastikan berkas adalah ekspor ' . self::KINDS[$kind]['label'] . ' dari Dapodik.';
        }

        return $out;
    }

    // ---------------------------------------------------------------- Bandingkan

    private static function personKey(string $name): string
    {
        $n = mb_strtolower(preg_replace('/,.*$/', '', $name)); // buang gelar di belakang koma
        $n = preg_replace('/\b(drs?|hj?|ir|prof)\.?\s+/u', '', $n);

        return preg_replace('/[^a-z]/', '', $n);
    }

    /**
     * @return array{items: list<array>, counts: array<string,int>}
     *   item: key, status (new|changed|same|missing), data (Dapodik), local, changes [field => [lama, baru]]
     */
    public static function diff(string $kind, array $rows): array
    {
        $db  = db_connect();
        $yid = (int) Period::yearId();
        $items = [];
        if ($kind === 'gtk') {
            $locals = $db->table('users')->select('id, name, nip, gender')->where('role', 'teacher')->get()->getResultArray();
            $byNip  = [];
            $byName = [];
            foreach ($locals as $l) {
                if (($l['nip'] ?? '') !== '') {
                    $byNip[preg_replace('/\D/', '', $l['nip'])] = $l;
                }
                $byName[self::personKey($l['name'])] = $l;
            }
            $map = static fn ($l) => ['name' => $l['name'], 'nip' => preg_replace('/\D/', '', (string) $l['nip']), 'gender' => (string) $l['gender']];
            $matcher = static fn ($r) => ($r['nip'] !== '' ? ($byNip[$r['nip']] ?? null) : null) ?? ($byName[self::personKey($r['name'])] ?? null);
            $keyOf = static fn ($r) => $r['nip'] !== '' ? 'nip:' . $r['nip'] : 'nama:' . self::personKey($r['name']);
        } elseif ($kind === 'rombel') {
            $locals = $db->table('class_groups')->select('id, name, grade_level')->where('academic_year_id', $yid)->where('deleted_at', null)->get()->getResultArray();
            $byName = [];
            foreach ($locals as $l) {
                $byName[mb_strtolower(preg_replace('/\s+/', ' ', $l['name']))] = $l;
            }
            $byNip = [];
            $map = static fn ($l) => ['name' => $l['name'], 'grade' => (string) ($l['grade_level'] ?? '')];
            $matcher = static fn ($r) => $byName[mb_strtolower($r['name'])] ?? null;
            $keyOf = static fn ($r) => 'rombel:' . mb_strtolower($r['name']);
        } else {
            $cls = $db->prefixTable('class_groups');
            $en  = $db->prefixTable('student_enrollments');
            $locals = $db->table('students s')->select("s.id, s.full_name AS name, s.nis, s.nisn, s.gender, s.birth_date, s.status,
                (SELECT c.name FROM {$en} e JOIN {$cls} c ON c.id = e.class_group_id WHERE e.student_id = s.id AND e.academic_year_id = {$yid} LIMIT 1) AS class", false)
                ->where('s.deleted_at', null)->get()->getResultArray();
            $byNip = [];
            $byName = [];
            foreach ($locals as $l) {
                foreach (['nisn', 'nis'] as $k) {
                    if (($l[$k] ?? '') !== '') {
                        $byNip[$k . ':' . $l[$k]] = $l;
                    }
                }
                $byName[self::personKey($l['name']) . '|' . $l['birth_date']] = $l;
            }
            $map = static fn ($l) => ['name' => $l['name'], 'nis' => (string) $l['nis'], 'nisn' => (string) $l['nisn'], 'gender' => (string) $l['gender'], 'birth_date' => (string) $l['birth_date'], 'class' => (string) $l['class']];
            $matcher = static fn ($r) => ($r['nisn'] !== '' ? ($byNip['nisn:' . $r['nisn']] ?? null) : null) ?? ($r['nis'] !== '' ? ($byNip['nis:' . $r['nis']] ?? null) : null)
                ?? ($byName[self::personKey($r['name']) . '|' . $r['birth_date']] ?? null);
            $keyOf = static fn ($r) => $r['nisn'] !== '' ? 'nisn:' . $r['nisn'] : ($r['nis'] !== '' ? 'nis:' . $r['nis'] : 'nama:' . self::personKey($r['name']) . '|' . $r['birth_date']);
            $locals = array_values(array_filter($locals, static fn ($l) => $l['status'] === 'aktif'));
        }

        $matched = [];
        $seen    = [];
        foreach ($rows as $r) {
            $key = $keyOf($r);
            if (isset($seen[$key])) {
                continue; // baris kembar di sumber Dapodik
            }
            $seen[$key] = true;
            $l = $matcher($r);
            if (! $l) {
                $items[] = ['key' => $key, 'status' => 'new', 'data' => $r, 'local' => null, 'changes' => []];

                continue;
            }
            $matched[(int) $l['id']] = true;
            $cur     = $map($l);
            $changes = [];
            foreach (array_keys(self::FIELDS[$kind]) as $f) {
                $new = (string) ($r[$f] ?? '');
                $old = (string) ($cur[$f] ?? '');
                // kolom kosong di Dapodik tidak menimpa data yang sudah ada; nama dibandingkan tanpa beda huruf besar/kecil & spasi
                if ($new !== '' && ($f === 'name' ? mb_strtolower(preg_replace('/\s+/', ' ', $new)) !== mb_strtolower(preg_replace('/\s+/', ' ', $old)) : $new !== $old)) {
                    $changes[$f] = [$old, $new];
                }
            }
            $items[] = ['key' => $key, 'status' => $changes ? 'changed' : 'same', 'data' => $r, 'local' => $cur + ['id' => (int) $l['id']], 'changes' => $changes];
        }
        foreach ($locals as $l) {
            if (! isset($matched[(int) $l['id']])) {
                $items[] = ['key' => 'local:' . $l['id'], 'status' => 'missing', 'data' => null, 'local' => $map($l) + ['id' => (int) $l['id']], 'changes' => []];
            }
        }
        $counts = ['new' => 0, 'changed' => 0, 'same' => 0, 'missing' => 0];
        foreach ($items as $it) {
            $counts[$it['status']]++;
        }
        $order = ['changed' => 0, 'new' => 1, 'missing' => 2, 'same' => 3];
        usort($items, static fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return ['items' => $items, 'counts' => $counts];
    }

    // ---------------------------------------------------------------- Terapkan

    /** @return array{created:int, updated:int, credentials: list<array{0:string,1:string}>, skipped: list<string>} */
    public static function apply(string $kind, array $rows, array $keys): array
    {
        $diff = self::diff($kind, $rows);
        $want = array_flip($keys);
        $db   = db_connect();
        $yid  = (int) Period::yearId();
        $now  = date('Y-m-d H:i:s');
        $out  = ['created' => 0, 'updated' => 0, 'credentials' => [], 'skipped' => []];
        $classes = array_column($db->table('class_groups')->select('id, name')->where('academic_year_id', $yid)->where('deleted_at', null)->get()->getResultArray(), 'id', 'name');
        $classes = array_change_key_case($classes, CASE_LOWER);

        foreach ($diff['items'] as $it) {
            if (! isset($want[$it['key']]) || ! in_array($it['status'], ['new', 'changed'], true)) {
                continue;
            }
            $d = $it['data'];
            try {
                if ($kind === 'rombel') {
                    $grade = $d['grade'] !== '' ? (int) $d['grade'] : null;
                    if ($it['status'] === 'new') {
                        $db->table('class_groups')->insert(['academic_year_id' => $yid, 'name' => $d['name'], 'grade_level' => $grade, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                        $out['created']++;
                    } else {
                        $db->table('class_groups')->where('id', $it['local']['id'])->update(['grade_level' => $grade, 'updated_at' => $now]);
                        $out['updated']++;
                    }
                } elseif ($kind === 'gtk') {
                    if ($it['status'] === 'new') {
                        $email = self::teacherEmail($d['name']);
                        $db->table('users')->insert(['name' => $d['name'], 'email' => $email, 'nip' => $d['nip'] ?: null, 'gender' => $d['gender'] ?: null,
                            'password_hash' => password_hash(MasterImporter::DEFAULT_TEACHER_PASSWORD, PASSWORD_DEFAULT), 'role' => 'teacher', 'is_active' => 1, 'theme_pref' => 'system', 'created_at' => $now, 'updated_at' => $now]);
                        $out['created']++;
                        $out['credentials'][] = [$email, MasterImporter::DEFAULT_TEACHER_PASSWORD];
                    } else {
                        $upd = [];
                        foreach ($it['changes'] as $f => [, $new]) {
                            $upd[$f] = $new;
                        }
                        $db->table('users')->where('id', $it['local']['id'])->update($upd + ['updated_at' => $now]);
                        $out['updated']++;
                    }
                } else {
                    $cid = $d['class'] !== '' ? ($classes[mb_strtolower($d['class'])] ?? null) : null;
                    if ($d['class'] !== '' && ! $cid) {
                        $out['skipped'][] = $d['name'] . ': rombel "' . $d['class'] . '" belum ada (sinkronkan Rombel dulu)';
                    }
                    $data = ['full_name' => $d['name'], 'nis' => $d['nis'] ?: null, 'nisn' => $d['nisn'] ?: null, 'gender' => $d['gender'] ?: null, 'birth_date' => $d['birth_date'] ?: null];
                    if ($it['status'] === 'new') {
                        $db->table('students')->insert($data + ['status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
                        $sid = (int) $db->insertID();
                        $out['created']++;
                    } else {
                        $sid = (int) $it['local']['id'];
                        $upd = [];
                        foreach ($it['changes'] as $f => [, $new]) {
                            if ($f !== 'class') {
                                $upd[$f === 'name' ? 'full_name' : $f] = $new;
                            }
                        }
                        if ($upd) {
                            $db->table('students')->where('id', $sid)->update($upd + ['updated_at' => $now]);
                        }
                        $out['updated']++;
                    }
                    if ($cid && ($it['status'] === 'new' || isset($it['changes']['class']))) {
                        Students::enroll($sid, (int) $cid);
                    }
                }
            } catch (Throwable $e) {
                log_message('error', 'Sinkron Dapodik ' . $kind . ': ' . $e->getMessage());
                $out['skipped'][] = ($d['name'] ?? '?') . ': gagal disimpan (data bentrok)';
            }
        }

        return $out;
    }

    /** Email guru baru: namadepan.namabelakang@domain-yang-paling-banyak-dipakai-guru. */
    private static function teacherEmail(string $name): string
    {
        $db     = db_connect();
        $emails = array_column($db->table('users')->select('email')->where('role', 'teacher')->get()->getResultArray(), 'email');
        $doms   = array_count_values(array_map(static fn ($e) => substr(strrchr($e, '@') ?: '@sekolah.sch.id', 1), $emails));
        arsort($doms);
        $domain = (string) (array_key_first($doms) ?: 'sekolah.sch.id');
        $words  = array_values(array_filter(explode(' ', preg_replace('/[^a-z ]/', '', mb_strtolower(preg_replace('/,.*$/', '', $name))))));
        $local  = $words ? $words[0] . (count($words) > 1 ? '.' . end($words) : '') : 'guru';
        $email  = $local . '@' . $domain;
        for ($i = 2; $db->table('users')->where('email', $email)->countAllResults(); $i++) {
            $email = $local . $i . '@' . $domain;
        }

        return $email;
    }
}
