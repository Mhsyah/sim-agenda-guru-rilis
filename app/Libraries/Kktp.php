<?php

namespace App\Libraries;

/**
 * KKTP (Kriteria Ketercapaian Tujuan Pembelajaran) berbentuk interval nilai.
 * Disimpan ringkas sebagai batas atas tiga tingkat pertama, mis. "40,65,85" →
 * 0–40 Perlu bimbingan · 41–65 Cukup · 66–85 Baik · 86–100 Sangat baik.
 */
final class Kktp
{
    public const DEFAULT = '40,65,85';
    public const LEVELS  = ['Perlu bimbingan', 'Cukup', 'Baik', 'Sangat baik'];
    public const TONES   = ['danger', 'warning', 'info', 'success'];

    /** Normalisasi masukan ("0-40;41-65;66-85;86-100", "40,65,85", "40 65 85") → "40,65,85" atau null bila tidak valid. */
    public static function normalize(?string $in): ?string
    {
        $in = trim((string) $in);
        if ($in === '') {
            return null;
        }
        if (preg_match_all('/(\d{1,3})\s*[-–]\s*(\d{1,3})/u', $in, $m) && count($m[0]) >= 3) {
            $b = array_map('intval', array_slice($m[2], 0, 3));
        } else {
            preg_match_all('/\d{1,3}/', $in, $m);
            $b = array_map('intval', array_slice($m[0], 0, 3));
        }
        if (count($b) !== 3 || ! ($b[0] > 0 && $b[0] < $b[1] && $b[1] < $b[2] && $b[2] < 100)) {
            return null;
        }

        return implode(',', $b);
    }

    /** @return list<array{min:int, max:int, label:string, tone:string}> */
    public static function bands(?string $stored): array
    {
        $b   = array_map('intval', explode(',', self::normalize($stored) ?? self::DEFAULT));
        $min = 0;
        $out = [];
        foreach ([...$b, 100] as $i => $max) {
            $out[] = ['min' => $min, 'max' => $max, 'label' => self::LEVELS[$i], 'tone' => self::TONES[$i]];
            $min   = $max + 1;
        }

        return $out;
    }

    /** Teks ringkas, mis. "0–40 Perlu bimbingan · 41–65 Cukup · …". */
    public static function describe(?string $stored): string
    {
        return implode(' · ', array_map(static fn ($x) => $x['min'] . '–' . $x['max'] . ' ' . $x['label'], self::bands($stored)));
    }

    /** Tingkat ketercapaian untuk nilai 0–100. */
    public static function level(?float $score, ?string $stored): ?array
    {
        if ($score === null) {
            return null;
        }
        foreach (self::bands($stored) as $band) {
            if ($score <= $band['max'] + 0.4999) {
                return $band;
            }
        }

        return self::bands($stored)[3];
    }
}
