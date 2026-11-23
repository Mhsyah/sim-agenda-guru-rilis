<?php

namespace App\Libraries;

use App\Controllers\Promotions;

/**
 * Pemeriksaan kapasitas rombel & kesiapan tahun pelajaran baru:
 * - jumlah peserta didik per rombel maksimal 36 (ketentuan Kemendikdasmen terbaru untuk SMP);
 * - kelas akhir T.P. sebelumnya yang belum diluluskan.
 */
final class ClassCapacity
{
    public const MAX = 36;

    /**
     * Rombel pada T.P. yang melebihi batas.
     *
     * @return list<array{id:int, name:string, n:int}>
     */
    public static function over(?int $yearId = null): array
    {
        $yearId ??= Period::yearId();
        if (! $yearId) {
            return [];
        }
        $db   = db_connect();
        $rows = $db->table('student_enrollments e')->select('c.id, c.name, COUNT(*) AS n')
            ->join('class_groups c', 'c.id = e.class_group_id')
            ->join('students s', 's.id = e.student_id')
            ->where('e.academic_year_id', $yearId)->where('e.status', 'aktif')
            ->where('s.status', 'aktif')->where('s.deleted_at', null)->where('c.deleted_at', null)
            ->groupBy('c.id, c.name')->having('COUNT(*) >', self::MAX)
            ->orderBy('c.name', 'ASC')->get()->getResultArray();

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'n' => (int) $r['n']], $rows);
    }

    /**
     * Kelas akhir pada T.P. sebelum T.P. aktif yang siswanya masih berstatus aktif (belum diluluskan).
     *
     * @return array{year:string, count:int}|null
     */
    public static function pendingGraduation(): ?array
    {
        $active = Period::get()['year'];
        if (! $active) {
            return null;
        }
        $prev = db_connect()->table('academic_years')->select('id, name')
            ->where('start_date <', $active['start_date'])->orderBy('start_date', 'DESC')->limit(1)->get()->getRowArray();
        if (! $prev) {
            return null;
        }
        $n = 0;
        foreach (Promotions::plan((int) $prev['id'], null)['classes'] as $c) {
            if ($c['final']) {
                foreach ($c['students'] as $s) {
                    $n += (int) ($s['status'] === 'aktif');
                }
            }
        }

        return $n ? ['year' => $prev['name'], 'count' => $n] : null;
    }

    /** Peringatan siap tampil (HTML) untuk halaman impor siswa / data siswa. */
    public static function notices(?int $yearId = null): array
    {
        $out = [];
        if ($over = self::over($yearId)) {
            $list  = implode(', ', array_map(static fn ($c) => $c['name'] . ' (' . $c['n'] . ')', $over));
            $out[] = ['warning', 'Rombel melebihi ' . self::MAX . ' peserta didik: ' . $list . '. Sesuai ketentuan Kemendikdasmen terbaru, satu rombel SMP maksimal ' . self::MAX . ' siswa — pertimbangkan memecah rombel.', site_url('classes'), 'Kelas/Rombel'];
        }
        if ($pg = self::pendingGraduation()) {
            $out[] = ['warning', $pg['count'] . ' siswa kelas akhir T.P. ' . $pg['year'] . ' belum diluluskan. Proses kelulusan dulu sebelum menempatkan peserta didik baru.', site_url('promotions'), 'Kelulusan'];
        }

        return $out;
    }
}
