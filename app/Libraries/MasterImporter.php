<?php

namespace App\Libraries;

use App\Database\Seeds\ReferenceSeeder;
use App\Models\AcademicYearModel;
use App\Models\ClassGroupModel;
use App\Models\SemesterModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use Throwable;

/**
 * Impor data induk dari CSV (guru, kelas/rombel, mata pelajaran, tahun pelajaran).
 * Setiap baris divalidasi; baris yang sudah ada diperbarui (bukan diduplikasi).
 * Hasil: jumlah baru/diperbarui/gagal + alasan per baris.
 */
final class MasterImporter
{
    /** Opsi tambahan per jenis impor (curriculum: id mapel bawaan dari formulir unggah). */
    private ?int $option = null;

    /** Kata sandi awal guru bila kolom password dikosongkan (guru diminta menggantinya setelah login). */
    public const DEFAULT_TEACHER_PASSWORD = 'simguru123';

    public const TYPES = [
        'teachers' => [
            'title'   => 'Data Guru',
            'desc'    => 'Akun guru untuk login. Guru yang emailnya sudah ada akan diperbarui. Bila kata sandi dikosongkan, kata sandi awal = simguru123.',
            'columns' => ['name' => 'Nama lengkap (wajib)', 'email' => 'Email login (wajib, unik)', 'nip' => 'NIP', 'gender' => 'L/P', 'password' => 'Kata sandi awal (opsional; kosong = simguru123)'],
            'sample'  => [['Siti Aminah, S.Pd.', 'siti@sekolah.sch.id', '198001012005012001', 'P', ''], ['Budi Santoso, S.Pd.', 'budi@sekolah.sch.id', '', 'L', '']],
        ],
        'students' => [
            'title'   => 'Data Siswa',
            'desc'    => 'Siswa ditempatkan ke kelas pada tahun pelajaran aktif. NIS/NISN yang sudah ada akan diperbarui.',
            'columns' => ['full_name' => 'Nama lengkap (wajib)', 'nis' => 'NIS', 'nisn' => 'NISN (angka)', 'gender' => 'L/P', 'birth_date' => 'YYYY-MM-DD atau DD/MM/YYYY', 'class' => 'Nama kelas, mis. VII A'],
            'sample'  => [['Contoh Nama Siswa', '2026001', '0123456789', 'L', '2013-05-17', 'VII A']],
        ],
        'classes' => [
            'title'   => 'Kelas/Rombel',
            'desc'    => 'Kelas dibuat pada tahun pelajaran aktif. Kelas dengan nama sama diperbarui.',
            'columns' => ['name' => 'Nama kelas (wajib), mis. VII A', 'grade_level' => 'Tingkat (angka 1–13)'],
            'sample'  => [['VII A', '7'], ['VII B', '7'], ['VIII A', '8']],
        ],
        'subjects' => [
            'title'   => 'Mata Pelajaran',
            'desc'    => 'Mapel dengan kode atau nama yang sama diperbarui.',
            'columns' => ['name' => 'Nama mapel (wajib)', 'code' => 'Kode singkat, mis. MTK', 'phase' => 'Fase A–F'],
            'sample'  => [['Matematika', 'MTK', 'D'], ['Bahasa Indonesia', 'BIN', 'D'], ['Ilmu Pengetahuan Alam', 'IPA', 'D']],
        ],
        'years' => [
            'title'   => 'Tahun Pelajaran',
            'desc'    => 'Semester Ganjil & Genap dibuat otomatis. Kolom aktif = 1 menjadikannya tahun pelajaran aktif.',
            'columns' => ['name' => 'Nama, format 2026/2027 (wajib)', 'start_date' => 'Tanggal mulai (wajib)', 'end_date' => 'Tanggal selesai (wajib)', 'is_active' => '1 = aktif'],
            'sample'  => [['2026/2027', '2026-07-13', '2027-06-30', '1']],
        ],
        'curriculum' => [
            'title'    => 'CP, TP, ATP & KKTP',
            'desc'     => '1 baris = 1 TP beserta CP-nya. TP/CP dengan kode (atau rumusan) sama pada mapel yang sama diperbarui, bukan diduplikasi.',
            'required' => 'tp',
            'columns'  => [
                'mapel' => 'Kode atau nama mapel (wajib, kecuali mapel dipilih saat unggah)', 'semester' => 'Ganjil / Genap / 1 / 2 (kosong = keduanya)', 'fase' => 'Fase A–F', 'elemen' => 'Elemen CP',
                'kode_cp' => 'Kode CP', 'cp' => 'Rumusan capaian pembelajaran', 'kode_tp' => 'Kode TP, mis. TP-7.1', 'tp' => 'Rumusan tujuan pembelajaran (wajib)',
                'urutan_atp' => 'Urutan ATP (1, 2, 3, …)', 'kelas' => 'Tingkat kelas, mis. 7 atau VII', 'alokasi_jp' => 'Alokasi JP (1 JP = 40 menit)',
                'kktp_kriteria' => 'KKTP: deskripsi kriteria ketercapaian', 'kktp_interval' => 'KKTP: interval, mis. 0-40;41-65;66-85;86-100 (kosong = bawaan)',
            ],
            'sample'   => [
                ['MTK', 'Ganjil', 'D', 'Bilangan', 'CP-MTK-D-01', 'Peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, rasional, dan desimal.', 'TP-7.1', 'Menjelaskan konsep bilangan bulat dan letaknya pada garis bilangan.', '1', '7', '4', 'Siswa mampu menempatkan bilangan bulat pada garis bilangan dengan benar.', '0-40;41-65;66-85;86-100'],
                ['MTK', 'Ganjil', 'D', 'Bilangan', 'CP-MTK-D-01', '', 'TP-7.2', 'Melakukan operasi hitung bilangan bulat.', '2', '7', '6', 'Siswa mampu menyelesaikan operasi hitung campuran bilangan bulat.', ''],
            ],
        ],
    ];

    private const ALIASES = [
        'nama' => 'name', 'nama_lengkap' => 'name', 'nama_guru' => 'name', 'nama_kelas' => 'name', 'nama_mapel' => 'name', 'tahun_pelajaran' => 'name', 'tahun' => 'name',
        'kode' => 'code', 'fase' => 'phase', 'tingkat' => 'grade_level', 'kelas_tingkat' => 'grade_level',
        'mulai' => 'start_date', 'tanggal_mulai' => 'start_date', 'selesai' => 'end_date', 'tanggal_selesai' => 'end_date', 'aktif' => 'is_active',
        'mata_pelajaran' => 'mapel', 'kode_mapel' => 'mapel', 'tujuan_pembelajaran' => 'tp', 'capaian_pembelajaran' => 'cp', 'atp' => 'urutan_atp',
        'kktp' => 'kktp_kriteria', 'interval_kktp' => 'kktp_interval', 'jp' => 'alokasi_jp', 'tingkat_kelas' => 'kelas',
        'kata_sandi' => 'password', 'sandi' => 'password', 'e-mail' => 'email', 'jk' => 'gender', 'l/p' => 'gender', 'jenis_kelamin' => 'gender',
    ];

    /** @return array{header: list<?string>, rows: list<array{line: int, data: array<string, string>}>}|string */
    public static function parse(string $content, array $known, bool $rawNames = false)
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines   = array_values(array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", $content)), static fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return 'File kosong atau hanya berisi header.';
        }
        $delim  = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header  = [];
        $ignored = [];
        foreach (str_getcsv($lines[0], $delim, '"', '') as $h) {
            $k        = strtolower(trim(str_replace(' ', '_', $h)));
            $k        = $rawNames && in_array($k, $known, true) ? $k : (self::ALIASES[$k] ?? $k);
            $header[] = in_array($k, $known, true) ? $k : null;
            if (! in_array($k, $known, true) && trim($h) !== '') {
                $ignored[] = trim($h);
            }
        }
        $rows = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            $cells = str_getcsv($line, $delim, '"', '');
            $data  = [];
            foreach ($header as $idx => $k) {
                if ($k) {
                    $data[$k] = trim((string) ($cells[$idx] ?? ''));
                }
            }
            $rows[] = ['line' => $i + 2, 'data' => $data];
        }

        return ['header' => $header, 'rows' => $rows, 'ignored' => $ignored];
    }

    public static function normDate(string $d): ?string
    {
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $d, $m)) {
            [$y, $mo, $da] = [$m[1], $m[2], $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $d, $m)) {
            [$y, $mo, $da] = [$m[3], $m[2], $m[1]];
        } else {
            return null;
        }

        return checkdate((int) $mo, (int) $da, (int) $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $da) : null;
    }

    /** @return array{ok: int, created: int, updated: int, total: int, errors: list<array{line: int, reason: string}>, credentials: list<array{0: string, 1: string}>} */
    public function run(string $type, string $content, ?int $defaultClassId = null): array
    {
        $res = ['ok' => 0, 'created' => 0, 'updated' => 0, 'restored' => 0, 'total' => 0, 'errors' => [], 'credentials' => [], 'rows' => [], 'ignored' => [], 'duplicates' => 0];
        $this->option = $defaultClassId; // curriculum: mapel bawaan
        if ($type === 'students') {
            $r = (new StudentImporter())->import($content, (int) Period::yearId(), $defaultClassId);

            return $r + ['credentials' => []];
        }
        $parsed = self::parse($content, array_keys(self::TYPES[$type]['columns']), $type === 'curriculum');
        if (is_string($parsed)) {
            $res['errors'][] = ['line' => 1, 'reason' => $parsed];

            return $res;
        }
        $req = self::TYPES[$type]['required'] ?? 'name';
        if (! in_array($req, $parsed['header'], true)) {
            $res['errors'][] = ['line' => 1, 'reason' => 'Header wajib memuat kolom "' . $req . '"' . ($req === 'name' ? ' (atau "nama")' : '') . '. Gunakan template.'];

            return $res;
        }
        $res['ignored'] = $parsed['ignored'];
        $seen = [];
        foreach ($parsed['rows'] as $row) {
            $res['total']++;
            try {
                $out = $this->{'row' . ucfirst($type)}($row['data'] + ['__line' => $row['line']], $seen);
            } catch (Throwable $e) {
                log_message('error', 'Impor ' . $type . ' baris ' . $row['line'] . ': ' . $e->getMessage());
                $out = 'Gagal disimpan karena bentrok dengan data lain.';
            }
            $shown = array_diff_key($row['data'], ['password' => 1]); // kata sandi tidak ditampilkan di pratinjau
            if (is_string($out)) {
                // "DUP:" = baris kembar di dalam file (dilewati, ditandai terpisah dari galat data)
                $dup = str_starts_with($out, 'DUP:');
                $out = $dup ? ltrim(substr($out, 4)) : $out;
                $res['duplicates'] += (int) $dup;
                $res['errors'][] = ['line' => $row['line'], 'reason' => $out];
                $res['rows'][]   = ['line' => $row['line'], 'data' => $shown, 'status' => $dup ? 'duplicate' : 'error', 'reason' => $out];

                continue;
            }
            $res['ok']++;
            $res[$out['action']]++;
            $res['rows'][] = ['line' => $row['line'], 'data' => $shown, 'status' => $out['action'], 'reason' => $out['note'] ?? null];
            if (! empty($out['credential'])) {
                $res['credentials'][] = $out['credential'];
            }
        }

        return $res;
    }

    private function rowTeachers(array $d, array &$seen)
    {
        $name  = preg_replace('/\s+/', ' ', $d['name'] ?? '');
        $email = strtolower($d['email'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            return 'Nama kosong atau melebihi 100 karakter.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email tidak valid.';
        }
        if (isset($seen[$email])) {
            return 'DUP: Email ' . $email . ' muncul lebih dari sekali di file.';
        }
        $seen[$email] = true;
        $pw = $d['password'] ?? '';
        if ($pw !== '' && mb_strlen($pw) < 8) {
            return 'Kata sandi minimal 8 karakter (atau kosongkan agar memakai sandi awal simguru123).';
        }
        $g = strtoupper(substr(trim($d['gender'] ?? ''), 0, 1));
        if ($g !== '' && ! in_array($g, ['L', 'P'], true)) {
            return 'Jenis kelamin harus L atau P.';
        }
        $data = ['name' => $name, 'nip' => ($d['nip'] ?? '') ?: null] + ($g !== '' ? ['gender' => $g] : []);
        $cur  = (new UserModel())->where('email', $email)->first();
        if ($cur) {
            if ($cur['role'] !== 'teacher') {
                return 'Email sudah dipakai akun admin teknis.';
            }
            if ($pw !== '') {
                $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            }
            (new UserModel())->update($cur['id'], $data);

            return ['action' => 'updated', 'note' => 'Email sudah terdaftar — data guru diperbarui' . ($pw !== '' ? ' termasuk kata sandi.' : ', kata sandi tidak diubah.')];
        }
        $gen = $pw === '';
        if ($gen) {
            $pw = self::DEFAULT_TEACHER_PASSWORD;
        }
        (new UserModel())->insert($data + ['email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'teacher', 'is_active' => 1, 'theme_pref' => 'system']);

        return ['action' => 'created', 'credential' => $gen ? [$email, $pw] : null, 'note' => $gen ? 'Kata sandi awal: ' . self::DEFAULT_TEACHER_PASSWORD : null];
    }

    private function rowClasses(array $d, array &$seen)
    {
        $name = preg_replace('/\s+/', ' ', $d['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 50) {
            return 'Nama kelas kosong atau melebihi 50 karakter.';
        }
        $lvl = $d['grade_level'] ?? '';
        if ($lvl !== '' && (! ctype_digit($lvl) || (int) $lvl < 1 || (int) $lvl > 13)) {
            return 'Tingkat harus angka 1–13.';
        }
        $key = mb_strtolower($name);
        if (isset($seen[$key])) {
            return 'DUP: Kelas ' . $name . ' muncul lebih dari sekali di file.';
        }
        $seen[$key] = true;
        $yid = Period::yearId();
        if (! $yid) {
            return 'Belum ada tahun pelajaran aktif.';
        }
        $cur = (new ClassGroupModel())->withDeleted()->where('academic_year_id', $yid)->where('name', $name)->first();
        $data = ['name' => $name, 'grade_level' => $lvl === '' ? null : (int) $lvl, 'is_active' => 1];
        if ($cur) {
            (new ClassGroupModel())->update($cur['id'], $data);
            db_connect()->table('class_groups')->where('id', $cur['id'])->update(['deleted_at' => null]);

            return ['action' => 'updated', 'note' => 'Kelas dengan nama sama sudah ada — diperbarui.'];
        }
        (new ClassGroupModel())->insert($data + ['academic_year_id' => $yid]);

        return ['action' => 'created'];
    }

    private function rowSubjects(array $d, array &$seen)
    {
        $name  = preg_replace('/\s+/', ' ', $d['name'] ?? '');
        $code  = strtoupper($d['code'] ?? '');
        $phase = strtoupper($d['phase'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            return 'Nama mapel kosong atau melebihi 100 karakter.';
        }
        if ($code !== '' && ! preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
            return 'Kode hanya huruf/angka/-/_ (maks. 20).';
        }
        if ($phase !== '' && ! in_array($phase, ['A', 'B', 'C', 'D', 'E', 'F'], true)) {
            return 'Fase harus A–F.';
        }
        $key = $code !== '' ? 'c:' . $code : 'n:' . mb_strtolower($name);
        if (isset($seen[$key])) {
            return 'DUP: Mapel muncul lebih dari sekali di file.';
        }
        $seen[$key] = true;
        $cur = $code !== '' ? (new SubjectModel())->withDeleted()->where('code', $code)->first() : null;
        $cur ??= (new SubjectModel())->withDeleted()->where('name', $name)->first();
        $data = ['name' => $name, 'code' => $code ?: ($cur['code'] ?? null), 'phase' => $phase ?: null, 'is_active' => 1];
        if ($cur) {
            (new SubjectModel())->update($cur['id'], $data);
            db_connect()->table('subjects')->where('id', $cur['id'])->update(['deleted_at' => null]);

            return ['action' => 'updated', 'note' => 'Mapel dengan kode/nama sama sudah ada — diperbarui.'];
        }
        (new SubjectModel())->insert($data);

        return ['action' => 'created'];
    }

    private function rowYears(array $d, array &$seen)
    {
        $name = trim($d['name'] ?? '');
        if (! preg_match('/^(\d{4})\/(\d{4})$/', $name, $m) || (int) $m[2] !== (int) $m[1] + 1) {
            return 'Nama harus berformat 2026/2027.';
        }
        $start = self::normDate($d['start_date'] ?? '');
        $end   = self::normDate($d['end_date'] ?? '');
        if (! $start || ! $end || $start >= $end) {
            return 'Tanggal mulai/selesai tidak valid atau mulai tidak sebelum selesai.';
        }
        if (isset($seen[$name])) {
            return 'DUP: Tahun pelajaran muncul lebih dari sekali di file.';
        }
        $seen[$name] = true;
        $active = in_array(strtolower($d['is_active'] ?? ''), ['1', 'ya', 'y', 'true', 'aktif'], true) ? 1 : 0;
        $cur    = (new AcademicYearModel())->where('name', $name)->first();
        if ($cur) {
            (new AcademicYearModel())->update($cur['id'], ['start_date' => $start, 'end_date' => $end] + ($active ? ['is_active' => 1] : []));
            $id = (int) $cur['id'];
            $action = 'updated';
        } else {
            $id = (int) (new AcademicYearModel())->insert(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'is_active' => $active]);
            $y  = (int) $m[1];
            if ($y . '-12-31' < $end) {
                (new SemesterModel())->insert(['academic_year_id' => $id, 'name' => 'Ganjil', 'start_date' => $start, 'end_date' => $y . '-12-31', 'is_active' => 1]);
                (new SemesterModel())->insert(['academic_year_id' => $id, 'name' => 'Genap', 'start_date' => ($y + 1) . '-01-01', 'end_date' => $end, 'is_active' => 0]);
            }
            $action = 'created';
        }
        if ($active) {
            (new AcademicYearModel())->where('id !=', $id)->set('is_active', 0)->update();
            Period::set($id, null);
        }

        return ['action' => $action];
    }

    private function rowCurriculum(array $d, array &$seen)
    {
        $db  = db_connect();
        $yid = (int) Period::yearId();
        if (! $yid) {
            return 'Belum ada tahun pelajaran aktif.';
        }
        // Mapel: kolom mapel (kode/nama) atau mapel yang dipilih saat unggah; guru hanya mapel yang diajarkan
        $allowed = Auth::isTeacher() ? Lookup::teachingSubjects() : Lookup::subjects(false);
        $m       = trim($d['mapel'] ?? '');
        $sid     = null;
        if ($m !== '') {
            $sub = $db->table('subjects')->where('deleted_at', null)->groupStart()->where('code', strtoupper($m))->orWhere('LOWER(name)', mb_strtolower($m))->groupEnd()->get()->getRowArray();
            if (! $sub) {
                return 'Mapel "' . $m . '" tidak ditemukan. Pakai kode/nama mapel yang terdaftar.';
            }
            $sid = (int) $sub['id'];
        } elseif ($this->option) {
            $sid = $this->option;
        }
        if (! $sid) {
            return 'Kolom mapel kosong. Isi kode/nama mapel atau pilih mapel saat unggah.';
        }
        if (! isset($allowed[$sid])) {
            return 'Mapel ini bukan mapel yang Anda ajarkan.';
        }
        $tp = preg_replace('/\s+/', ' ', trim($d['tp'] ?? ''));
        if ($tp === '' || mb_strlen($tp) > 3000) {
            return 'Rumusan TP kosong atau melebihi 3000 karakter.';
        }
        $codeTp = trim($d['kode_tp'] ?? '');
        $codeCp = trim($d['kode_cp'] ?? '');
        $cpText = preg_replace('/\s+/', ' ', trim($d['cp'] ?? ''));
        $phase  = strtoupper(trim($d['fase'] ?? ''));
        if ($phase !== '' && ! in_array($phase, ['A', 'B', 'C', 'D', 'E', 'F'], true)) {
            return 'Fase harus A–F.';
        }
        foreach (['kode_tp' => $codeTp, 'kode_cp' => $codeCp] as $k => $v) {
            if (mb_strlen($v) > 30) {
                return strtoupper($k) . ' melebihi 30 karakter.';
            }
        }
        $grade = trim($d['kelas'] ?? '');
        if ($grade !== '') {
            $g = preg_replace('/^kelas\s*/i', '', $grade);
            $grade = ctype_digit($g) ? (int) $g : roman_to_int(strtoupper($g));
            if ($grade < 1 || $grade > 13) {
                return 'Kelas/tingkat tidak dikenali (contoh: 7 atau VII).';
            }
        } else {
            $grade = null;
        }
        $atp = trim($d['urutan_atp'] ?? '');
        if ($atp !== '' && (! ctype_digit($atp) || (int) $atp < 1 || (int) $atp > 999)) {
            return 'Urutan ATP harus angka 1–999.';
        }
        $jp = trim(str_replace(',', '.', $d['alokasi_jp'] ?? ''));
        if ($jp !== '' && (! is_numeric($jp) || (float) $jp < 0 || (float) $jp > 500)) {
            return 'Alokasi JP harus angka 0–500.';
        }
        // Semester: Ganjil/Genap/1/2 pada T.P. aktif; kosong = berlaku di kedua semester
        $semRaw = mb_strtolower(trim($d['semester'] ?? ''));
        $semId  = null;
        if ($semRaw !== '' && ! in_array($semRaw, ['keduanya', 'semua', '-', '1-2', '1&2'], true)) {
            $semName = ['1' => 'ganjil', 'i' => 'ganjil', 'gasal' => 'ganjil', '2' => 'genap', 'ii' => 'genap'][$semRaw] ?? $semRaw;
            foreach (Lookup::semesters() as $sid2 => $sn) {
                if (mb_strtolower($sn) === $semName) {
                    $semId = (int) $sid2;
                }
            }
            if (! $semId) {
                return 'Semester "' . ($d['semester'] ?? '') . '" tidak dikenali (isi Ganjil, Genap, 1, 2, atau kosongkan).';
            }
        }
        $intRaw = trim($d['kktp_interval'] ?? '');
        $int    = Kktp::normalize($intRaw);
        if ($intRaw !== '' && $int === null) {
            return 'Interval KKTP tidak valid (contoh: 0-40;41-65;66-85;86-100).';
        }
        $key = $sid . '|' . ($codeTp !== '' ? 'k:' . mb_strtolower($codeTp) : 'd:' . mb_strtolower($tp));
        if (isset($seen[$key])) {
            return 'DUP: TP ' . ($codeTp !== '' ? $codeTp : '"' . mb_strimwidth($tp, 0, 40, '…') . '"') . ' sama dengan baris ' . $seen[$key] . ' di file ini.';
        }
        $seen[$key] = $d['__line'] ?? '?';

        $notes = [];
        // CP: dicari dari kode (atau rumusan); dibuat bila belum ada
        $cpId = null;
        if ($codeCp !== '' || $cpText !== '') {
            $q = $db->table('learning_outcomes')->where('academic_year_id', $yid)->where('subject_id', $sid)->where('deleted_at', null);
            $cp = $codeCp !== '' ? (clone $q)->where('code', $codeCp)->get()->getRowArray() : (clone $q)->where('description', $cpText)->get()->getRowArray();
            if ($cp) {
                $cpId = (int) $cp['id'];
                $upd  = array_filter(['description' => $cpText, 'element_name' => trim($d['elemen'] ?? ''), 'phase' => $phase], static fn ($v) => $v !== '') + ($semRaw !== '' ? ['semester_id' => $semId] : []);
                if ($upd) {
                    $db->table('learning_outcomes')->where('id', $cpId)->update($upd + ['updated_at' => date('Y-m-d H:i:s')]);
                }
            } elseif ($cpText === '') {
                return 'CP dengan kode ' . $codeCp . ' belum ada; isi kolom cp untuk membuatnya.';
            } else {
                $el = trim($d['elemen'] ?? '');
                $db->table('learning_outcomes')->insert(['academic_year_id' => $yid, 'subject_id' => $sid, 'phase' => $phase ?: null, 'element_name' => mb_substr($el !== '' ? $el : 'Umum', 0, 150),
                    'code' => $codeCp ?: null, 'description' => $cpText, 'semester_id' => $semId, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $cpId    = (int) $db->insertID();
                $notes[] = 'CP baru dibuat';
            }
        }
        // TP
        $q   = $db->table('learning_objectives')->where('academic_year_id', $yid)->where('subject_id', $sid)->where('deleted_at', null);
        $cur = $codeTp !== '' ? (clone $q)->where('code', $codeTp)->get()->getRowArray() : (clone $q)->where('description', $tp)->get()->getRowArray();
        $data = ['code' => $codeTp ?: ($cur['code'] ?? null), 'description' => $tp, 'grade_level' => $grade ?? ($cur['grade_level'] ?? null),
            'atp_order' => $atp !== '' ? (int) $atp : ($cur['atp_order'] ?? null), 'estimated_minutes' => $jp !== '' ? (int) round((float) $jp * 40) : ($cur['estimated_minutes'] ?? null),
            'kktp_criteria' => trim($d['kktp_kriteria'] ?? '') ?: ($cur['kktp_criteria'] ?? null), 'kktp_intervals' => $int ?? ($cur['kktp_intervals'] ?? null),
            'semester_id' => $semRaw !== '' ? $semId : ($cur['semester_id'] ?? null),
            'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($cur) {
            $tpId = (int) $cur['id'];
            $db->table('learning_objectives')->where('id', $tpId)->update($data);
            $action = 'updated';
            array_unshift($notes, 'TP sudah ada' . ($codeTp !== '' ? ' (kode ' . $codeTp . ')' : '') . ' — diperbarui');
        } else {
            $db->table('learning_objectives')->insert($data + ['academic_year_id' => $yid, 'subject_id' => $sid, 'created_at' => date('Y-m-d H:i:s')]);
            $tpId   = (int) $db->insertID();
            $action = 'created';
        }
        if ($cpId && ! $db->table('outcome_objectives')->where('learning_outcome_id', $cpId)->where('learning_objective_id', $tpId)->countAllResults()) {
            $db->table('outcome_objectives')->insert(['learning_outcome_id' => $cpId, 'learning_objective_id' => $tpId]);
        }

        return ['action' => $action, 'note' => $notes ? implode('; ', $notes) . '.' : null];
    }
}
