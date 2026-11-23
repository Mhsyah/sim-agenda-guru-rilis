<?php

namespace App\Libraries;

/**
 * Blanko sampul Buku Agenda Guru (gambar latar + isian data guru otomatis).
 * - Admin: blanko default sekolah (school_profiles.cover_blank).
 * - Guru : blanko milik sendiri (users.cover_blank); bila kosong memakai default sekolah, lalu desain bawaan.
 * Opsi: fields (tampilkan isian), y (posisi isian dari atas, %), box (kotak latar putih di belakang isian).
 */
final class CoverBlank
{
    public const DEFAULTS = ['fields' => 1, 'y' => 58, 'box' => 1];

    public static function opts(?string $json): array
    {
        $o = json_decode((string) $json, true);
        $o = is_array($o) ? $o : [];

        return [
            'fields' => (int) (bool) ($o['fields'] ?? self::DEFAULTS['fields']),
            'y'      => max(5, min(90, (int) ($o['y'] ?? self::DEFAULTS['y']))),
            'box'    => (int) (bool) ($o['box'] ?? self::DEFAULTS['box']),
        ];
    }

    public static function encode(array $in): string
    {
        return json_encode(self::opts(json_encode($in)));
    }

    /** Simpan gambar blanko (JPG/PNG/WebP, maks. 15 MB) → WebP potret, mengembalikan nama file. */
    public static function store(string $tmp): string
    {
        if (! ImageTool::isImage($tmp) || (int) @filesize($tmp) > 15 * 1048576) {
            throw new \RuntimeException('Blanko sampul harus gambar JPG/PNG/WebP, maks. 15 MB.');
        }
        $img = ImageTool::load($tmp);
        $name = 'blanko-' . bin2hex(random_bytes(8)) . '.webp';
        ImageTool::saveWebp(ImageTool::fit($img, 3000), SchoolLogo::dir() . $name, 1400 * 1024);

        return $name;
    }

    public static function delete(?string $name): void
    {
        if ($p = self::path($name)) {
            @unlink($p);
        }
    }

    public static function path(?string $name): ?string
    {
        if (! $name || ! preg_match('/^blanko-[a-f0-9]{16}\.webp$/', $name)) {
            return null;
        }
        $p = SchoolLogo::dir() . $name;

        return is_file($p) ? $p : null;
    }

    public static function url(?string $name): ?string
    {
        return self::path($name) ? site_url('cover-blank/' . $name) : null;
    }

    /**
     * Blanko yang berlaku untuk pengguna: milik guru → default sekolah → null (desain bawaan).
     *
     * @return array{url: string, opts: array, source: string}|null
     */
    public static function resolve(?array $user, ?array $profile): ?array
    {
        if ($user && ($user['role'] ?? '') === 'teacher' && ($u = self::url($user['cover_blank'] ?? null))) {
            return ['url' => $u, 'opts' => self::opts($user['cover_blank_opts'] ?? null), 'source' => 'guru'];
        }
        if ($u = self::url($profile['cover_blank'] ?? null)) {
            return ['url' => $u, 'opts' => self::opts($profile['cover_blank_opts'] ?? null), 'source' => 'sekolah'];
        }

        return null;
    }
}
