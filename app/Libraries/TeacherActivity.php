<?php

namespace App\Libraries;

/**
 * Progres kegiatan guru untuk kartu "Guru & Pengguna":
 * jumlah agenda per minggu (8 minggu terakhir) + persentase agenda terisi dibanding pertemuan terjadwal semester aktif.
 */
class TeacherActivity
{
    public const WEEKS = 8;

    /**
     * @param list<int> $userIds
     *
     * @return array<int, array{weeks: list<array{label: string, n: int}>, filled: int, expected: int, pct: int|null}>
     */
    public static function forUsers(array $userIds, ?string $today = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (! $userIds) {
            return [];
        }
        $db    = db_connect();
        $today ??= date('Y-m-d');

        // Minggu (Senin) dari 7 minggu lalu s.d. minggu ini
        $monday = strtotime('monday this week', strtotime($today));
        $starts = [];
        for ($i = self::WEEKS - 1; $i >= 0; $i--) {
            $starts[] = date('Y-m-d', strtotime('-' . $i . ' week', $monday));
        }
        $out = [];
        foreach ($userIds as $u) {
            $out[$u] = ['weeks' => array_map(static fn ($s) => ['label' => date('d/m', strtotime($s)), 'n' => 0], $starts), 'filled' => 0, 'expected' => 0, 'pct' => null];
        }
        foreach ($db->table('teaching_agendas')->select('user_id, agenda_date')->whereIn('user_id', $userIds)
            ->where('agenda_date >=', $starts[0])->where('agenda_date <=', $today)->where('deleted_at', null)->get()->getResultArray() as $r) {
            $w = (int) floor((strtotime($r['agenda_date']) - strtotime($starts[0])) / (7 * 86400));
            if (isset($out[(int) $r['user_id']]['weeks'][$w])) {
                $out[(int) $r['user_id']]['weeks'][$w]['n']++;
            }
        }

        // Semester aktif: agenda terisi vs pertemuan terjadwal s.d. hari ini (hari libur kalender tidak dihitung)
        $sem = Period::get()['semester'] ?? null;
        $yid = Period::yearId();
        if (! $sem || ! $yid || $sem['start_date'] > $today) {
            return $out;
        }
        $end      = min($today, $sem['end_date']);
        $holidays = [];
        foreach ($db->table('school_calendar_events')->where('event_type', 'libur')->where('end_date >=', $sem['start_date'])->where('start_date <=', $end)->get()->getResultArray() as $h) {
            for ($t = strtotime($h['start_date']); $t <= strtotime($h['end_date']); $t += 86400) {
                $holidays[date('Y-m-d', $t)] = true;
            }
        }
        $days = [];
        for ($t = strtotime($sem['start_date']); $t <= strtotime($end); $t += 86400) {
            if (! isset($holidays[date('Y-m-d', $t)])) {
                $days[(int) date('N', $t)] = ($days[(int) date('N', $t)] ?? 0) + 1;
            }
        }
        $seen = [];
        foreach ($db->table('teaching_schedules')->select('user_id, day_of_week, class_group_id, subject_id')->where('academic_year_id', $yid)
            ->whereIn('user_id', $userIds)->get()->getResultArray() as $r) {
            $k = $r['user_id'] . '|' . $r['day_of_week'] . '|' . $r['class_group_id'] . '|' . $r['subject_id'];
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $out[(int) $r['user_id']]['expected'] += $days[(int) $r['day_of_week']] ?? 0;
            }
        }
        foreach ($db->table('teaching_agendas')->select('user_id, COUNT(*) AS n')->whereIn('user_id', $userIds)->where('semester_id', (int) $sem['id'])
            ->where('agenda_date <=', $end)->where('deleted_at', null)->groupBy('user_id')->get()->getResultArray() as $r) {
            $out[(int) $r['user_id']]['filled'] = (int) $r['n'];
        }
        foreach ($out as &$o) {
            $o['pct'] = $o['expected'] ? (int) min(100, round($o['filled'] / $o['expected'] * 100)) : null;
        }
        unset($o);

        return $out;
    }
}
