<?php

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AccessLimit;
use Config\Services;

/**
 * Pembatas akses per IP (nilai diatur dari .env, lihat Config\AccessLimit):
 * - IP dalam daftar blokir → 403;
 * - admin teknis hanya dari IP yang diizinkan (bila diatur) → 403;
 * - batas permintaan per menit, percobaan login, dan unggahan → 429 "Terlalu banyak permintaan".
 */
class RateLimit implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var AccessLimit $cfg */
        $cfg = config('AccessLimit');
        if (! $cfg->enabled) {
            return null;
        }
        $ip = (string) $request->getIPAddress();

        if (self::ipMatches($ip, $cfg->blockedIps)) {
            return self::deny(403, 'Akses dari alamat IP ini diblokir.');
        }
        if (trim($cfg->adminAllowedIps) !== '' && Auth::check() && Auth::isAdmin() && ! self::ipMatches($ip, $cfg->adminAllowedIps)) {
            return self::deny(403, 'Akun admin teknis hanya dapat dipakai dari jaringan yang diizinkan sekolah.');
        }

        $throttler = Services::throttler();
        $key       = 'rl_' . md5($ip);
        $method    = strtolower($request->getMethod());
        $path      = trim($request->getUri()->getPath(), '/');

        if ($method === 'post' && preg_match('#(^|/)login$#', $path)) {
            [$n, $sec] = self::pair($cfg->login, 10, 300);
            if (! $throttler->check($key . '_login', $n, $sec)) {
                return self::deny(429, 'Terlalu banyak percobaan login dari jaringan ini. Coba lagi beberapa menit lagi.', $throttler->getTokenTime());
            }
        }
        if ($method === 'post' && str_contains(strtolower($request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
            [$n, $sec] = self::pair($cfg->upload, 20, 60);
            if (! $throttler->check($key . '_up', $n, $sec)) {
                return self::deny(429, 'Terlalu banyak unggahan dalam waktu singkat. Tunggu sebentar lalu coba lagi.', $throttler->getTokenTime());
            }
        }
        if ($cfg->perMinute > 0 && ! $throttler->check($key, $cfg->perMinute, MINUTE)) {
            return self::deny(429, 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu muat ulang halaman.', $throttler->getTokenTime());
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /** "10/300" → [10, 300] */
    public static function pair(string $v, int $n, int $sec): array
    {
        return preg_match('#^\s*(\d+)\s*/\s*(\d+)\s*$#', $v, $m) && (int) $m[1] > 0 && (int) $m[2] > 0 ? [(int) $m[1], (int) $m[2]] : [$n, $sec];
    }

    public static function ipMatches(string $ip, string $list): bool
    {
        foreach (array_filter(array_map('trim', explode(',', $list))) as $rule) {
            if ($rule === $ip || (str_ends_with($rule, '*') && str_starts_with($ip, rtrim($rule, '*')))) {
                return true;
            }
        }

        return false;
    }

    private static function deny(int $code, string $msg, int $retry = 0): ResponseInterface
    {
        $res = Services::response()->setStatusCode($code)->setHeader('Cache-Control', 'no-store');
        if ($retry > 0) {
            $res->setHeader('Retry-After', (string) max(1, $retry));
        }
        $req = Services::request();
        if ($req instanceof \CodeIgniter\HTTP\IncomingRequest && ($req->isAJAX() || str_contains($req->getHeaderLine('Accept'), 'application/json'))) {
            return $res->setJSON(['error' => $msg]);
        }
        $title = $code === 429 ? 'Terlalu banyak permintaan' : 'Akses ditolak';

        return $res->setBody('<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f1f5f9;color:#0f172a}main{max-width:28rem;padding:2rem;background:#fff;border-radius:1rem;box-shadow:0 10px 30px rgb(15 23 42/.1);text-align:center}h1{font-size:1.2rem}p{color:#475569}</style></head>'
            . '<body><main><h1>' . $code . ' · ' . $title . '</h1><p>' . esc($msg) . '</p></main></body></html>');
    }
}
