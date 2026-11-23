<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Logo sekolah disimpan di writable/uploads/logo (tidak dapat dieksekusi dari web)
 * dan disajikan lewat rute /school-logo. Hanya PNG, JPG, dan WebP (SVG ditolak demi keamanan).
 */
final class SchoolLogo
{
    public const MAX_BYTES = 1048576; // 1 MB
    private const TYPES    = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    public static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
        }

        return $dir;
    }

    public static function path(?string $name): ?string
    {
        if (! $name || ! preg_match('/^(logo|gov|ttd|cover)-[a-f0-9]{16}\.(png|jpg|webp)$/', $name)) {
            return null;
        }
        $p = self::dir() . $name;

        return is_file($p) ? $p : null;
    }

    /** URL logo dengan penanda versi, atau null bila belum ada logo. */
    public static function url(?array $profile): ?string
    {
        $name = $profile['school_logo'] ?? null;

        return self::path($name) ? site_url('school-logo') . '?v=' . substr((string) $name, 5, 8) : null;
    }

    /** URL logo instansi (gov) atau foto sekolah untuk sampul (cover). */
    public static function assetUrl(?string $name, string $kind): ?string
    {
        return $name && str_starts_with($name, $kind . '-') && self::path($name)
            ? site_url('school-logo/' . $kind) . '?v=' . substr($name, strlen($kind) + 1, 8) : null;
    }

    /** @return string nama file baru, atau melempar RuntimeException berisi pesan untuk pengguna */
    public static function store(UploadedFile $file, string $prefix = 'logo'): string
    {
        if (! $file->isValid()) {
            throw new \RuntimeException('Unggahan gambar gagal. Coba lagi.');
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . self::inspect($file->getTempName());
        $file->move(self::dir(), $name, true);

        return $name;
    }

    /**
     * Pastikan file benar-benar gambar PNG/JPG/WebP berukuran wajar.
     *
     * @return string ekstensi file (png|jpg|webp)
     */
    public static function inspect(string $path): string
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new \RuntimeException('Ukuran logo maksimal 1 MB.');
        }
        $info = @getimagesize($path);
        if (! $info || ! isset(self::TYPES[$info[2]])) {
            throw new \RuntimeException('Logo harus berupa gambar PNG, JPG, atau WebP.');
        }
        if ($info[0] > 4000 || $info[1] > 4000) {
            throw new \RuntimeException('Dimensi logo terlalu besar (maks. 4000 × 4000 piksel).');
        }

        return self::TYPES[$info[2]];
    }

    public static function delete(?string $name): void
    {
        if ($p = self::path($name)) {
            @unlink($p);
        }
    }

    /** Ganti awalan file (mis. logo- ⇄ gov-) saat posisi logo ditukar. */
    public static function reprefix(?string $name, string $prefix): ?string
    {
        if (! ($p = self::path($name))) {
            return null;
        }
        $new = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . pathinfo($p, PATHINFO_EXTENSION);

        return rename($p, self::dir() . $new) ? $new : null;
    }

    /**
     * Area isi foto (tanpa latar polos di tepi), dalam pecahan 0–1: [x, y, lebar, tinggi]. Disimpan di cache per file.
     * Dipakai spanduk agar gedung sekolah tampil besar & jelas walau foto punya banyak ruang kosong.
     */
    public static function contentBox(string $file): array
    {
        $key = 'imgbox_' . md5($file . '|' . @filemtime($file));

        return cache()->remember($key, 30 * 86400, static function () use ($file): array {
            try {
                $im = ImageTool::load($file);
            } catch (\Throwable $e) {
                return [0, 0, 1, 1];
            }
            $w = imagesx($im);
            $h = imagesy($im);
            $rgb = static function ($x, $y) use ($im): array {
                $c = imagecolorat($im, $x, $y);

                return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            };
            $bg = array_map(static fn (...$v) => array_sum($v) / 4, ...[$rgb(1, 1), $rgb($w - 2, 1), $rgb(1, $h - 2), $rgb($w - 2, $h - 2)]);
            $isBg = static function ($x, $y) use ($im, $rgb, $bg): bool {
                if (((imagecolorat($im, $x, $y) >> 24) & 127) > 100) {
                    return true; // transparan
                }
                [$r, $g, $b] = $rgb($x, $y);

                return abs($r - $bg[0]) + abs($g - $bg[1]) + abs($b - $bg[2]) < 60;
            };
            $step = max(2, (int) round(max($w, $h) / 400));
            [$x0, $y0, $x1, $y1] = [$w, $h, 0, 0];
            for ($y = 0; $y < $h; $y += $step) {
                for ($x = 0; $x < $w; $x += $step) {
                    if (! $isBg($x, $y)) {
                        $x0 = min($x0, $x);
                        $y0 = min($y0, $y);
                        $x1 = max($x1, $x);
                        $y1 = max($y1, $y);
                    }
                }
            }
            imagedestroy($im);
            if ($x1 <= $x0 || $y1 <= $y0) {
                return [0, 0, 1, 1];
            }
            // beri sedikit ruang di sekeliling isi
            $pad = 0.03;
            $bx  = max(0, $x0 / $w - $pad);
            $by  = max(0, $y0 / $h - $pad);
            $bw  = min(1 - $bx, ($x1 - $x0) / $w + 2 * $pad);
            $bh  = min(1 - $by, ($y1 - $y0) / $h + 2 * $pad);

            return [round($bx, 4), round($by, 4), round($bw, 4), round($bh, 4)];
        });
    }

    /** Foto sekolah untuk sampul & spanduk: diperkecil (sisi terpanjang 2400 px) & dikonversi ke WebP (maks. 900 KB). */
    public static function storeCover(string $tmp): string
    {
        if (! ImageTool::isImage($tmp) || (int) @filesize($tmp) > 15 * 1048576) {
            throw new \RuntimeException('Foto sekolah harus gambar JPG/PNG/WebP, maks. 15 MB.');
        }
        $name = 'cover-' . bin2hex(random_bytes(8)) . '.webp';
        ImageTool::saveWebp(ImageTool::fit(ImageTool::load($tmp), 2400), self::dir() . $name, 900 * 1024);

        return $name;
    }
}
