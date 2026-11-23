<?php

namespace App\Libraries;

use RuntimeException;
use ZipArchive;

/**
 * Lampiran materi agenda (opsional).
 * - Gambar (JPG/PNG/WebP/GIF/BMP) selalu dikonversi ke WebP ≤ 1 MB.
 * - DOCX/XLSX/PPTX > 1 MB dikompres otomatis (gambar di dalamnya diperkecil).
 * - PDF > 1 MB dikompres dengan Ghostscript bila tersedia di server.
 * File disimpan di writable/uploads/materials (tidak dapat diakses langsung dari web).
 */
final class MaterialFile
{
    public const MAX_BYTES    = 1048576;      // ukuran akhir maks. 1 MB
    public const MAX_UPLOAD   = 20 * 1048576; // ukuran unggahan mentah maks. 20 MB (sebelum dikompres)
    public const ACCEPT       = '.jpg,.jpeg,.png,.webp,.gif,.bmp,.pdf,.docx,.xlsx,.pptx';
    private const IMAGES      = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
    private const OFFICE      = ['docx' => 'word/media/', 'xlsx' => 'xl/media/', 'pptx' => 'ppt/media/'];
    public const MIMES        = [
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
        }

        return $dir;
    }

    public static function path(?string $name): ?string
    {
        if (! $name || ! preg_match('/^m-[a-f0-9]{20}\.(webp|pdf|docx|xlsx|pptx)$/', $name)) {
            return null;
        }
        $p = self::dir() . $name;

        return is_file($p) ? $p : null;
    }

    public static function ext(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function isImage(?string $name): bool
    {
        return $name !== null && self::ext($name) === 'webp';
    }

    /**
     * Olah file sementara lalu simpan.
     *
     * @return array{material_file: string, material_name: string, material_size: int, note: string}
     */
    public static function store(string $tmp, string $clientName): array
    {
        $size = (int) @filesize($tmp);
        if ($size <= 0) {
            throw new RuntimeException('File materi kosong atau gagal diunggah.');
        }
        if ($size > self::MAX_UPLOAD) {
            throw new RuntimeException('File materi terlalu besar (maks. 20 MB sebelum dikompres).');
        }
        $ext  = self::ext($clientName);
        $base = trim(preg_replace('/[^\pL\pN ._()-]+/u', '', pathinfo($clientName, PATHINFO_FILENAME)) ?? '') ?: 'materi';
        $base = mb_substr($base, 0, 150);
        $id   = 'm-' . bin2hex(random_bytes(10));
        $note = '';

        if (in_array($ext, self::IMAGES, true)) {
            if (! ImageTool::isImage($tmp)) {
                throw new RuntimeException('File gambar tidak valid.');
            }
            $name = $id . '.webp';
            $out  = ImageTool::saveWebp(ImageTool::fit(ImageTool::load($tmp), 2000), self::dir() . $name, self::MAX_BYTES);
            $note = 'Gambar dikonversi ke WebP (' . self::human($size) . ' → ' . self::human($out) . ').';

            return ['material_file' => $name, 'material_name' => $base . '.webp', 'material_size' => $out, 'note' => $note];
        }

        if ($ext === 'pdf') {
            if ((string) file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
                throw new RuntimeException('File PDF tidak valid.');
            }
        } elseif (isset(self::OFFICE[$ext])) {
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true || $zip->locateName('[Content_Types].xml') === false) {
                throw new RuntimeException('File ' . strtoupper($ext) . ' tidak valid atau rusak.');
            }
            $zip->close();
        } else {
            throw new RuntimeException('Jenis file tidak didukung. Gunakan JPG, PNG, WebP, PDF, DOCX, XLSX, atau PPTX.');
        }

        $name = $id . '.' . $ext;
        $dest = self::dir() . $name;
        if (! copy($tmp, $dest)) {
            throw new RuntimeException('Gagal menyimpan file materi.');
        }
        if ($size > self::MAX_BYTES) {
            $ok = $ext === 'pdf' ? self::shrinkPdf($dest) : self::shrinkOffice($dest, self::OFFICE[$ext]);
            clearstatcache(true, $dest);
            $new = (int) filesize($dest);
            if (! $ok || $new > self::MAX_BYTES) {
                @unlink($dest);
                throw new RuntimeException('File ' . strtoupper($ext) . ' berukuran ' . self::human($size) . ' dan tidak bisa dikompres sampai 1 MB'
                    . ($ext === 'pdf' && ! self::ghostscript() ? ' (kompresor PDF tidak tersedia di server)' : '')
                    . '. Perkecil dulu (mis. kurangi/kompres gambar di dalamnya), lalu unggah ulang.');
            }
            $note = 'File dikompres otomatis (' . self::human($size) . ' → ' . self::human($new) . ').';
            $size = $new;
        }

        return ['material_file' => $name, 'material_name' => $base . '.' . $ext, 'material_size' => $size, 'note' => $note];
    }

    /** Perkecil gambar di dalam DOCX/XLSX/PPTX (nama & format tetap, dokumen tetap utuh). */
    private static function shrinkOffice(string $path, string $mediaDir): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $changed = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            $e     = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (! str_starts_with($entry, $mediaDir) || ! in_array($e, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }
            $data = $zip->getFromIndex($i);
            if ($data !== false && strlen($data) > 60000 && ($small = ImageTool::recompress($data, $e === 'png' ? 'png' : 'jpg')) !== null) {
                $zip->addFromString($entry, $small);
                $changed = true;
            }
        }

        return $zip->close() && $changed;
    }

    private static function ghostscript(): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }
        foreach (['gs', 'gswin64c', 'gswin32c'] as $bin) {
            $out = [];
            @exec($bin . ' --version 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'), $out, $code);
            if ($code === 0 && $out) {
                return $bin;
            }
        }

        return null;
    }

    private static function shrinkPdf(string $path): bool
    {
        if (! ($gs = self::ghostscript())) {
            return false;
        }
        $tmp = $path . '.gs.pdf';
        foreach (['/ebook', '/screen'] as $preset) {
            @exec($gs . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=' . $preset . ' -dNOPAUSE -dQUIET -dBATCH -dSAFER'
                . ' -sOutputFile=' . escapeshellarg($tmp) . ' ' . escapeshellarg($path), $o, $code);
            if ($code === 0 && is_file($tmp) && filesize($tmp) > 0 && filesize($tmp) < filesize($path)) {
                rename($tmp, $path);
                clearstatcache(true, $path);
                if (filesize($path) <= self::MAX_BYTES) {
                    return true;
                }
            }
            @unlink($tmp);
        }

        return false;
    }

    public static function delete(?string $name): void
    {
        if ($p = self::path($name)) {
            @unlink($p);
        }
    }

    public static function human(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }
}
