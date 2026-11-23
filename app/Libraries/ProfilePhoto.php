<?php

namespace App\Libraries;

use RuntimeException;

/** Foto profil: dipotong persegi 400 px, disimpan WebP ≤ 200 KB di writable/uploads/avatars. */
final class ProfilePhoto
{
    public const MAX_UPLOAD = 15 * 1048576; // foto kamera HP bisa besar

    public static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
        }

        return $dir;
    }

    public static function path(?string $name): ?string
    {
        if (! $name || ! preg_match('/^u\d+-[a-f0-9]{12}\.webp$/', $name)) {
            return null;
        }
        $p = self::dir() . $name;

        return is_file($p) ? $p : null;
    }

    /** URL foto (dengan penanda versi) atau null bila belum ada. */
    public static function url(?array $user): ?string
    {
        $name = $user['photo'] ?? null;

        return $user && self::path($name) ? site_url('avatar/' . (int) $user['id']) . '?v=' . substr((string) $name, -17, 8) : null;
    }

    public static function store(string $tmp, int $userId): string
    {
        $size = (int) @filesize($tmp);
        if ($size <= 0 || $size > self::MAX_UPLOAD) {
            throw new RuntimeException('Ukuran foto maksimal 15 MB.');
        }
        if (! ImageTool::isImage($tmp)) {
            throw new RuntimeException('Foto harus berupa gambar JPG, PNG, atau WebP.');
        }
        $name = 'u' . $userId . '-' . bin2hex(random_bytes(6)) . '.webp';
        ImageTool::saveWebp(ImageTool::square(ImageTool::load($tmp), 400), self::dir() . $name, 200 * 1024, 85);

        return $name;
    }

    public static function delete(?string $name): void
    {
        if ($p = self::path($name)) {
            @unlink($p);
        }
    }
}
