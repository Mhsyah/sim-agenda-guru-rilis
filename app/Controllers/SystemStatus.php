<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\BackupService;
use App\Models\AuditLogModel;
use App\Models\UserModel;
use Throwable;

/**
 * Status sistem untuk admin teknis (dan guru): kesehatan server & database,
 * backup, keamanan login, log galat, serta ukuran data — tanpa menampilkan isi data akademik.
 */
class SystemStatus extends BaseController
{
    public function index()
    {
        return $this->render('settings/system', ['title' => 'Status Sistem'] + self::collect());
    }

    public static function collect(): array
    {
        $db = db_connect();

        // ----- Server & database
        try {
            $dbVersion = $db->getVersion();
        } catch (Throwable $e) {
            $dbVersion = '—';
        }
        $driver = $db->DBDriver === 'Postgre' ? 'PostgreSQL' : (stripos((string) $dbVersion, 'maria') !== false ? 'MariaDB' : 'MySQL/MariaDB');
        $req    = service('request');
        $server = [
            ['Versi aplikasi', 'Agenda Guru ' . app_version(), true],
            ['Mode', ENVIRONMENT, ENVIRONMENT === 'production'],
            ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['CodeIgniter', \CodeIgniter\CodeIgniter::CI_VERSION, true],
            ['Database', $driver . ' ' . $dbVersion, true],
            ['Awalan tabel', $db->DBPrefix !== '' ? $db->DBPrefix : '(tanpa awalan)', true],
            ['URL aplikasi', base_url(), true],
            ['HTTPS', $req->isSecure() ? 'Aktif' : 'Tidak aktif', $req->isSecure()],
            ['Zona waktu server', date_default_timezone_get(), true],
            ['OPcache (kecepatan PHP)', function_exists('opcache_get_status') && @opcache_get_status(false) ? 'Aktif' : 'Tidak aktif — aktifkan agar halaman 3–10× lebih cepat', function_exists('opcache_get_status') && (bool) @opcache_get_status(false)],
        ];

        // ----- Penyimpanan
        $dirs = [];
        foreach (['cache', 'logs', 'session', 'uploads', 'backups'] as $d) {
            $p      = WRITEPATH . $d;
            $dirs[] = ['writable/' . $d, is_dir($p) && is_writable($p), self::dirSize($p)];
        }
        $free = @disk_free_space(WRITEPATH);

        // ----- Backup
        $backups = BackupService::list();
        $last    = $backups[0]['time'] ?? null;
        $age     = $last ? (int) floor((time() - $last) / 86400) : null;

        // ----- Keamanan login (7 hari)
        $since   = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $count   = static fn (string $a) => (new AuditLogModel())->where('action', $a)->where('created_at >=', $since)->countAllResults();
        $logins  = (new AuditLogModel())->select('audit_logs.created_at, audit_logs.ip_address, audit_logs.action, u.name')
            ->join('users u', 'u.id = audit_logs.user_id', 'left')
            ->whereIn('audit_logs.action', ['login', 'login_failed', 'login_blocked'])
            ->orderBy('audit_logs.id', 'DESC')->findAll(8);
        $users = (new UserModel())->select('name, email, role, is_active, last_login_at')->orderBy('role', 'DESC')->findAll();

        // ----- Ukuran data (jumlah baris saja)
        $counts = [];
        foreach (BackupService::TABLES as $t) {
            try {
                $counts[$t] = $db->table($t)->countAllResults();
            } catch (Throwable $e) {
                $counts[$t] = null;
            }
        }

        return [
            'server'  => $server,
            'dirs'    => $dirs,
            'free'    => $free,
            'backups' => array_slice($backups, 0, 5),
            'backupCount' => count($backups),
            'lastBackupAge' => $age,
            'failed7' => $count('login_failed'),
            'blocked7' => $count('login_blocked'),
            'logins'  => $logins,
            'users'   => $users,
            'counts'  => $counts,
            'errors'  => ($es = self::errorSummary())['items'],
            'errorsRecovered' => $es['recovered'],
            'installedAt' => is_file(WRITEPATH . 'installed.lock') ? filemtime(WRITEPATH . 'installed.lock') : null,
        ];
    }

    private static function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $size += $f->getSize();
        }

        return $size;
    }

    /** Berkas penanda "galat sudah ditangani": galat sebelum waktu ini tidak ditampilkan lagi. */
    public const ERR_ACK_FILE = WRITEPATH . 'errors-ack.txt';

    /**
     * Galat ERROR/CRITICAL terbaru (7 hari terakhir, setelah tanda "sudah ditangani"), dikelompokkan per pesan.
     * Galat koneksi database dianggap sudah pulih bila halaman ini bisa dibuka (database sedang tersambung).
     *
     * @return array{items: list<array{level:string,time:string,message:string,count:int}>, recovered: int}
     */
    public static function errorSummary(): array
    {
        $ack   = is_file(self::ERR_ACK_FILE) ? (int) trim((string) @file_get_contents(self::ERR_ACK_FILE)) : 0;
        $since = max($ack, strtotime('-7 days'));
        $files = glob(WRITEPATH . 'logs/log-*.log') ?: [];
        rsort($files);
        $groups    = [];
        $recovered = 0;
        foreach (array_slice($files, 0, 7) as $f) {
            if (filemtime($f) < $since) {
                continue;
            }
            $fh = @fopen($f, 'rb');
            if (! $fh) {
                continue;
            }
            fseek($fh, max(0, filesize($f) - 200000));
            $chunk = (string) fread($fh, 200000);
            fclose($fh);
            if (! preg_match_all('/^(ERROR|CRITICAL|ALERT|EMERGENCY) - (\S+ \S+) --> (.{0,240})/m', $chunk, $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $row) {
                if (strtotime($row[2]) < $since) {
                    continue;
                }
                if (preg_match('/connect(ing)? to the database|actively refused|Connection refused|No such host/i', $row[3])) {
                    $recovered++;

                    continue;
                }
                $key = preg_replace('/\d+/', '#', $row[3]);
                if (! isset($groups[$key])) {
                    $groups[$key] = ['level' => $row[1], 'time' => $row[2], 'message' => $row[3], 'count' => 0];
                }
                $groups[$key]['count']++;
                if ($row[2] > $groups[$key]['time']) {
                    $groups[$key]['time'] = $row[2];
                }
            }
        }
        $items = array_values($groups);
        usort($items, static fn ($x, $y) => strcmp($y['time'], $x['time']));

        return ['items' => array_slice($items, 0, 8), 'recovered' => $recovered];
    }

    /**
     * Migrasi database yang belum dijalankan (berkas ada, belum tercatat di tabel migrations).
     *
     * @return list<string> nama migrasi, mis. "2026-11-20-000016 CurriculumSemester"
     */
    public static function pendingMigrations(): array
    {
        try {
            $runner = service('migrations', null, null, false);
            $runner->setNamespace('App');
            $done = [];
            foreach (db_connect()->table('migrations')->select('version, class')->get()->getResultArray() as $r) {
                $done[$r['version'] . '|' . ltrim($r['class'], '\\')] = true;
            }
            $out = [];
            foreach ($runner->findMigrations() as $m) {
                if (! isset($done[$m->version . '|' . ltrim($m->class, '\\')])) {
                    $out[] = $m->version . ' ' . substr(strrchr('\\' . $m->class, '\\'), 1);
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Jalankan migrasi tertunda dari halaman (untuk hosting tanpa Terminal/SSH); database dibackup dulu. */
    public function runMigrations()
    {
        $pending = self::pendingMigrations();
        if (! $pending) {
            return redirect()->to(site_url('system-status'))->with('info', 'Struktur database sudah terbaru; tidak ada migrasi yang perlu dijalankan.');
        }
        try {
            $backup = (new \App\Libraries\BackupService())->store('sebelum-migrasi');
        } catch (\Throwable $e) {
            log_message('error', 'Backup sebelum migrasi gagal: ' . $e->getMessage());

            return redirect()->to(site_url('system-status'))->with('error', 'Backup database gagal; migrasi dibatalkan. (' . mb_substr($e->getMessage(), 0, 160) . ')');
        }
        try {
            service('migrations', null, null, false)->setNamespace(null)->latest();
        } catch (\Throwable $e) {
            log_message('error', 'Migrasi dari Status Sistem gagal: ' . $e->getMessage());
            Audit::log('migrate', 'system', null, null, ['berhasil' => false, 'backup' => $backup]);

            return redirect()->to(site_url('system-status'))->with('error', 'Migrasi gagal: ' . mb_substr($e->getMessage(), 0, 160) . '. Data dapat dipulihkan dari backup ' . $backup . '.');
        }
        cache()->clean();
        Audit::log('migrate', 'system', null, null, ['berhasil' => true, 'migrasi' => $pending, 'backup' => $backup]);

        return redirect()->to(site_url('system-status'))->with('success', count($pending) . ' pembaruan struktur database dijalankan (backup: ' . $backup . ').');
    }

    /** Pasang pembaruan dari GitHub (backup, unduh, pasang, migrasi; dipulihkan bila gagal). */
    public function installUpdate()
    {
        $res = \App\Libraries\UpdateInstaller::install();
        Audit::log('app_update', 'system', null, null, ['berhasil' => $res['ok'], 'pesan' => $res['message']]);

        return redirect()->to(site_url('system-status#ss-ver'))->with($res['ok'] ? 'success' : 'error', $res['message'])
            ->with('update_steps', $res['steps']);
    }

    /** Cek pembaruan sekarang (tidak memasang apa pun). */
    public function updateCheck()
    {
        $s = \App\Libraries\AppUpdate::check();
        if ($s['error']) {
            return redirect()->to(site_url('system-status#ss-ver'))->with('warning', $s['error']);
        }
        $new = \App\Libraries\AppUpdate::available($s);
        $n   = \App\Libraries\AppUpdate::pendingCommits($s);
        // Ada pembaruan & bisa dipasang dari aplikasi → langsung dipasang otomatis
        if (($new || $n) && config('AppUpdate')->autoInstall && ($blk = \App\Libraries\UpdateInstaller::blocker()) === null) {
            $res = \App\Libraries\UpdateInstaller::install();
            Audit::log('app_update', 'system', null, null, ['berhasil' => $res['ok'], 'pesan' => $res['message'], 'otomatis' => true]);

            return redirect()->to(site_url('system-status#ss-ver'))->with($res['ok'] ? 'success' : 'error', $res['message'])
                ->with('update_steps', $res['steps']);
        }
        $msg = $new ? 'Versi ' . $new['version'] . ' tersedia di GitHub.'
            : ($n ? $n . ' pembaruan kode di GitHub belum terpasang (versi tetap v' . app_version() . ').' : 'Aplikasi sudah versi & kode terbaru (v' . app_version() . ').');
        if (($new || $n) && ! empty($blk)) {
            $msg .= ' Belum bisa dipasang otomatis: ' . $blk;
        }

        return redirect()->to(site_url('system-status#ss-ver'))->with($new || $n ? 'info' : 'success', $msg);
    }

    /** Terima kondisi file saat ini sebagai versi resmi (setelah admin sengaja memasang perubahan). */
    public function acceptIntegrity()
    {
        $ok = \App\Libraries\Integrity::accept();
        Audit::log('integrity_accept', 'system');

        return redirect()->to(site_url('system-status#ss-int'))->with($ok ? 'success' : 'error',
            $ok ? 'Kondisi file saat ini dicatat sebagai versi resmi.' : 'Manifest tidak dapat diperbarui (periksa izin tulis app/Config).');
    }

    /** Tandai semua galat yang tampil sebagai sudah ditangani (log tetap utuh). */
    public function ackErrors()
    {
        @file_put_contents(self::ERR_ACK_FILE, (string) time());
        Audit::log('errors_ack', 'system');

        return redirect()->to(site_url('system-status'))->with('success', 'Galat ditandai sudah ditangani.');
    }

    /** Bersihkan cache aplikasi (aman; tidak menyentuh data). */
    public function clearCache()
    {
        cache()->clean();
        Audit::log('cache_clear', 'system');

        return redirect()->to(site_url('system-status'))->with('success', 'Cache aplikasi dibersihkan.');
    }

    /** Hapus file log lebih dari 30 hari. */
    public function pruneLogs()
    {
        $n = 0;
        foreach (glob(WRITEPATH . 'logs/log-*.log') ?: [] as $f) {
            if (filemtime($f) < strtotime('-30 days')) {
                @unlink($f) && $n++;
            }
        }
        Audit::log('logs_prune', 'system', null, null, ['deleted' => $n]);

        return redirect()->to(site_url('system-status'))->with('success', $n . ' file log lama dihapus.');
    }
}
