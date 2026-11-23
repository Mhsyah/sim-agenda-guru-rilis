<?php

namespace App\Libraries;

/**
 * Rekap ketercapaian KKTP per semester: siswa × TP yang diukur asesmen (kelas, mapel, semester aktif).
 * Nilai per TP = rata-rata nilai asesmen yang mengukur TP itu (dinormalisasi 0–100);
 * "tidak kumpul" mengikuti aturan asesmen (dihitung 0 atau diabaikan). Tingkat = interval KKTP TP.
 */
final class KktpRecap
{
    /**
     * @return array{tps: list<array>, rows: list<array>, summary: array<int, array<string,int>>}
     */
    public static function build(int $yearId, int $semesterId, int $classId, int $subjectId, int $userId): array
    {
        $db = db_connect();
        $assess = $db->table('assessments a')->select('a.id, a.max_score, a.missing_policy, a.learning_objective_id, lo.code, lo.description, lo.kktp_intervals, lo.atp_order')
            ->join('learning_objectives lo', 'lo.id = a.learning_objective_id')
            ->where('a.user_id', $userId)->where('a.academic_year_id', $yearId)->where('a.semester_id', $semesterId)
            ->where('a.class_group_id', $classId)->where('a.subject_id', $subjectId)->where('a.deleted_at', null)
            ->where('a.learning_objective_id IS NOT NULL', null, false)->get()->getResultArray();
        $tps = [];
        foreach ($assess as $a) {
            $tid = (int) $a['learning_objective_id'];
            $tps[$tid] ??= ['id' => $tid, 'code' => (string) $a['code'], 'description' => (string) $a['description'], 'intervals' => $a['kktp_intervals'],
                'atp' => $a['atp_order'], 'assessments' => []];
            $tps[$tid]['assessments'][(int) $a['id']] = $a;
        }
        uasort($tps, static fn ($x, $y) => [(int) ($x['atp'] ?? 9999), $x['code']] <=> [(int) ($y['atp'] ?? 9999), $y['code']]);

        $scores = [];
        if ($assess) {
            foreach ($db->table('assessment_scores')->select('assessment_id, student_id, score, is_missing')
                ->whereIn('assessment_id', array_column($assess, 'id'))->get()->getResultArray() as $s) {
                $scores[(int) $s['assessment_id']][(int) $s['student_id']] = $s;
            }
        }
        $students = RecapService::enrolledStudents($classId);
        $rows     = [];
        $summary  = [];
        foreach ($students as $st) {
            $sid   = (int) $st['id'];
            $cells = [];
            foreach ($tps as $tid => $tp) {
                $vals = [];
                foreach ($tp['assessments'] as $aid => $a) {
                    $sc = $scores[$aid][$sid] ?? null;
                    if (! $sc) {
                        continue;
                    }
                    if ((int) $sc['is_missing'] === 1) {
                        if (($a['missing_policy'] ?? 'nol') === 'abaikan') {
                            continue;
                        }
                        $vals[] = 0.0;

                        continue;
                    }
                    if ($sc['score'] !== null && (float) $a['max_score'] > 0) {
                        $vals[] = (float) $sc['score'] / (float) $a['max_score'] * 100;
                    }
                }
                $avg   = $vals ? array_sum($vals) / count($vals) : null;
                $level = Kktp::level($avg, $tp['intervals']);
                $cells[$tid] = ['value' => $avg, 'level' => $level];
                if ($level) {
                    $summary[$tid][$level['label']] = ($summary[$tid][$level['label']] ?? 0) + 1;
                }
            }
            $rows[] = ['student' => $st, 'cells' => $cells];
        }

        return ['tps' => array_values($tps), 'rows' => $rows, 'summary' => $summary];
    }
}
