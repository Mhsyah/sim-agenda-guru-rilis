<?php

namespace App\Libraries;

use App\Models\AgendaModel;
use App\Models\AttendanceModel;

/**
 * Daftar hadir yang tersinkron dengan agenda/jurnal.
 *
 * Saat agenda baru dibuat, status siswa diisi otomatis dari agenda lain pada
 * kelas & tanggal yang sama (mis. siswa yang sakit sejak jam pertama), sehingga
 * guru cukup mengubah yang berbeda. Selain itu default "Hadir".
 */
final class Attendance
{
    public const STATUSES = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
    public const TONES    = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];

    /**
     * @return list<array{id: int, nis: ?string, full_name: string, status: string, notes: ?string, source: string}>
     *         source: saved (tersimpan di agenda ini), synced (dari agenda lain hari yang sama), default
     */
    public static function roster(int $classId, string $date, ?int $agendaId = null): array
    {
        $students = RecapService::enrolledStudents($classId);
        $saved    = $agendaId ? self::byStudent($agendaId) : [];
        $synced   = [];

        if ($saved === [] && $date !== '') {
            // Agenda lain pada kelas & tanggal yang sama (paling akhir diubah menang).
            $others = (new AgendaModel())->select('id')
                ->where('class_group_id', $classId)->where('agenda_date', $date)
                ->where('status !=', 'batal')
                ->orderBy('updated_at', 'ASC')->orderBy('id', 'ASC')->findAll();
            foreach ($others as $o) {
                if ((int) $o['id'] === (int) $agendaId) {
                    continue;
                }
                foreach (self::byStudent((int) $o['id']) as $sid => $row) {
                    $synced[$sid] = $row;
                }
            }
        }

        $out = [];
        foreach ($students as $s) {
            $sid = (int) $s['id'];
            $src = isset($saved[$sid]) ? 'saved' : (isset($synced[$sid]) ? 'synced' : 'default');
            $row = $saved[$sid] ?? $synced[$sid] ?? null;
            $out[] = [
                'id'        => $sid,
                'nis'       => $s['nis'],
                'full_name' => $s['full_name'],
                'status'    => $row['status'] ?? 'H',
                'notes'     => $row['notes'] ?? null,
                'source'    => $src,
            ];
        }

        return $out;
    }

    /** @return array<int, array{status: string, notes: ?string}> */
    public static function byStudent(int $agendaId): array
    {
        $out = [];
        foreach ((new AttendanceModel())->where('teaching_agenda_id', $agendaId)->findAll() as $r) {
            $out[(int) $r['student_id']] = ['status' => $r['status'], 'notes' => $r['notes']];
        }

        return $out;
    }

    /**
     * Simpan daftar hadir untuk satu agenda. Hanya siswa aktif di kelas agenda yang disimpan;
     * siswa yang tidak lagi di kelas dihapus dari daftar.
     *
     * @param array<int|string, string> $statuses student_id => H|S|I|A
     * @param array<int|string, string> $notes
     *
     * @return array{H: int, S: int, I: int, A: int}
     */
    public static function save(int $agendaId, int $classId, array $statuses, array $notes = []): array
    {
        $enrolled = array_map('intval', array_column(RecapService::enrolledStudents($classId), 'id'));
        $existing = [];
        foreach ((new AttendanceModel())->where('teaching_agenda_id', $agendaId)->findAll() as $r) {
            $existing[(int) $r['student_id']] = $r;
        }
        $count = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

        foreach ($enrolled as $sid) {
            $st = strtoupper((string) ($statuses[$sid] ?? 'H'));
            $st = isset(self::STATUSES[$st]) ? $st : 'H';
            $nt = trim((string) ($notes[$sid] ?? ''));
            $nt = $nt === '' ? null : mb_substr($nt, 0, 200);
            $count[$st]++;

            if (isset($existing[$sid])) {
                if ($existing[$sid]['status'] !== $st || ($existing[$sid]['notes'] ?? null) !== $nt) {
                    (new AttendanceModel())->update($existing[$sid]['id'], ['status' => $st, 'notes' => $nt]);
                }
                unset($existing[$sid]);
            } else {
                (new AttendanceModel())->insert(['teaching_agenda_id' => $agendaId, 'student_id' => $sid, 'status' => $st, 'notes' => $nt]);
            }
        }
        if ($existing) {
            (new AttendanceModel())->whereIn('id', array_column($existing, 'id'))->delete();
        }

        return $count;
    }

    public static function clear(int $agendaId): void
    {
        (new AttendanceModel())->where('teaching_agenda_id', $agendaId)->delete();
    }

    /** Ringkasan teks, mis. "2 Sakit, 1 Alpa". */
    public static function summaryText(array $count): string
    {
        $parts = [];
        foreach (['S', 'I', 'A'] as $k) {
            if (! empty($count[$k])) {
                $parts[] = $count[$k] . ' ' . self::STATUSES[$k];
            }
        }

        return $parts ? implode(', ', $parts) : 'semua hadir';
    }

    /**
     * Siswa tidak hadir per agenda: agenda_id => [[name, status], ...].
     *
     * @param list<int> $agendaIds
     */
    public static function absentees(array $agendaIds): array
    {
        if ($agendaIds === []) {
            return [];
        }
        $rows = (new AttendanceModel())
            ->select('attendances.teaching_agenda_id, attendances.status, s.full_name')
            ->join('students s', 's.id = attendances.student_id')
            ->whereIn('attendances.teaching_agenda_id', $agendaIds)
            ->where('attendances.status !=', 'H')
            ->orderBy('s.full_name', 'ASC')->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['teaching_agenda_id']][] = [$r['full_name'], $r['status']];
        }

        return $out;
    }
}
