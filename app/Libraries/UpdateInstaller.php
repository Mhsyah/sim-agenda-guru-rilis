<?php

namespace App\Libraries;

use Throwable;

/**
 * Memasang pembaruan aplikasi dari GitHub (tombol "Pasang pembaruan" di Status Sistem).
 *
 * Urutan: backup database → unduh kode commit terbaru (zipball, memakai token baca) → periksa isi →
 * simpan salinan file lama → timpa file aplikasi (KECUALI writable/ dan .env) → migrasi database →
 * perbarui manifest keutuhan. Bila gagal di tengah, file lama dipulihkan.
 *
 * Instalasi hasil "git clone" (ada folder .git) diperbarui dengan git: backup database → git pull --ff-only
 * (ditolak bila ada berkas yang diubah manual) → migrasi → bersihkan cache. Membutuhkan perintah git & proc_open.
 * Bila dependensi composer berubah (composer.lock berbeda), pemasangan dibatalkan: gunakan paket rilis manual.
 */
final class UpdateInstaller
{
    /** Folder & berkas yang ikut diperbarui (relatif dari akar aplikasi). */
    private const DIRS  = ['app', 'public'];
    private const FILES = ['spark', '.htaccess', 'preload.php', 'composer.json', 'composer.lock', '.env.example', 'LICENSE', 'README.md', 'PANDUAN-HOSTING.md', 'PANDUAN-GITHUB.md'];
    private const MAX_BYTES = 60 * 1024 * 1024;

    /** Untuk pengujian: akar aplikasi lain (folder sementara) & lewati migrasi/backup. */
    public static ?string $root = null;
    public static bool $dryDb = false;

    private static function root(): string
    {
        return rtrim(self::$root ?? ROOTPATH, '/\\');
    }

    private static function work(): string
    {
        return self::root() . '/writable/update';
    }

    /** Instalasi hasil git clone (yang diperbarui dengan git pull)? */
    public static function isGit(): bool
    {
        return is_dir(self::root() . '/.git') && ! self::ignoresGit();
    }

    /**
     * Folder .git asing di server production (mis. sisa git clone repo kode private): diabaikan,
     * pembaruan dipasang dari paket ZIP repo rilis seperti instalasi biasa.
     */
    public static function ignoresGit(): bool
    {
        return self::$root === null && is_dir(self::root() . '/.git')
            && AppUpdate::isProduction() && ! AppUpdate::originIsSource(self::root());
    }

    /** Alasan pembaruan TIDAK bisa dipasang dari aplikasi, atau null bila bisa. */
    public static function blocker(): ?string
    {
        if (self::isGit()) {
            if (self::$root === null && AppUpdate::isDevCheckout()) {
                return 'Mode pengembangan (git clone repo kode, bukan repo rilis): perbarui dengan git pull, lalu terbitkan rilis dengan build-release.php --publish.';
            }

            return self::gitBlocker();
        }
        if (! class_exists(\ZipArchive::class)) {
            return 'Ekstensi PHP zip belum aktif di server.';
        }
        foreach (array_merge(self::DIRS, ['writable']) as $d) {
            if (! is_writable(self::root() . '/' . $d)) {
                return 'Folder ' . $d . '/ tidak dapat ditulis oleh server; gunakan paket rilis manual.';
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, message: string, steps: list<string>}
     */
    public static function install(): array
    {
        $steps = [];
        $fail  = static fn (string $m) => ['ok' => false, 'message' => $m, 'steps' => $steps];
        if ($b = self::blocker()) {
            return $fail($b);
        }
        $state = AppUpdate::state();
        $sha   = (string) ($state['commit']['sha'] ?? '');
        if (! preg_match('/^[0-9a-f]{40}$/', $sha) || (! AppUpdate::available($state) && ! AppUpdate::pendingCommits($state))) {
            return $fail('Tidak ada pembaruan untuk dipasang. Klik "Cek pembaruan" dulu.');
        }
        if (self::isGit()) {
            return self::installViaGit($state, $sha);
        }
        @set_time_limit(600);
        $work = self::work();
        self::rrmdir($work);
        @mkdir($work, 0750, true);
        @file_put_contents($work . '/.htaccess', "Require all denied\n");

        // 1) Backup database
        if (! self::$dryDb) {
            try {
                $steps[] = 'Backup database: ' . (new BackupService())->store('sebelum-pembaruan');
            } catch (Throwable $e) {
                return $fail('Backup database gagal, pembaruan dibatalkan.');
            }
        }

        // 2) Unduh kode commit terbaru
        $zipFile = $work . '/paket.zip';
        $err     = AppUpdate::downloadZip($sha, $zipFile, self::MAX_BYTES);
        if ($err !== null) {
            return $fail('Gagal mengunduh pembaruan: ' . $err);
        }
        $steps[] = 'Kode ' . substr($sha, 0, 7) . ' diunduh (' . round(filesize($zipFile) / 1048576, 1) . ' MB).';

        // 3) Ekstrak & periksa
        $new = $work . '/baru';
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return $fail('Berkas pembaruan rusak.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (str_contains($n, '..') || str_starts_with($n, '/') || preg_match('#^[A-Za-z]:#', $n)) {
                $zip->close();

                return $fail('Isi paket tidak aman (jalur berkas tidak wajar).');
            }
        }
        $zip->extractTo($new);
        $zip->close();
        $top = glob($new . '/*', GLOB_ONLYDIR) ?: [];
        $src = count($top) === 1 && ! is_dir($new . '/app') ? $top[0] : $new; // zipball GitHub: satu folder induk
        foreach (['app/Config/App.php', 'public/index.php', 'spark', 'app/Helpers/app_helper.php'] as $must) {
            if (! is_file($src . '/' . $must)) {
                return $fail('Paket tidak lengkap (' . $must . ' tidak ada).');
            }
        }
        // Paket rilis membawa folder vendor sendiri → vendor ikut diperbarui & perubahan composer.lock aman
        $dirs    = is_file($src . '/vendor/autoload.php') ? array_merge(self::DIRS, ['vendor']) : self::DIRS;
        $lockNow = in_array('vendor', $dirs, true) ? '' : (is_file(self::root() . '/composer.lock') ? hash_file('sha256', self::root() . '/composer.lock') : '');
        $lockNew = is_file($src . '/composer.lock') ? hash_file('sha256', $src . '/composer.lock') : '';
        if ($lockNow !== '' && $lockNew !== '' && $lockNow !== $lockNew) {
            return $fail('Dependensi (composer) berubah pada versi ini. Pasang lewat paket rilis manual agar folder vendor ikut diperbarui.');
        }
        $newVersion = preg_match("#return '(\\d+\\.\\d+\\.\\d+[^']*)';#", (string) file_get_contents($src . '/app/Helpers/app_helper.php'), $vm) ? $vm[1] : app_version();
        $steps[] = 'Isi paket diperiksa (v' . $newVersion . ').';

        // 4) Daftar perubahan berkas
        $incoming = self::listFiles($src, $dirs);
        $current  = self::listFiles(self::root(), $dirs);
        $removed  = array_diff_key($current, $incoming);   // berkas lama yang tidak ada lagi di versi baru
        unset($removed['app/Config/release-manifest.json']);

        // 5) Salin aman: simpan salinan lama dulu, lalu timpa; pulihkan bila gagal
        $backup  = $work . '/lama';
        $touched = [];
        try {
            foreach ($incoming as $rel => $_) {
                $dst = self::root() . '/' . $rel;
                if (is_file($dst)) {
                    self::copyFile($dst, $backup . '/' . $rel);
                }
                $touched[$rel] = is_file($dst);
                self::copyFile($src . '/' . $rel, $dst);
            }
            foreach ($removed as $rel => $_) {
                self::copyFile(self::root() . '/' . $rel, $backup . '/' . $rel);
                $touched[$rel] = true;
                if (! @unlink(self::root() . '/' . $rel)) {
                    throw new \RuntimeException('tidak dapat menghapus ' . $rel);
                }
            }
        } catch (Throwable $e) {
            self::rollback($touched, $backup);

            return $fail('Gagal menyalin berkas (' . mb_substr($e->getMessage(), 0, 120) . '). Berkas lama sudah dipulihkan.');
        }
        $steps[] = count($incoming) . ' berkas diperbarui' . ($removed ? ', ' . count($removed) . ' berkas usang dihapus' : '') . '.';

        // 6) Migrasi database
        if (! self::$dryDb) {
            try {
                \Config\Services::migrations(null, null, false)->setNamespace(null)->latest();
                if ($left = \App\Controllers\SystemStatus::pendingMigrations()) {
                    $steps[] = count($left) . ' migrasi belum terpasang otomatis — klik "Jalankan migrasi" di Status Sistem.';
                }
                $steps[] = 'Struktur database diperbarui (migrasi).';
            } catch (Throwable $e) {
                self::rollback($touched, $backup);
                log_message('error', 'Migrasi pembaruan gagal: ' . $e->getMessage());

                return $fail('Migrasi database gagal; berkas lama dipulihkan. Pulihkan database dari backup "sebelum-pembaruan" bila perlu.');
            }
        }

        // 7) Manifest keutuhan & bersih-bersih
        Integrity::write(self::root(), $newVersion, $sha);
        $state['commit']['installed'] = $sha;
        $state['commit']['ahead']     = 0;
        if (isset($state['latest']['version']) && version_compare($state['latest']['version'], $newVersion, '<=')) {
            $state['latest']['version'] = $newVersion;
        }
        AppUpdate::saveState($state);
        self::rrmdir($work);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (! self::$dryDb) {
            cache()->clean();
        }
        $steps[] = 'Selesai: terpasang ' . substr($sha, 0, 7) . ' (v' . $newVersion . ').';

        return ['ok' => true, 'message' => 'Pembaruan terpasang: v' . $newVersion . ' (' . substr($sha, 0, 7) . ').', 'steps' => $steps];
    }

    /** @return array<string, true> berkas yang dikelola pembaruan (relatif) */
    private static function listFiles(string $root, array $dirs = self::DIRS): array
    {
        $out = [];
        foreach ($dirs as $d) {
            if (! is_dir($root . '/' . $d)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $d, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $out[str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = true;
                }
            }
        }
        foreach (self::FILES as $f) {
            if (is_file($root . '/' . $f)) {
                $out[$f] = true;
            }
        }

        return $out;
    }

    private static function copyFile(string $from, string $to): void
    {
        if (! is_dir(dirname($to)) && ! @mkdir(dirname($to), 0755, true) && ! is_dir(dirname($to))) {
            throw new \RuntimeException('tidak dapat membuat folder ' . dirname($to));
        }
        if (! @copy($from, $to)) {
            throw new \RuntimeException('tidak dapat menulis ' . $to);
        }
    }

    private static function rollback(array $touched, string $backup): void
    {
        foreach ($touched as $rel => $existed) {
            $dst = self::root() . '/' . $rel;
            if ($existed && is_file($backup . '/' . $rel)) {
                @copy($backup . '/' . $rel, $dst);
            } elseif (! $existed) {
                @unlink($dst);
            }
        }
    }

    private static function rrmdir(string $d): void
    {
        if (! is_dir($d)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($d, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($d);
    }

    // ---------------------------------------------------------------- Instalasi hasil git clone

    /** Jalankan git di akar aplikasi. Token baca (bila ada) dikirim lewat variabel lingkungan, tidak tampil di perintah. */
    private static function git(array $args, ?string &$out = null): int
    {
        $cmd = 'git';
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }
        $env = getenv() ?: [];
        $env['GIT_TERMINAL_PROMPT'] = '0';
        if (($t = trim((string) config('AppUpdate')->githubToken)) !== '') {
            $env['GIT_CONFIG_COUNT']   = '1';
            $env['GIT_CONFIG_KEY_0']   = 'http.https://github.com/.extraheader';
            $env['GIT_CONFIG_VALUE_0'] = 'AUTHORIZATION: basic ' . base64_encode('x-access-token:' . $t);
        }
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::root(), $env);
        if (! is_resource($p)) {
            $out = 'proc_open gagal';

            return 127;
        }
        $out = trim(stream_get_contents($pipes[1]) . "\n" . stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($p);
    }

    private static function gitBlocker(): ?string
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (! function_exists('proc_open') || in_array('proc_open', $disabled, true)) {
            return 'Instalasi hasil git clone, tetapi fungsi PHP proc_open dinonaktifkan di server: jalankan git pull secara manual, lalu "Jalankan migrasi".';
        }
        if (self::git(['--version'], $o) !== 0) {
            return 'Instalasi hasil git clone, tetapi perintah git tidak tersedia di server: jalankan git pull secara manual.';
        }
        // Pastikan repo git memang folder aplikasi ini (bukan folder induk)
        $top = self::git(['rev-parse', '--show-toplevel'], $o) === 0 ? realpath(trim((string) $o)) : false;
        $norm = static fn ($p) => strtolower(rtrim(str_replace('\\', '/', (string) $p), '/'));
        if (! $top || $norm($top) !== $norm(realpath(self::root()))) {
            return 'Folder .git ada tetapi bukan repo git yang valid untuk aplikasi ini: perbarui manual atau pasang ulang dari paket ZIP.';
        }
        if (self::git(['symbolic-ref', '--short', '-q', 'HEAD'], $o) !== 0 || trim((string) $o) === '') {
            return 'Repo git dalam keadaan "detached HEAD": pindah ke cabang utama (git checkout main) lalu coba lagi.';
        }
        self::git(['status', '--porcelain', '--untracked-files=no'], $o);
        if (trim((string) $o) !== '') {
            return 'Ada berkas aplikasi yang diubah manual (git status tidak bersih): simpan/batalkan perubahan itu dulu agar git pull aman.';
        }

        return null;
    }

    private static function installViaGit(array $state, string $sha): array
    {
        $steps = [];
        $fail  = static fn (string $m) => ['ok' => false, 'message' => $m, 'steps' => $steps];
        @set_time_limit(600);
        self::git(['symbolic-ref', '--short', 'HEAD'], $branch);
        $branch = trim((string) $branch);
        self::git(['rev-parse', 'HEAD'], $before);
        $before = trim((string) $before);

        if (! self::$dryDb) {
            try {
                $steps[] = 'Backup database: ' . (new BackupService())->store('sebelum-pembaruan');
            } catch (Throwable $e) {
                return $fail('Backup database gagal, pembaruan dibatalkan.');
            }
        }
        if (self::git(['pull', '--ff-only', 'origin', $branch], $o) !== 0) {
            $msg = mb_substr(preg_replace('/\s+/', ' ', (string) $o), 0, 200);

            return $fail('git pull gagal: ' . (stripos($msg, 'auth') !== false || stripos($msg, 'could not read') !== false || stripos($msg, 'not found') !== false
                ? 'akses ke GitHub ditolak — isi appupdate.githubToken (Contents: Read-only) di .env.' : $msg));
        }
        self::git(['rev-parse', 'HEAD'], $after);
        $after = trim((string) $after);
        $steps[] = 'git pull: ' . substr($before, 0, 7) . ' → ' . substr($after, 0, 7) . ' (cabang ' . $branch . ').';
        self::git(['diff', '--name-only', $before, $after, '--', 'composer.lock'], $lock);
        if (trim((string) $lock) !== '') {
            $steps[] = 'Perhatian: composer.lock berubah — jalankan "composer install --no-dev" di server agar folder vendor ikut diperbarui.';
        }
        if (! self::$dryDb) {
            try {
                \Config\Services::migrations(null, null, false)->setNamespace(null)->latest();
                if ($left = \App\Controllers\SystemStatus::pendingMigrations()) {
                    $steps[] = count($left) . ' migrasi belum terpasang otomatis — klik "Jalankan migrasi" di Status Sistem.';
                }
                $steps[] = 'Struktur database diperbarui (migrasi).';
            } catch (Throwable $e) {
                log_message('error', 'Migrasi pembaruan (git) gagal: ' . $e->getMessage());

                return $fail('Kode sudah diperbarui, tetapi migrasi database gagal. Klik "Jalankan migrasi" atau pulihkan dari backup "sebelum-pembaruan".');
            }
            cache()->clean();
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $newVersion = preg_match("#return '(\d+\.\d+\.\d+[^']*)';#", (string) @file_get_contents(self::root() . '/app/Helpers/app_helper.php'), $vm) ? $vm[1] : app_version();
        $state['commit']['installed'] = $after;
        $state['commit']['ahead']     = $after === $sha ? 0 : (int) ($state['commit']['ahead'] ?? 0);
        AppUpdate::saveState($state);
        $steps[] = 'Selesai: terpasang ' . substr($after, 0, 7) . ' (v' . $newVersion . ').';

        return ['ok' => true, 'message' => 'Pembaruan terpasang lewat git: v' . $newVersion . ' (' . substr($after, 0, 7) . ').', 'steps' => $steps];
    }
}
