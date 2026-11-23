<?php

namespace App\Libraries;

/**
 * Pemeriksa keutuhan file aplikasi (pengganti enkripsi source code).
 * Paket rilis (scripts/build-release.php) menyertakan app/Config/release-manifest.json berisi SHA-256 setiap file
 * di app/, public/, spark, dan .htaccess. Di hosting, Status Sistem membandingkan file saat ini dengan manifest
 * sehingga perubahan file tanpa izin terlihat. Di development (tanpa manifest) pemeriksa tidak aktif.
 */
final class Integrity
{
    public const MANIFEST = APPPATH . 'Config/release-manifest.json';
    public const DIRS     = ['app', 'public'];
    public const FILES    = ['spark', '.htaccess'];

    /** Daftar file yang dipantau (path relatif dengan garis miring "/") → sha256. */
    public static function scan(string $root): array
    {
        $out = [];
        foreach (self::DIRS as $d) {
            if (! is_dir($root . '/' . $d)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $d, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                if ($rel === 'app/Config/release-manifest.json' || str_starts_with($rel, 'public/uploads/')) {
                    continue;
                }
                $out[$rel] = hash_file('sha256', $f->getPathname());
            }
        }
        foreach (self::FILES as $f) {
            if (is_file($root . '/' . $f)) {
                $out[$f] = hash_file('sha256', $root . '/' . $f);
            }
        }
        ksort($out);

        return $out;
    }

    /** Sidik jari manifest (ditampilkan saat build & di Status Sistem untuk dicocokkan). */
    public static function fingerprint(array $files): string
    {
        return substr(hash('sha256', json_encode($files)), 0, 16);
    }

    public static function write(string $root, string $version, ?string $commit = null): array
    {
        $files = self::scan($root);
        $data  = ['version' => $version, 'commit' => $commit, 'built_at' => date('c'), 'fingerprint' => self::fingerprint($files), 'files' => $files];
        file_put_contents($root . '/app/Config/release-manifest.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    /**
     * @return array{active: bool, version?: string, built_at?: string, fingerprint?: string, total?: int, changed?: list<string>, missing?: list<string>, added?: list<string>}
     */
    public static function check(bool $fresh = false): array
    {
        if (! is_file(self::MANIFEST)) {
            return ['active' => false];
        }
        $key = 'integrity_' . md5((string) filemtime(self::MANIFEST));
        if (! $fresh && ($c = cache($key))) {
            return $c;
        }
        $m = json_decode((string) file_get_contents(self::MANIFEST), true);
        if (! is_array($m) || ! is_array($m['files'] ?? null)) {
            return ['active' => true, 'total' => 0, 'changed' => ['app/Config/release-manifest.json (rusak)'], 'missing' => [], 'added' => [], 'fingerprint' => '—'];
        }
        $now  = self::scan(ROOTPATH);
        $res  = [
            'active'      => true,
            'version'     => (string) ($m['version'] ?? ''),
            'built_at'    => (string) ($m['built_at'] ?? ''),
            'fingerprint' => (string) ($m['fingerprint'] ?? ''),
            'total'       => count($m['files']),
            'changed'     => array_keys(array_filter($m['files'], static fn ($h, $f) => isset($now[$f]) && $now[$f] !== $h, ARRAY_FILTER_USE_BOTH)),
            'missing'     => array_keys(array_diff_key($m['files'], $now)),
            'added'       => array_values(array_filter(array_keys(array_diff_key($now, $m['files'])), static fn ($f) => ! str_starts_with($f, 'public/assets/css/'))),
        ];
        cache()->save($key, $res, 600);

        return $res;
    }

    /** Admin menerima kondisi file saat ini sebagai versi resmi (mis. setelah memasang perbaikan sendiri). */
    public static function accept(): bool
    {
        if (! is_file(self::MANIFEST) || ! is_writable(self::MANIFEST)) {
            return false;
        }
        $m = json_decode((string) file_get_contents(self::MANIFEST), true) ?: [];
        self::write(rtrim(ROOTPATH, '/\\'), (string) ($m['version'] ?? app_version()), $m['commit'] ?? null);
        cache()->clean();

        return true;
    }
}
