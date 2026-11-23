<?php

namespace App\Libraries;

/**
 * Pengaturan kop surat & cetak dokumen (disimpan sebagai JSON di school_profiles.kop_settings).
 * Bila belum pernah diatur, nilai diambil dari data sekolah agar kop lama tetap tampil sama.
 */
final class KopSettings
{
    public const FONTS = [
        'arial'     => ['Arial / Helvetica', 'Arial, Helvetica, "Liberation Sans", sans-serif'],
        'arialnar'  => ['Arial Narrow', '"Arial Narrow", "Liberation Sans Narrow", Arial, sans-serif'],
        'calibri'   => ['Calibri', 'Calibri, Carlito, "Segoe UI", sans-serif'],
        'segoe'     => ['Segoe UI', '"Segoe UI", "Noto Sans", Roboto, sans-serif'],
        'tahoma'    => ['Tahoma', 'Tahoma, "DejaVu Sans", sans-serif'],
        'verdana'   => ['Verdana', 'Verdana, "DejaVu Sans", sans-serif'],
        'trebuchet' => ['Trebuchet MS', '"Trebuchet MS", "Lucida Grande", sans-serif'],
        'century'   => ['Century Gothic', '"Century Gothic", "URW Gothic", "Avant Garde", sans-serif'],
        'times'     => ['Times New Roman', '"Times New Roman", Times, "Liberation Serif", serif'],
        'cambria'   => ['Cambria', 'Cambria, Caladea, Georgia, serif'],
        'georgia'   => ['Georgia', 'Georgia, "DejaVu Serif", serif'],
        'bookman'   => ['Bookman Old Style', '"Bookman Old Style", Bookman, "URW Bookman", Georgia, serif'],
        'garamond'  => ['Garamond', 'Garamond, "EB Garamond", "Times New Roman", serif'],
        'palatino'  => ['Book Antiqua / Palatino', '"Book Antiqua", "Palatino Linotype", Palatino, "URW Palladio L", serif'],
        'bodoni'    => ['Bodoni MT', '"Bodoni MT", Didot, "Times New Roman", serif'],
    ];
    public const CASES   = ['as_typed' => 'Sesuai ketikan', 'upper' => 'HURUF KAPITAL'];
    public const WEIGHTS = ['bold' => 'Tebal', 'semibold' => 'Agak tebal', 'normal' => 'Normal'];
    public const RULES   = ['double' => 'Garis ganda', 'thick_thin' => 'Tebal-tipis', 'single' => 'Garis tunggal', 'thick' => 'Garis tebal', 'none' => 'Tanpa garis'];
    public const PAPERS  = [
        'A4'     => ['A4 (21 × 29,7 cm)', '210mm 297mm'],
        'F4'     => ['F4/Folio (21,5 × 33 cm)', '215mm 330mm'],
        'Letter' => ['Letter (21,6 × 27,9 cm)', '216mm 279mm'],
        'Legal'  => ['Legal (21,6 × 35,6 cm)', '216mm 356mm'],
    ];
    public const DATE_MODES = ['auto' => 'Otomatis (tanggal cetak)', 'manual' => 'Tanggal tetap (isi manual)'];

    /** Nilai bawaan murni (dipakai tombol "Kembalikan bawaan" untuk gaya kop). */
    public const STYLE_DEFAULTS = [
        'font' => 'arial', 'font_addr' => 'arial', 'case' => 'as_typed', 'weight' => 'bold', 'rule' => 'double',
        'size1' => 12, 'size2' => 12, 'size3' => 14, 'size_addr' => 10,
    ];
    public const PRINT_DEFAULTS = [
        'paper' => 'A4', 'date_mode' => 'auto', 'sign_date' => '',
        'margin_top' => 10, 'margin_right' => 13, 'margin_bottom' => 17, 'margin_left' => 13,
        'show_cover_photo' => 1, 'show_logo' => 0,
    ];

    /** @var array<string, array> */
    private static array $memo = [];

    /** Pengaturan khusus satu cetakan (dari panel "Pengaturan Cetak" di halaman cetak). */
    private static array $override = [];

    /**
     * Baca pilihan cetak sekali pakai dari query string (?print[paper]=F4&print[logo]=1 …).
     * Hanya kunci yang sesuai Pengaturan Cetak Dokumen yang diterima.
     */
    public static function useRequest(): void
    {
        $in = service('request')->getGet('print');
        if (! is_array($in)) {
            self::$override = [];

            return;
        }
        $o = [];
        if (isset($in['paper'], self::PAPERS[$in['paper']])) {
            $o['paper'] = (string) $in['paper'];
        }
        if (isset($in['date_mode'], self::DATE_MODES[$in['date_mode']])) {
            $o['date_mode'] = (string) $in['date_mode'];
            if ($o['date_mode'] === 'manual' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['sign_date'] ?? ''))) {
                $o['sign_date'] = (string) $in['sign_date'];
            } elseif ($o['date_mode'] === 'manual') {
                $o['date_mode'] = 'auto';
            }
        }
        foreach (['logo' => 'show_logo', 'cover' => 'show_cover_photo'] as $q => $key) {
            if (isset($in[$q])) {
                $o[$key] = $in[$q] === '1' ? 1 : 0;
            }
        }
        self::$override = $o;
    }

    public static function resetOverride(): void
    {
        self::$override = [];
    }

    /** Apakah cetakan ini memakai pengaturan berbeda dari bawaan sekolah. */
    public static function hasOverride(?array $profile): bool
    {
        $base = self::clean((json_decode((string) ($profile['kop_settings'] ?? ''), true) ?: []) + self::textDefaults($profile) + self::STYLE_DEFAULTS + self::PRINT_DEFAULTS);
        foreach (self::$override as $k => $v) {
            if (($base[$k] ?? null) !== $v) {
                return true;
            }
        }

        return false;
    }

    /** Isi teks bawaan dari data sekolah (untuk sekolah yang belum mengatur kop). */
    public static function textDefaults(?array $p): array
    {
        $p = $p ?? [];
        $contact = implode(', ', array_filter([
            ! empty($p['npsn']) ? 'NPSN: ' . $p['npsn'] : null,
            ! empty($p['school_phone']) ? 'Telepon : ' . $p['school_phone'] : null,
            ! empty($p['school_email']) ? 'E-mail : ' . $p['school_email'] : null,
            ! empty($p['school_website']) ? 'Website : ' . $p['school_website'] : null,
        ]));

        return [
            'line1' => mb_strtoupper((string) ($p['header_line'] ?? '')),
            'line2' => '',
            'line3' => mb_strtoupper((string) ($p['school_name'] ?? '')),
            'addr1' => trim(preg_replace('/\s+/', ' ', (string) ($p['school_address'] ?? '')) ?? ''),
            'addr2' => $contact,
            'city'  => mb_strtoupper((string) ($p['school_city'] ?? '')),
            'postal' => '',
        ];
    }

    public static function get(?array $profile): array
    {
        $key = md5(json_encode([$profile ?? [], self::$override]));
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        $saved = json_decode((string) ($profile['kop_settings'] ?? ''), true);
        $saved = is_array($saved) ? $saved : [];

        return self::$memo[$key] = self::clean(self::$override + $saved + self::textDefaults($profile) + self::STYLE_DEFAULTS + self::PRINT_DEFAULTS);
    }

    /** Bersihkan & batasi nilai (dipakai saat menyimpan dan membaca). */
    public static function clean(array $in): array
    {
        $txt = static fn ($k, $len) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')) ?? ''), 0, $len);
        $pick = static fn ($k, array $opts, $def) => isset($opts[$in[$k] ?? null]) ? (string) $in[$k] : $def;
        $int = static fn ($k, $min, $max, $def) => is_numeric($in[$k] ?? null) ? max($min, min($max, (int) $in[$k])) : $def;
        $date = (string) ($in['sign_date'] ?? '');

        return [
            'line1' => $txt('line1', 150), 'line2' => $txt('line2', 150), 'line3' => $txt('line3', 150),
            'addr1' => $txt('addr1', 250), 'addr2' => $txt('addr2', 250), 'city' => $txt('city', 100), 'postal' => $txt('postal', 60),
            'font'   => $pick('font', self::FONTS, 'arial'),
            'font_addr' => $pick('font_addr', self::FONTS, isset(self::FONTS[$in['font'] ?? '']) ? (string) $in['font'] : 'arial'),
            'case'   => $pick('case', self::CASES, 'as_typed'),
            'weight' => $pick('weight', self::WEIGHTS, 'bold'),
            'rule'   => $pick('rule', self::RULES, 'double'),
            'size1'  => $int('size1', 8, 28, 12), 'size2' => $int('size2', 8, 28, 12), 'size3' => $int('size3', 8, 32, 14), 'size_addr' => $int('size_addr', 7, 20, 10),
            'paper'  => $pick('paper', self::PAPERS, 'A4'),
            'date_mode' => $pick('date_mode', self::DATE_MODES, 'auto'),
            'sign_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
            'margin_top' => $int('margin_top', 0, 50, 10), 'margin_right' => $int('margin_right', 0, 50, 13),
            'margin_bottom' => $int('margin_bottom', 0, 50, 17), 'margin_left' => $int('margin_left', 0, 50, 13),
            'show_cover_photo' => array_key_exists('show_cover_photo', $in) ? (empty($in['show_cover_photo']) ? 0 : 1) : 1,
            'show_logo' => empty($in['show_logo']) ? 0 : 1,
        ];
    }

    /** Aturan @page sesuai ukuran kertas & margin (disisipkan di <head> halaman). */
    public static function pageCss(array $k): string
    {
        $size = self::PAPERS[$k['paper']][1];
        $m    = sprintf('%dmm %dmm %dmm %dmm', $k['margin_top'], $k['margin_right'], $k['margin_bottom'], $k['margin_left']);
        [$w, $h] = explode(' ', $size);

        return '@media print{@page{size:' . $size . ';margin:' . $m . '}@page landscape{size:' . $h . ' ' . $w . ';margin:' . $m . '}'
            . '@page cover{size:' . $size . ';margin:0}.cover-sheet{--cv-ph:' . $h . '}}';
    }

    /** Tanggal pada blok tanda tangan (YYYY-MM-DD). */
    public static function signDate(array $k): string
    {
        return $k['date_mode'] === 'manual' && $k['sign_date'] !== '' ? $k['sign_date'] : date('Y-m-d');
    }

    public static function fontStack(array $k, string $key = 'font'): string
    {
        return self::FONTS[$k[$key] ?? 'arial'][1] ?? self::FONTS['arial'][1];
    }
}
