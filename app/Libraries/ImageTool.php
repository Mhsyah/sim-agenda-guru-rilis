<?php

namespace App\Libraries;

use GdImage;
use RuntimeException;

/** Olah gambar dengan GD: baca, putar sesuai EXIF (foto HP), perkecil, simpan WebP sesuai batas ukuran. */
final class ImageTool
{
    private const READERS = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_BMP  => 'imagecreatefrombmp',
    ];

    public static function isImage(string $path): bool
    {
        $info = @getimagesize($path);

        return $info && isset(self::READERS[$info[2]]);
    }

    public static function load(string $path): GdImage
    {
        $info = @getimagesize($path);
        if (! $info || ! isset(self::READERS[$info[2]]) || ! function_exists(self::READERS[$info[2]])) {
            throw new RuntimeException('Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('Resolusi gambar terlalu besar (maks. ± 40 megapiksel).');
        }
        $img = @(self::READERS[$info[2]])($path);
        if (! $img instanceof GdImage) {
            throw new RuntimeException('Gambar rusak atau tidak dapat dibaca.');
        }
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $info[2] === IMAGETYPE_JPEG ? self::orient($img, $path) : $img;
    }

    /** Foto kamera HP sering disimpan miring dengan tanda EXIF; putar agar tegak. */
    private static function orient(GdImage $img, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $r = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => null };
        if ($o === 2 || $o === 4) {
            imageflip($img, $o === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        return $r instanceof GdImage ? $r : $img;
    }

    public static function fit(GdImage $img, int $max): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $max && $h <= $max) {
            return $img;
        }
        $k = $max / max($w, $h);

        return self::resample($img, 0, 0, $w, $h, max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
    }

    /** Potong persegi di tengah lalu perkecil (untuk foto profil). */
    public static function square(GdImage $img, int $size): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $s = min($w, $h);

        return self::resample($img, (int) (($w - $s) / 2), (int) (($h - $s) / 2), $s, $s, min($size, $s), min($size, $s));
    }

    private static function resample(GdImage $src, int $sx, int $sy, int $sw, int $sh, int $dw, int $dh): GdImage
    {
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        return $dst;
    }

    /** Simpan sebagai WebP; turunkan kualitas lalu dimensi sampai ≤ $maxBytes. */
    public static function saveWebp(GdImage $img, string $dest, int $maxBytes, int $quality = 82): int
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Server belum mendukung konversi WebP (ekstensi GD tanpa WebP).');
        }
        for ($round = 0; $round < 6; $round++) {
            for ($q = $quality; $q >= 45; $q -= 12) {
                ob_start();
                imagewebp($img, null, $q);
                $data = (string) ob_get_clean();
                if (strlen($data) <= $maxBytes) {
                    file_put_contents($dest, $data);

                    return strlen($data);
                }
            }
            $img = self::fit($img, (int) (max(imagesx($img), imagesy($img)) * 0.75));
        }
        throw new RuntimeException('Gambar tidak dapat dikompres di bawah batas ukuran.');
    }

    /** Kompres ulang gambar dalam format aslinya (dipakai untuk gambar di dalam dokumen Office). */
    public static function recompress(string $data, string $ext, int $max = 1600): ?string
    {
        $img = @imagecreatefromstring($data);
        if (! $img instanceof GdImage) {
            return null;
        }
        $img = self::fit($img, $max);
        ob_start();
        if ($ext === 'png') {
            imagesavealpha($img, true);
            imagepng($img, null, 9);
        } else {
            imagejpeg($img, null, 70);
        }
        $out = (string) ob_get_clean();

        return $out !== '' && strlen($out) < strlen($data) ? $out : null;
    }
}
