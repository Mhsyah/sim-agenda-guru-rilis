<?php

namespace App\Controllers;

use App\Models\SchoolProfileModel;
use App\Models\UserModel;
use Config\App;
use Config\Database;
use Throwable;

/**
 * Wizard instalasi untuk hosting tanpa akses SSH/terminal:
 * 1) cek persyaratan + koneksi database → tulis .env
 * 2) jalankan migration + buat akun guru → kunci instalasi (writable/installed.lock)
 */
class Install extends BaseController
{
    private function locked(): bool
    {
        return is_file(WRITEPATH . 'installed.lock');
    }

    private function guard()
    {
        return $this->locked() ? redirect()->to(site_url('login'))->with('error', 'Aplikasi sudah terpasang. Wizard instalasi dinonaktifkan.') : null;
    }

    public static function requirements(): array
    {
        $writable = [WRITEPATH, WRITEPATH . 'cache', WRITEPATH . 'session', WRITEPATH . 'logs', WRITEPATH . 'uploads'];
        $checks   = [
            ['PHP versi 8.2 atau lebih baru (terpasang: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>='), true],
            ['Ekstensi intl', extension_loaded('intl'), true],
            ['Ekstensi mbstring', extension_loaded('mbstring'), true],
            ['Ekstensi json', extension_loaded('json'), true],
            ['Driver MySQLi (MariaDB/MySQL)', extension_loaded('mysqli'), false],
            ['Driver pgsql (PostgreSQL)', extension_loaded('pgsql'), false],
        ];
        foreach ($writable as $dir) {
            $checks[] = ['Folder dapat ditulis: ' . str_replace(ROOTPATH, '', $dir), is_dir($dir) && is_writable($dir), true];
        }
        $checks[] = ['File .env dapat ditulis (bila tidak, salin manual)', is_file(ROOTPATH . '.env') ? is_writable(ROOTPATH . '.env') : is_writable(ROOTPATH), false];
        $checks[] = ['Minimal satu driver database tersedia', extension_loaded('mysqli') || extension_loaded('pgsql'), true];

        return $checks;
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $req = self::requirements();
        $ok  = ! array_filter($req, static fn ($c) => $c[2] && ! $c[1]);

        return view('install/index', [
            'title'   => 'Instalasi Agenda Guru',
            'checks'  => $req,
            'ok'      => $ok,
            'db'      => config(Database::class)->default,
            'baseURL' => App::detectBaseURL(),
            'envText' => session('envText'),
            'errors'  => session('errors') ?? [],
        ]);
    }

    public function database()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $driver = $this->request->getPost('driver') === 'Postgre' ? 'Postgre' : 'MySQLi';
        $cfg    = [
            'DSN'      => '',
            'hostname' => trim((string) $this->request->getPost('hostname')) ?: 'localhost',
            'username' => trim((string) $this->request->getPost('username')),
            'password' => (string) $this->request->getPost('password'),
            'database' => trim((string) $this->request->getPost('database')),
            'DBDriver' => $driver,
            'DBPrefix' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) $this->request->getPost('prefix'))),
            'port'     => (int) ($this->request->getPost('port') ?: ($driver === 'Postgre' ? 5432 : 3306)),
            'charset'  => $driver === 'Postgre' ? 'utf8' : 'utf8mb4',
            'DBCollat' => $driver === 'Postgre' ? '' : 'utf8mb4_unicode_ci',
            'DBDebug'  => true,
        ];
        $baseURL = rtrim(trim((string) $this->request->getPost('base_url')), '/') . '/';
        if ($cfg['username'] === '' || $cfg['database'] === '' || ! filter_var($baseURL, FILTER_VALIDATE_URL)) {
            return redirect()->back()->withInput()->with('error', 'Nama database, username, dan URL aplikasi wajib diisi dengan benar.');
        }
        if (! extension_loaded($driver === 'Postgre' ? 'pgsql' : 'mysqli')) {
            return redirect()->back()->withInput()->with('error', 'Ekstensi PHP untuk driver ' . $driver . ' tidak aktif di server ini.');
        }

        try {
            $db = Database::connect($cfg, false);
            $db->initialize();
            $db->query('SELECT 1');
            $db->close();
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Koneksi database gagal: ' . $e->getMessage());
        }

        $env = $this->buildEnv($cfg, $baseURL);
        $ok  = @file_put_contents(ROOTPATH . '.env', $env, LOCK_EX) !== false;
        if (! $ok) {
            return redirect()->back()->withInput()->with('envText', $env)
                ->with('error', 'Koneksi berhasil, tetapi file .env tidak dapat ditulis. Salin isi di bawah ke file .env di folder utama aplikasi, lalu buka langkah 2.');
        }
        @chmod(ROOTPATH . '.env', 0640);

        return redirect()->to(rtrim($baseURL, '/') . '/install/account')->with('success', 'Koneksi database berhasil dan konfigurasi tersimpan.');
    }

    private function buildEnv(array $c, string $baseURL): string
    {
        $q   = static fn (string $v) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
        $key = 'hex2bin:' . bin2hex(random_bytes(32));
        // Pertahankan kunci enkripsi lama bila ada.
        if (is_file(ROOTPATH . '.env') && preg_match('/^encryption\.key\s*=\s*(\S+)/m', (string) file_get_contents(ROOTPATH . '.env'), $m)) {
            $key = $m[1];
        }
        $https = str_starts_with($baseURL, 'https://') ? 'true' : 'false';

        return implode("\n", [
            '# Dibuat oleh wizard instalasi Agenda Guru pada ' . date('Y-m-d H:i:s'),
            '# JANGAN membagikan atau meng-commit file ini.',
            'CI_ENVIRONMENT = production',
            '',
            'app.baseURL = ' . $q($baseURL),
            'app.forceGlobalSecureRequests = ' . $https,
            'cookie.secure = ' . $https,
            '',
            'database.default.hostname = ' . $q($c['hostname']),
            'database.default.database = ' . $q($c['database']),
            'database.default.username = ' . $q($c['username']),
            'database.default.password = ' . $q($c['password']),
            'database.default.DBDriver = ' . $c['DBDriver'],
            'database.default.DBPrefix = ' . $c['DBPrefix'],
            'database.default.port = ' . $c['port'],
            'database.default.charset = ' . $c['charset'],
            'database.default.DBCollat = ' . $c['DBCollat'],
            '',
            'encryption.key = ' . $key,
            'logger.threshold = 4',
            '',
        ]);
    }

    public function account()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        try {
            db_connect()->initialize();
        } catch (Throwable $e) {
            return redirect()->to(site_url('install'))->with('error', 'Database belum dapat diakses. Lengkapi langkah 1 terlebih dahulu.');
        }

        return view('install/account', ['title' => 'Instalasi Agenda Guru — Akun', 'errors' => session('errors') ?? []]);
    }

    public function finish()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $rules = [
            'school_name' => ['label' => 'Nama sekolah', 'rules' => 'required|max_length[150]'],
            'admin_name'  => ['label' => 'Nama admin teknis', 'rules' => 'required|max_length[100]'],
            'email'       => ['label' => 'Email admin', 'rules' => 'required|valid_email|max_length[150]'],
            'password'    => ['label' => 'Kata sandi admin', 'rules' => 'required|min_length[10]|max_length[200]'],
            'password2'   => ['label' => 'Ulangi kata sandi', 'rules' => 'required|matches[password]'],
        ];
        $withTeacher = trim((string) $this->request->getPost('teacher_email')) !== '';
        if ($withTeacher) {
            $rules += [
                'teacher_name'     => ['label' => 'Nama guru', 'rules' => 'required|max_length[100]'],
                'teacher_email'    => ['label' => 'Email guru', 'rules' => 'required|valid_email|max_length[150]|differs[email]'],
                'teacher_password' => ['label' => 'Kata sandi guru', 'rules' => 'required|min_length[10]|max_length[200]'],
            ];
        }
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('error', 'Periksa kembali isian yang ditandai.');
        }

        try {
            $migrate = service('migrations');
            $migrate->setNamespace('App');
            $migrate->latest();
            $seeder = Database::seeder();
            $seeder->setSilent(true);
            $seeder->call('ReferenceSeeder');

            if ((new UserModel())->countAllResults() > 0) {
                file_put_contents(WRITEPATH . 'installed.lock', date('c'));

                return redirect()->to(site_url('login'))->with('error', 'Database sudah berisi akun. Instalasi dikunci; silakan login.');
            }

            $uid = (new UserModel())->insert([
                'name'          => trim((string) $this->request->getPost('admin_name')),
                'email'         => strtolower(trim((string) $this->request->getPost('email'))),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role'          => 'admin',
                'is_active'     => 1,
                'theme_pref'    => 'system',
            ]);
            if ($withTeacher) {
                (new UserModel())->insert([
                    'name'          => trim((string) $this->request->getPost('teacher_name')),
                    'email'         => strtolower(trim((string) $this->request->getPost('teacher_email'))),
                    'password_hash' => password_hash((string) $this->request->getPost('teacher_password'), PASSWORD_DEFAULT),
                    'role'          => 'teacher',
                    'is_active'     => 1,
                    'theme_pref'    => 'system',
                ]);
            }
            (new SchoolProfileModel())->insert([
                'school_name'  => trim((string) $this->request->getPost('school_name')),
                'teacher_name' => '',
                'timezone'     => 'Asia/Jakarta',
            ]);
            if ($this->request->getPost('demo')) {
                $seeder->call('DemoSeeder');
            }
            file_put_contents(WRITEPATH . 'installed.lock', 'installed ' . date('c') . ' admin ' . $uid);
        } catch (Throwable $e) {
            log_message('error', 'Instalasi gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Instalasi gagal: ' . $e->getMessage());
        }

        return redirect()->to(site_url('login'))->with('success', 'Instalasi selesai. Login sebagai admin teknis untuk melengkapi data sekolah, guru, kelas, dan siswa (bisa lewat Impor Data).');
    }
}
