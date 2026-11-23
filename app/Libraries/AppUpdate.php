<?php

namespace App\Libraries;

use Config\Changelog;
use Throwable;

/**
 * Info versi & pengecekan pembaruan aplikasi.
 * - Catatan rilis bawaan (offline) dari Config\Changelog.
 * - Cek pembaruan ke alamat rilis yang diisi admin: repositori GitHub ("pemilik/repo" atau URL github.com)
 *   atau URL JSON {"version","url","notes","date"}.
 * - Cek otomatis 1× sehari (bila diaktifkan) → notifikasi lonceng admin.
 * Pembaruan TIDAK dipasang otomatis; admin mengunduh & memasang sendiri sesuai panduan.
 */
final class AppUpdate
{
    private const FILE = WRITEPATH . 'update-check.json';

    public static function current(): string
    {
        return app_version();
    }

    /**
     * Catatan rilis yang ditampilkan: versi terpasang + SATU versi sebelumnya.
     * Saat versi naik satu tingkat, versi yang lebih lama otomatis tidak ditampilkan lagi.
     *
     * @return list<array{version: string, date: string, notes: list<string>}>
     */
    public static function releases(): array
    {
        $all = Changelog::$releases;
        usort($all, static fn ($a, $b) => version_compare($b['version'], $a['version']));
        $cur = array_values(array_filter($all, static fn ($r) => version_compare($r['version'], app_version(), '<=')));

        return array_slice($cur ?: $all, 0, 2);
    }

    /** Pengaturan + hasil pengecekan terakhir. */
    public static function state(): array
    {
        $s = is_file(self::FILE) ? json_decode((string) @file_get_contents(self::FILE), true) : null;

        $s   = (is_array($s) ? $s : []) + ['checked_at' => null, 'latest' => null, 'error' => null];
        $cfg = config('AppUpdate');
        $src = trim((string) $cfg->source);
        // hasil cek lama dari alamat lain diabaikan
        if (($s['source'] ?? $src) !== $src) {
            $s['latest'] = null;
            $s['error']  = null;
            $s['checked_at'] = null;
        }
        $s['source']    = self::endpoint($src) ? $src : '';
        $s['auto']      = $cfg->autoCheck && $s['source'] !== '' ? 1 : 0;
        $s['hasToken']  = trim((string) $cfg->githubToken) !== '';

        return $s;
    }

    private static function save(array $s): void
    {
        unset($s['auto'], $s['hasToken']);
        @file_put_contents(self::FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** URL API yang dipanggil untuk alamat rilis. */
    private static function endpoint(string $source): ?array
    {
        if (preg_match('#^(?:https?://github\.com/)?([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $source, $m)) {
            return ['github', 'https://api.github.com/repos/' . $m[1] . '/' . $m[2] . '/releases/latest'];
        }
        if (filter_var($source, FILTER_VALIDATE_URL) && str_starts_with(strtolower($source), 'https://')) {
            return ['json', $source];
        }

        return null;
    }

    /** Cek pembaruan sekarang. */
    public static function check(): array
    {
        $s = self::state();
        $ep = $s['source'] !== '' ? self::endpoint($s['source']) : null;
        $s['checked_at'] = time();
        if (! $ep) {
            $s['error'] = 'Alamat rilis belum diisi.';
            self::save($s);

            return $s;
        }
        try {
            $latest = $ep[0] === 'github' ? self::fromGithub($ep[1]) : self::fromJson($ep[1]);
            if (! preg_match('/^\d+(\.\d+){0,3}/', $latest['version'])) {
                throw new \RuntimeException('Nomor versi tidak ditemukan');
            }
            if ($latest['url'] !== '' && ! str_starts_with(strtolower($latest['url']), 'https://')) {
                $latest['url'] = '';
            }
            $s['latest'] = $latest;
            $s['error']  = null;
            // Selain nomor versi, bandingkan commit terpasang dengan commit terbaru di GitHub
            $s['commit'] = $ep[0] === 'github' ? self::commitStatus($ep[1]) : null;
        } catch (Throwable $e) {
            $s['error'] = 'Gagal mengecek pembaruan (' . mb_substr($e->getMessage(), 0, 160) . ').';
        }
        self::save($s);

        return $s;
    }

    /** Simpan status cek (dipakai pemasang pembaruan). */
    public static function saveState(array $s): void
    {
        self::save($s);
    }

    /** Unduh kode satu commit dari GitHub (zipball) ke $dest. Mengembalikan pesan galat atau null. */
    public static function downloadZip(string $sha, string $dest, int $maxBytes): ?string
    {
        $s  = self::state();
        $ep = $s['source'] !== '' ? self::endpoint($s['source']) : null;
        if (! $ep || $ep[0] !== 'github' || ! preg_match('/^[0-9a-f]{40}$/', $sha)) {
            return 'sumber rilis bukan repo GitHub';
        }
        $url = substr($ep[1], 0, -strlen('/releases/latest')) . '/zipball/' . $sha;
        try {
            if (array_key_exists($url, self::$fake)) {
                [$code, $body] = self::$fake[$url];
            } elseif (self::$fake !== []) {
                [$code, $body] = [404, ''];
            } else {
                $headers = ['User-Agent' => 'AgendaGuru-Updater', 'Accept' => 'application/vnd.github+json'];
                if (($t = trim((string) config('AppUpdate')->githubToken)) !== '') {
                    $headers['Authorization'] = 'Bearer ' . $t;
                }
                // GitHub mengalihkan zipball ke codeload.github.com (302): ikuti pengalihan secara eksplisit (hanya https)
                $next = $url;
                for ($hop = 0; $hop < 5; $hop++) {
                    $res  = service('curlrequest', ['timeout' => 180, 'connect_timeout' => 8, 'http_errors' => false], null, null, false)
                        ->get($next, ['headers' => $headers, 'allow_redirects' => false]);
                    $code = $res->getStatusCode();
                    $loc  = $res->getHeaderLine('Location');
                    if ($code >= 300 && $code < 400 && str_starts_with(strtolower($loc), 'https://')) {
                        $next = $loc;
                        unset($headers['Authorization']); // token tidak dikirim ke alamat unduhan
                        continue;
                    }
                    break;
                }
                $body = (string) $res->getBody();
            }
        } catch (Throwable $e) {
            return 'koneksi ke GitHub gagal (' . mb_substr($e->getMessage(), 0, 100) . ')';
        }
        if ($code === 401 || $code === 403 || $code === 404) {
            return 'akses ditolak (HTTP ' . $code . ') — periksa token baca di .env';
        }
        if ($code !== 200 || strlen($body) < 100) {
            return 'GitHub mengembalikan HTTP ' . $code;
        }
        if (strlen($body) > $maxBytes || ! str_starts_with($body, "PK")) {
            return 'berkas yang diterima bukan paket ZIP yang wajar';
        }

        return file_put_contents($dest, $body) === false ? 'tidak dapat menyimpan berkas di writable/update' : null;
    }

    /** Untuk pengujian: respons palsu per URL (tanpa jaringan). */
    public static array $fake = [];

    /** GET JSON; repo private memakai token baca dari .env (appupdate.githubToken). */
    private static function getJson(string $url, bool $github): array
    {
        if (array_key_exists($url, self::$fake)) {
            [$code, $body] = self::$fake[$url];
        } elseif (self::$fake !== []) {
            [$code, $body] = [404, '{}']; // mode uji: tidak pernah memanggil jaringan
        } else {
            $headers = ['User-Agent' => 'AgendaGuru-UpdateCheck', 'Accept' => $github ? 'application/vnd.github+json' : 'application/json'];
            $token   = trim((string) (config('AppUpdate')->githubToken ?? ''));
            if ($github && $token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            $res  = service('curlrequest', ['timeout' => 8, 'connect_timeout' => 4, 'http_errors' => false])->get($url, ['headers' => $headers]);
            $code = $res->getStatusCode();
            $body = (string) $res->getBody();
        }
        if ($code === 404 && $github) {
            return ['__404' => true];
        }
        if ($code === 401 || $code === 403) {
            throw new \RuntimeException('akses ditolak — repo private butuh appupdate.githubToken di .env');
        }
        if ($code !== 200) {
            throw new \RuntimeException('HTTP ' . $code);
        }
        $j = json_decode($body, true);
        if (! is_array($j)) {
            throw new \RuntimeException('Respons bukan JSON');
        }

        return $j;
    }

    /** GitHub: Release terbaru; bila belum ada Release, tag versi tertinggi (v0.2.0) dipakai. */
    private static function fromGithub(string $releaseUrl): array
    {
        $j = self::getJson($releaseUrl, true);
        if (empty($j['__404'])) {
            return ['version' => ltrim((string) ($j['tag_name'] ?? ''), 'vV'), 'url' => (string) ($j['html_url'] ?? ''),
                'notes' => mb_substr(trim((string) ($j['body'] ?? '')), 0, 1500), 'date' => substr((string) ($j['published_at'] ?? ''), 0, 10)];
        }
        $repoApi = substr($releaseUrl, 0, -strlen('/releases/latest'));
        $tags    = self::getJson($repoApi . '/tags?per_page=100', true);
        if (! empty($tags['__404'])) {
            // Repo private menjawab 404 bila tanpa token / token tanpa akses
            throw new \RuntimeException(trim((string) config('AppUpdate')->githubToken) === ''
                ? 'repo private — token belum diisi: tambahkan appupdate.githubToken di .env server ini'
                : 'token di .env tidak punya akses ke repo ini atau sudah kedaluwarsa — buat token baru (Contents: Read-only) untuk repo tersebut');
        }
        $best = null;
        foreach ($tags as $t) {
            $v = ltrim((string) ($t['name'] ?? ''), 'vV');
            if (preg_match('/^\d+(\.\d+){0,3}$/', $v) && ($best === null || version_compare($v, $best, '>'))) {
                $best = $v;
            }
        }
        if ($best === null) {
            throw new \RuntimeException('belum ada Release atau tag versi (mis. v0.2.0) di repo');
        }
        $html = 'https://github.com/' . substr($repoApi, strlen('https://api.github.com/repos/'));

        return ['version' => $best, 'url' => $html . '/tree/v' . $best, 'notes' => '', 'date' => ''];
    }

    private static function fromJson(string $url): array
    {
        $j = self::getJson($url, false);

        return ['version' => ltrim((string) ($j['version'] ?? ''), 'vV'), 'url' => (string) ($j['url'] ?? ''),
            'notes' => mb_substr(trim(is_array($j['notes'] ?? null) ? implode("\n", $j['notes']) : (string) ($j['notes'] ?? '')), 0, 1500), 'date' => (string) ($j['date'] ?? '')];
    }

    /** Cek otomatis (dipanggil saat notifikasi admin disusun): paling sering 1× per 24 jam. */
    public static function autoCheck(): array
    {
        $s = self::state();
        if ($s['auto'] && $s['source'] !== '' && (int) $s['checked_at'] < time() - 86400 && ENVIRONMENT !== 'testing') {
            $s = self::check();
        }

        return $s;
    }

    /** Versi baru yang tersedia (lebih tinggi dari versi terpasang), atau null. */
    public static function available(?array $s = null): ?array
    {
        $l = ($s ?? self::state())['latest'] ?? null;

        return $l && version_compare($l['version'], self::current(), '>') ? $l : null;
    }

    /** Jumlah commit di GitHub yang belum terpasang (versi sama, kode lebih baru); 0 bila tidak ada/tidak diketahui. */
    public static function pendingCommits(?array $s = null): int
    {
        return (int) (($s ?? self::state())['commit']['ahead'] ?? 0);
    }

    /**
     * Commit yang sedang terpasang: dari manifest paket rilis, atau dari folder .git (development).
     */
    /** Untuk pengujian: commit terpasang & status development tiruan. */
    public static ?string $installedOverride = null;
    public static ?bool $devOverride = null;

    /** URL remote "origin" dari .git/config (tanpa menjalankan git), atau null. */
    public static function gitOrigin(string $root): ?string
    {
        $cfg = $root . '/.git/config';
        if (! is_file($cfg)) {
            return null;
        }
        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', (string) file_get_contents($cfg), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Folder development: hasil git clone dari repo SELAIN repo rilis (mis. repo kode private).
     * Di sini pembaruan otomatis tidak dijalankan & perbandingan commit dilewati (perbarui dengan git pull lalu build rilis).
     */
    public static function isDevCheckout(?string $root = null): bool
    {
        if (self::$devOverride !== null) {
            return self::$devOverride;
        }
        $root ??= rtrim(ROOTPATH, '/\\');
        if (! is_dir($root . '/.git')) {
            return false;
        }
        $origin = strtolower((string) self::gitOrigin($root));
        $src    = strtolower(trim((string) config('AppUpdate')->source));

        return $src === '' || ! preg_match('#[/:]' . preg_quote($src, '#') . '(\.git)?/?$#', $origin);
    }

    public static function installedCommit(): ?string
    {
        if (self::$installedOverride !== null) {
            return self::$installedOverride;
        }
        if (is_file(Integrity::MANIFEST)) {
            $m = json_decode((string) file_get_contents(Integrity::MANIFEST), true);
            if (preg_match('/^[0-9a-f]{40}$/', (string) ($m['commit'] ?? ''))) {
                return $m['commit'];
            }
        }
        $git  = ROOTPATH . '.git/';
        $head = is_file($git . 'HEAD') ? trim((string) file_get_contents($git . 'HEAD')) : '';
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return $head;
        }
        if (preg_match('#^ref: (refs/heads/[\w./-]+)$#', $head, $m)) {
            if (is_file($git . $m[1])) {
                $sha = trim((string) file_get_contents($git . $m[1]));

                return preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : null;
            }
            foreach (is_file($git . 'packed-refs') ? file($git . 'packed-refs') : [] as $line) {
                if (preg_match('#^([0-9a-f]{40}) ' . preg_quote($m[1], '#') . '$#', trim($line), $mm)) {
                    return $mm[1];
                }
            }
        }

        return null;
    }

    /** Commit terbaru cabang utama di GitHub + berapa commit yang belum terpasang. */
    private static function commitStatus(string $releaseUrl): ?array
    {
        $repoApi = substr($releaseUrl, 0, -strlen('/releases/latest'));
        try {
            $c = self::getJson($repoApi . '/commits/HEAD', true);
        } catch (Throwable $e) {
            return null;
        }
        $sha = (string) ($c['sha'] ?? '');
        if (! preg_match('/^[0-9a-f]{40}$/', $sha)) {
            return null;
        }
        $out = [
            'sha'     => $sha,
            'message' => mb_substr((string) strtok((string) ($c['commit']['message'] ?? ''), "\n"), 0, 140),
            'date'    => substr((string) ($c['commit']['committer']['date'] ?? $c['commit']['author']['date'] ?? ''), 0, 10),
            'url'     => (string) ($c['html_url'] ?? ''),
            'ahead'   => 0,
        ];
        $mine = self::installedCommit();
        $out['installed'] = $mine;
        $out['dev']       = self::isDevCheckout();
        if ($out['dev']) {
            $out['installed'] = null; // commit repo development tidak sebanding dengan commit repo rilis

            return $out;
        }
        if ($mine && $mine !== $sha) {
            try {
                $cmp = self::getJson($repoApi . '/compare/' . $mine . '...' . $sha, true);
                // ahead_by = jumlah commit di GitHub yang belum ada di instalasi ini
                // Commit terpasang tidak dikenal repo rilis (mis. dari repo kode lama) → dianggap perlu diperbarui
                $out['ahead'] = ! empty($cmp['__404']) ? 1
                    : (in_array($cmp['status'] ?? '', ['ahead', 'diverged'], true) ? (int) ($cmp['ahead_by'] ?? 0) : 0);
            } catch (Throwable $e) {
                $out['ahead'] = 0;
            }
        }

        return $out;
    }
}
