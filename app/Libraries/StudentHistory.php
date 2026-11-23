<?php

namespace App\Libraries;

/** Status & riwayat siswa: dari log audit, penempatan kelas, dan daftar hadir agenda. */
final class StudentHistory
{
    /** @return list<array{title: string, actor: string, at: string, note: ?string, tone: string}> */
    public static function timeline(array $student): array
    {
        $db       = db_connect();
        $statuses = student_statuses();
        $out      = [];
        $rows = $db->table('audit_logs al')->select('al.action, al.old_values, al.new_values, al.created_at, al.user_id, u.name AS actor')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->where('al.entity_type', 'student')->where('al.entity_id', (int) $student['id'])
            ->orderBy('al.created_at', 'ASC')->orderBy('al.id', 'ASC')->limit(300)->get()->getResultArray();

        $classes = Lookup::classes();
        foreach ($rows as $r) {
            $old   = json_decode((string) $r['old_values'], true) ?: [];
            $new   = json_decode((string) $r['new_values'], true) ?: [];
            $actor = $r['actor'] ?: 'Sistem';
            $at    = AccountHistory::local($r['created_at']);
            switch ($r['action']) {
                case 'create':
                    $out[] = ['title' => 'Data siswa dibuat', 'actor' => $actor, 'at' => $at, 'tone' => 'info',
                        'note' => 'Status: ' . ($statuses[$new['status'] ?? 'aktif'] ?? 'Aktif')];
                    break;
                case 'update':
                    if (isset($new['status'], $old['status']) && $old['status'] !== $new['status']) {
                        $out[] = ['title' => ($statuses[$old['status']] ?? $old['status']) . ' → ' . ($statuses[$new['status']] ?? $new['status']),
                            'actor' => $actor, 'at' => $at, 'note' => null, 'tone' => $new['status'] === 'aktif' ? 'success' : 'warning'];
                    }
                    break;
                case 'enroll':
                    $out[] = ['title' => 'Ditempatkan di kelas ' . ($classes[$new['class_group_id'] ?? 0] ?? '—'), 'actor' => $actor, 'at' => $at, 'note' => null, 'tone' => 'info'];
                    break;
                case 'archive':
                case 'delete':
                    $out[] = ['title' => 'Dihapus dari daftar (arsip)', 'actor' => $actor, 'at' => $at, 'note' => 'Riwayat nilai & kehadiran tetap tersimpan', 'tone' => 'warning'];
                    break;
                case 'import':
                    $out[] = ['title' => 'Diimpor dari CSV', 'actor' => $actor, 'at' => $at, 'note' => null, 'tone' => 'info'];
                    break;
            }
        }
        if (! $out && ! empty($student['created_at'])) {
            $out[] = ['title' => 'Data siswa dibuat', 'actor' => 'Sistem', 'at' => fmt_date(substr($student['created_at'], 0, 10), true), 'note' => null, 'tone' => 'info'];
        }

        return $out;
    }

    /** Penempatan kelas per tahun pelajaran. */
    public static function placements(int $studentId): array
    {
        return db_connect()->table('student_enrollments en')->select('ay.name AS year_name, cg.name AS class_name, en.status')
            ->join('academic_years ay', 'ay.id = en.academic_year_id')->join('class_groups cg', 'cg.id = en.class_group_id')
            ->where('en.student_id', $studentId)->orderBy('ay.start_date', 'DESC')->get()->getResultArray();
    }

    /** Rekap H/S/I/A per semester (dari daftar hadir agenda guru). */
    public static function attendance(int $studentId): array
    {
        $rows = db_connect()->table('attendances a')->select('sm.name AS sem, ay.name AS year_name, a.status, COUNT(*) AS n')
            ->join('teaching_agendas ag', 'ag.id = a.teaching_agenda_id')
            ->join('semesters sm', 'sm.id = ag.semester_id')
            ->join('academic_years ay', 'ay.id = sm.academic_year_id')
            ->where('a.student_id', $studentId)->where('ag.deleted_at', null)
            ->groupBy('ay.name, sm.name, a.status')->orderBy('ay.name', 'DESC')->orderBy('sm.name', 'ASC')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $k = 'T.P. ' . $r['year_name'] . ' · ' . $r['sem'];
            $out[$k] ??= ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
            $out[$k][$r['status']] = (int) $r['n'];
        }

        return $out;
    }

    /** Ketidakhadiran terbaru. */
    public static function recentAbsences(int $studentId, int $limit = 15): array
    {
        return db_connect()->table('attendances a')->select('ag.agenda_date, ag.lesson_hours, s.name AS subject_name, a.status, a.notes, u.name AS teacher')
            ->join('teaching_agendas ag', 'ag.id = a.teaching_agenda_id')
            ->join('subjects s', 's.id = ag.subject_id')
            ->join('users u', 'u.id = ag.user_id', 'left')
            ->where('a.student_id', $studentId)->where('a.status !=', 'H')->where('ag.deleted_at', null)
            ->orderBy('ag.agenda_date', 'DESC')->limit($limit)->get()->getResultArray();
    }
}
