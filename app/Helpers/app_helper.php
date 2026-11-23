<?php

use CodeIgniter\HTTP\ResponseInterface;

if (! function_exists('app_version')) {
    function app_version(): string
    {
        return '0.2.3';
    }
}

if (! function_exists('id_months')) {
    function id_months(bool $short = false): array
    {
        return $short
            ? [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
            : [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }
}

if (! function_exists('day_names')) {
    /** ISO-8601: 1 = Senin … 7 = Minggu */
    function day_names(): array
    {
        return [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(?string $date, bool $long = false, bool $withDay = false): string
    {
        if (! $date) {
            return '—';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return esc($date);
        }
        $m   = id_months(! $long)[(int) date('n', $ts)];
        $out = date('j', $ts) . ' ' . $m . ' ' . date('Y', $ts);

        return $withDay ? day_names()[(int) date('N', $ts)] . ', ' . $out : $out;
    }
}

if (! function_exists('fmt_time')) {
    function fmt_time(?string $t): string
    {
        return $t ? substr($t, 0, 5) : '—';
    }
}

if (! function_exists('fmt_num')) {
    function fmt_num($v, int $dec = 2): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = number_format((float) $v, $dec, ',', '.');

        return $dec > 0 ? rtrim(rtrim($s, '0'), ',') : $s;
    }
}

if (! function_exists('fmt_score')) {
    /** Nilai kosong ditampilkan sebagai "Belum diinput" (bukan nol). */
    function fmt_score($v, string $rule = '2'): string
    {
        if ($v === null || $v === '') {
            return 'Belum diinput';
        }
        if ($rule === 'none') {
            return fmt_num($v, 4);
        }
        $d = max(0, (int) $rule);

        return number_format(round((float) $v, $d), $d, ',', '.');
    }
}

if (! function_exists('event_types')) {
    function event_types(): array
    {
        return ['libur' => 'Hari libur', 'kegiatan' => 'Kegiatan sekolah', 'ujian' => 'Asesmen/ujian', 'rapat' => 'Rapat', 'lainnya' => 'Lainnya'];
    }
}

if (! function_exists('event_color')) {
    /** Warna bawaan per jenis kegiatan kalender. */
    function event_type_colors(): array
    {
        return ['libur' => '#ef4444', 'kegiatan' => '#3b82f6', 'ujian' => '#f59e0b', 'rapat' => '#8b5cf6', 'lainnya' => '#14b8a6'];
    }

    /** Warna kegiatan: pilihan admin, atau bawaan sesuai jenis. */
    function event_color(array $e): string
    {
        $c = (string) ($e['color'] ?? '');

        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : (event_type_colors()[$e['event_type'] ?? ''] ?? '#64748b');
    }
}

if (! function_exists('student_statuses')) {
    function student_statuses(): array
    {
        return ['aktif' => 'Aktif', 'pindah' => 'Pindah', 'lulus' => 'Lulus', 'nonaktif' => 'Nonaktif'];
    }
}

if (! function_exists('phases')) {
    function phases(): array
    {
        return ['A' => 'Fase A', 'B' => 'Fase B', 'C' => 'Fase C', 'D' => 'Fase D', 'E' => 'Fase E', 'F' => 'Fase F'];
    }
}

if (! function_exists('nav_current')) {
    /** Kembalikan atribut aria-current bila path aktif diawali prefix. */
    function nav_current(string $prefix, bool $exact = false): string
    {
        $path = trim(service('request')->getUri()->getPath(), '/');
        $base = trim((string) parse_url(base_url(), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = trim(substr($path, strlen($base)), '/');
        }
        $query = null;
        if (str_contains($prefix, '?')) {
            [$prefix, $qs] = explode('?', $prefix, 2);
            parse_str($qs, $query);
        }
        $prefix = trim($prefix, '/');
        $hit    = $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, $prefix . '/'));
        if ($hit && $query) {
            foreach ($query as $k => $v) {
                if (strtoupper((string) service('request')->getGet($k)) !== strtoupper((string) $v)) {
                    $hit = false;
                }
            }
        }

        return $hit ? ' aria-current="page"' : '';
    }
}

if (! function_exists('options')) {
    /** @param list<array> $rows */
    function options(array $rows, string $key = 'id', string|callable $label = 'name'): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r[$key]] = is_string($label) ? $r[$label] : $label($r);
        }

        return $out;
    }
}

if (! function_exists('csv_response')) {
    /**
     * Respons CSV ramah Excel (UTF-8 BOM, pemisah titik koma).
     *
     * @param list<string>       $header
     * @param iterable<list<mixed>> $rows
     */
    function csv_response(string $filename, array $header, iterable $rows): ResponseInterface
    {
        $fh = fopen('php://temp', 'w+b');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ';', '"', '');
        foreach ($rows as $r) {
            // Cegah CSV/formula injection di spreadsheet.
            $r = array_map(static function ($v) {
                $v = (string) ($v ?? '');

                return preg_match('/^[=+\-@\t\r]/', $v) && ! is_numeric($v) ? "'" . $v : $v;
            }, $r);
            fputcsv($fh, $r, ';', '"', '');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '-', $filename);

        return service('response')
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($csv);
    }
}

if (! function_exists('icon')) {
    /** Ikon SVG inline (stroke), dekoratif secara default. */
    function icon(string $name, string $class = 'size-5'): string
    {
        $p = [
            'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
            'book'     => '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2Z"/><path d="M4 19.5A2 2 0 0 0 6 21h14"/>',
            'calendar' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
            'pen'      => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
            'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/>',
            'paperclip'=> '<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.5-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4l8-7.9"/>',
            'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
            'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'refresh'  => '<path d="M20 11a8 8 0 0 0-14.9-3.9L3 9"/><path d="M3 4v5h5"/><path d="M4 13a8 8 0 0 0 14.9 3.9L21 15"/><path d="M21 20v-5h-5"/>',
            'sidebar'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="m15 10-2 2 2 2"/>',
            'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
            'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/>',
            'monitor'  => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
            'upload'   => '<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>',
            'printer'  => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
            'edit'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/>',
            'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
            'archive'  => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'alert'    => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17.5v.01"/>',
            'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'pin'      => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'graduate' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/><path d="M22 10v6"/>',
            'target'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
            'layers'   => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'shield'   => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Z"/>',
            'database' => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
            'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
            'list'     => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'chevron-left'  => '<path d="m15 6-6 6 6 6"/>',
            'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
            'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
            'zap'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        ];

        return '<svg class="' . esc($class, 'attr') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($p[$name] ?? '') . '</svg>';
    }
}

if (! function_exists('badge')) {
    /** Lencana status; teks selalu ada sehingga status tidak dibedakan hanya dengan warna. */
    function badge(string $text, string $tone = 'neutral'): string
    {
        return '<span class="badge badge-' . esc($tone, 'attr') . '">' . esc($text) . '</span>';
    }
}

if (! function_exists('excerpt')) {
    function excerpt(?string $text, int $len = 80): string
    {
        return esc(mb_strimwidth((string) $text, 0, $len, '…'));
    }
}

if (! function_exists('brand_mark')) {
    /** Logo sekolah bila ada; jika belum, lencana "AG". */
    function brand_mark(?array $profile, string $size = 'size-11'): string
    {
        $url = \App\Libraries\SchoolLogo::url($profile);
        if ($url) {
            return '<img src="' . esc($url) . '" alt="Logo ' . esc($profile['school_name'] ?? 'sekolah', 'attr') . '" class="' . $size . ' shrink-0 rounded-xl bg-white object-contain p-0.5" width="44" height="44">';
        }

        return '<span class="grid ' . $size . ' shrink-0 place-items-center rounded-[1rem_1.4rem_1rem_1.6rem] bg-accent font-display text-lg font-extrabold text-accent-ink" aria-hidden="true">AG</span>';
    }
}

if (! function_exists('favicon_tags')) {
    /**
     * Tag favicon: memakai logo sekolah bila ada (URL berversi, sehingga langsung
     * berganti saat logo diganti), jika belum ada memakai ikon bawaan aplikasi.
     */
    function favicon_tags(?array $profile = null): string
    {
        $url = \App\Libraries\SchoolLogo::url($profile);
        if ($url) {
            $ext  = pathinfo((string) $profile['school_logo'], PATHINFO_EXTENSION);
            $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? 'image/png';

            return '<link rel="icon" href="' . esc($url) . '" type="' . $mime . '">' . "\n    "
                . '<link rel="apple-touch-icon" href="' . esc($url) . '">';
        }

        return '<link rel="icon" href="' . esc(base_url('assets/icon.svg')) . '" type="image/svg+xml">';
    }
}

if (! function_exists('safe_profile')) {
    /** Profil sekolah tanpa melempar galat (mis. saat database belum siap di wizard instalasi). */
    function safe_profile(): ?array
    {
        try {
            return (new \App\Models\SchoolProfileModel())->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (! function_exists('app_footer')) {
    function app_footer(): string
    {
        return '<p>Copyright © ' . date('Y') . ' <strong class="font-semibold text-ink">Manajemen Agenda Guru</strong>. Hak cipta dilindungi.</p>'
            . '<p class="mt-1">Create by @yhsyah · v' . esc(app_version()) . '</p>';
    }
}

if (! function_exists('greeting')) {
    function greeting(): string
    {
        $h = (int) date('G');

        return match (true) {
            $h < 11 => 'Selamat pagi',
            $h < 15 => 'Selamat siang',
            $h < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}

if (! function_exists('user_avatar')) {
    /** Foto profil pengguna, atau inisial nama bila belum ada foto. */
    function user_avatar(?array $user, string $size = 'size-8', string $extra = ''): string
    {
        if (! $user) {
            return '';
        }
        $url = \App\Libraries\ProfilePhoto::url($user);
        if ($url) {
            return '<img src="' . esc($url, 'attr') . '" alt="" class="' . $size . ' shrink-0 rounded-full object-cover ' . $extra . '" loading="lazy" decoding="async">';
        }
        // Belum ada foto: gambar bawaan sesuai jenis kelamin (guru laki-laki / perempuan).
        $g = strtoupper((string) ($user['gender'] ?? ''));
        if ($g === 'L' || $g === 'P') {
            return '<img src="' . esc(base_url('assets/img/avatar-guru-' . strtolower($g) . '.webp'), 'attr') . '" alt="" class="' . $size
                . ' shrink-0 rounded-full bg-surface-2 object-cover ' . $extra . '" loading="lazy" decoding="async">';
        }
        $words = array_slice(preg_split('/\s+/', trim((string) preg_replace('/[,.].*$/', '', (string) $user['name']))) ?: [], 0, 2);
        $ini   = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), $words)));

        return '<span class="grid ' . $size . ' shrink-0 place-items-center rounded-full bg-linear-to-br from-indigo-500 to-sky-500 ' . (preg_match('/text-(?:xs|sm|base|lg|[2-9]?xl)/', $extra) ? '' : 'text-xs ') . 'font-bold text-white ' . $extra . '" aria-hidden="true">' . esc($ini) . '</span>';
    }
}

if (! function_exists('roman_to_int')) {
    /** "VIII" → 8 (untuk mengurutkan jenjang); bukan angka Romawi → 0. */
    function roman_to_int(string $r): int
    {
        $map = ['M' => 1000, 'D' => 500, 'C' => 100, 'L' => 50, 'X' => 10, 'V' => 5, 'I' => 1];
        $r   = strtoupper(trim($r));
        if ($r === '' || preg_match('/[^MDCLXVI]/', $r)) {
            return 0;
        }
        $n = 0;
        for ($i = 0, $len = strlen($r); $i < $len; $i++) {
            $v = $map[$r[$i]];
            $n += ($i + 1 < $len && $map[$r[$i + 1]] > $v) ? -$v : $v;
        }

        return $n;
    }
}

if (! function_exists('int_to_roman')) {
    /** 8 → "VIII" (untuk nama kelas). */
    function int_to_roman(int $n): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $out = '';
        foreach ($map as $r => $v) {
            while ($n >= $v) {
                $out .= $r;
                $n -= $v;
            }
        }

        return $out;
    }
}

if (! function_exists('subject_color')) {
    /** Warna penanda tetap per mata pelajaran (garis kiri pada jadwal). */
    function subject_color(?string $code): string
    {
        static $map = [
            'MTK' => '#2563eb', 'IPA' => '#16a34a', 'IPS' => '#d97706', 'BIN' => '#db2777', 'BIG' => '#7c3aed', 'INF' => '#0891b2',
            'PAI' => '#059669', 'PAK' => '#9333ea', 'PP' => '#dc2626', 'PJOK' => '#ea580c', 'PKY' => '#a16207', 'SBD' => '#c026d3',
            'BK' => '#475569', 'MLK' => '#0d9488',
        ];
        $c = strtoupper(trim((string) $code));
        if (isset($map[$c])) {
            return $map[$c];
        }
        $pal = ['#0284c7', '#65a30d', '#be123c', '#4f46e5', '#b45309', '#0f766e', '#a21caf', '#334155'];

        return $pal[crc32($c) % count($pal)];
    }
}

if (! function_exists('subject_chip')) {
    /**
     * Kode mapel berwarna (warna sama dengan Beban JP, Struktur Jam, dan jadwal), opsional diikuti nama mapel.
     * Tanpa kode: memakai nama saja dengan warna dari nama.
     */
    function subject_chip(?string $code, ?string $name = null): string
    {
        $code = trim((string) $code);
        $key  = $code !== '' ? $code : (string) $name;
        $clr  = preg_match('/^#[0-9a-f]{6}$/i', $c = subject_color($key)) ? $c : '#64748b'; // hanya warna hex tervalidasi
        $chip = $code !== '' ? '<span class="mp-chip" style="--mp: ' . $clr . '">' . esc($code) . '</span>' : '';

        if ($name === null) {
            return $chip !== '' ? $chip : '<span class="text-muted">—</span>';
        }

        return '<span class="mp-with">' . ($chip !== '' ? $chip : '<span class="mp-dot" style="--mp: ' . $clr . '"></span>')
            . '<span class="mp-name">' . esc($name) . '</span></span>';
    }
}

