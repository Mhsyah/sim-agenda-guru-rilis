<?php

namespace App\Libraries;

/** Jadwal piket guru: per tahun pelajaran, sesi (pagi/siang) dan hari. */
final class DutyRoster
{
    public const SHIFTS = ['pagi' => 'Pagi', 'siang' => 'Siang'];

    /** @return array<string, array<int, list<array{id: int, name: string}>>> [sesi][hari] => guru */
    public static function all(?int $yearId): array
    {
        $out = [];
        if (! $yearId) {
            return $out;
        }
        $rows = db_connect()->table('duty_rosters d')->select('d.shift, d.day_of_week, u.id, u.name')
            ->join('users u', 'u.id = d.user_id')
            ->where('d.academic_year_id', $yearId)
            ->orderBy('d.shift', 'ASC')->orderBy('d.day_of_week', 'ASC')->orderBy('d.sort', 'ASC')->orderBy('d.id', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $out[$r['shift']][(int) $r['day_of_week']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }

        return $out;
    }

    /** Hari piket seorang guru. @return list<array{shift: string, day: int}> */
    public static function forUser(int $userId, ?int $yearId): array
    {
        if (! $yearId) {
            return [];
        }

        return array_map(static fn ($r) => ['shift' => $r['shift'], 'day' => (int) $r['day_of_week']], db_connect()->table('duty_rosters')
            ->select('shift, day_of_week')->where('academic_year_id', $yearId)->where('user_id', $userId)
            ->orderBy('day_of_week', 'ASC')->orderBy('shift', 'ASC')->get()->getResultArray());
    }

    /**
     * Simpan seluruh piket satu tahun pelajaran (mengganti yang lama).
     *
     * @param array<string, array<int, list<int|string>>> $map [sesi][hari] => [user_id, …]
     */
    public static function save(int $yearId, array $map): int
    {
        $db   = db_connect();
        $rows = [];
        foreach (self::SHIFTS as $shift => $_) {
            foreach (range(1, 6) as $day) {
                $seen = [];
                foreach (array_values((array) ($map[$shift][$day] ?? [])) as $i => $uid) {
                    $uid = (int) $uid;
                    if ($uid > 0 && ! isset($seen[$uid])) {
                        $seen[$uid] = true;
                        $rows[]     = ['academic_year_id' => $yearId, 'shift' => $shift, 'day_of_week' => $day, 'user_id' => $uid, 'sort' => count($seen)];
                    }
                }
            }
        }
        $db->transStart();
        $db->table('duty_rosters')->where('academic_year_id', $yearId)->delete();
        if ($rows) {
            $db->table('duty_rosters')->insertBatch($rows);
        }
        $db->transComplete();

        return count($rows);
    }
}
