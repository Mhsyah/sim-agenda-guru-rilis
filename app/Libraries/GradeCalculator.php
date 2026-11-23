<?php

namespace App\Libraries;

/**
 * Perhitungan rekap nilai (murni, tanpa database) sesuai aturan penilaian PRD §10.
 *
 * - Nilai dinormalisasi ke skala 0–100 terhadap nilai maksimum asesmen.
 * - Nilai kosong (null) = "Belum diinput", tidak dihitung sebagai nol.
 * - Final hanya bila bobot kategori aktif = 100% dan semua komponen lengkap.
 * - Selain itu dihitung "Sementara" dari komponen yang tersedia (bobot dinormalisasi).
 */
final class GradeCalculator
{
    public const METHODS = [
        'mean'    => 'Rata-rata aritmetika (default)',
        'latest'  => 'Nilai asesmen terbaru',
        'highest' => 'Nilai tertinggi',
    ];

    public const ROUNDING = [
        'none' => 'Tanpa pembulatan',
        '0'    => 'Bilangan bulat',
        '1'    => '1 angka desimal',
        '2'    => '2 angka desimal',
    ];

    public const STATUS_FINAL     = 'final';
    public const STATUS_PROVISION = 'sementara';
    public const STATUS_EMPTY     = 'kosong';

    private const EPS = 0.0001;

    /**
     * @param array<string, float|int|string> $weights kode kategori => bobot persen (hanya kategori aktif)
     *
     * @return array{sum: float, valid: bool}
     */
    public static function validateWeights(array $weights): array
    {
        $sum = 0.0;
        foreach ($weights as $w) {
            $w = (float) $w;
            if ($w < 0) {
                return ['sum' => $sum, 'valid' => false];
            }
            $sum += $w;
        }

        return ['sum' => round($sum, 4), 'valid' => count($weights) > 0 && abs($sum - 100) < self::EPS];
    }

    /**
     * Agregasi satu kategori untuk satu siswa.
     *
     * @param list<array{score: float|int|string|null, max?: float|int|string, date?: string}> $items
     */
    public static function aggregate(array $items, string $method = 'mean'): ?float
    {
        $graded = [];
        foreach ($items as $it) {
            if ($it['score'] === null || $it['score'] === '') {
                continue;
            }
            $max = (float) ($it['max'] ?? 100);
            if ($max <= 0) {
                continue;
            }
            $graded[] = ['v' => (float) $it['score'] / $max * 100, 'date' => (string) ($it['date'] ?? '')];
        }
        if ($graded === []) {
            return null;
        }

        switch ($method) {
            case 'highest':
                return max(array_column($graded, 'v'));

            case 'latest':
                usort($graded, static fn ($a, $b) => strcmp($a['date'], $b['date']));

                return end($graded)['v'];

            default:
                return array_sum(array_column($graded, 'v')) / count($graded);
        }
    }

    /**
     * @param array<string, float|int|string>                         $weights    kode => bobot (kategori aktif)
     * @param array<string, list<array{score: mixed, max?: mixed, date?: string}>> $components kode => daftar asesmen + nilai siswa
     *
     * @return array{value: ?float, status: string, weights_valid: bool, weight_sum: float,
     *               components: array<string, array{value: ?float, graded: int, total: int, complete: bool}>,
     *               missing: list<string>}
     */
    public static function compute(array $weights, array $components, string $method = 'mean'): array
    {
        $check = self::validateWeights($weights);
        $out   = [];
        $missing = [];
        $allComplete = true;
        $num = 0.0;
        $den = 0.0;

        foreach ($weights as $code => $w) {
            $items  = $components[$code] ?? [];
            $total  = count($items);
            $graded = count(array_filter($items, static fn ($i) => $i['score'] !== null && $i['score'] !== ''));
            $value  = self::aggregate($items, $method);
            $complete = $total > 0 && $graded === $total;

            if (! $complete) {
                $allComplete = false;
                $missing[]   = (string) $code;
            }
            if ($value !== null) {
                $num += $value * (float) $w;
                $den += (float) $w;
            }
            $out[$code] = ['value' => $value, 'graded' => $graded, 'total' => $total, 'complete' => $complete];
        }

        if ($den <= 0) {
            $value  = null;
            $status = self::STATUS_EMPTY;
        } elseif ($check['valid'] && $allComplete) {
            $value  = $num / 100;
            $status = self::STATUS_FINAL;
        } else {
            $value  = $num / $den;
            $status = self::STATUS_PROVISION;
        }

        return [
            'value'         => $value,
            'status'        => $status,
            'weights_valid' => $check['valid'],
            'weight_sum'    => $check['sum'],
            'components'    => $out,
            'missing'       => $missing,
        ];
    }

    public static function round(?float $value, string $rule = '2'): ?float
    {
        if ($value === null || $rule === 'none') {
            return $value;
        }

        return round($value, max(0, (int) $rule));
    }

    /** Teks formula untuk ditampilkan kepada guru. */
    public static function formula(array $weights, array $names = []): string
    {
        $parts = [];
        foreach ($weights as $code => $w) {
            $parts[] = rtrim(rtrim(number_format((float) $w, 2, ',', ''), '0'), ',') . '% × ' . ($names[$code] ?? $code);
        }

        return 'Nilai akhir = ' . ($parts === [] ? '—' : implode(' + ', $parts));
    }
}
