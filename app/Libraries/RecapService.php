<?php

namespace App\Libraries;

use App\Models\AssessmentModel;
use App\Models\EnrollmentModel;
use App\Models\GradingConfigItemModel;
use App\Models\GradingConfigModel;
use App\Models\ScoreModel;

/** Mengumpulkan data dari database lalu menghitung rekap via GradeCalculator. */
final class RecapService
{
    /** Konfigurasi aktif: spesifik kelas lebih dulu, lalu konfigurasi "semua kelas". */
    public static function resolveConfig(int $yearId, int $semesterId, int $classId, int $subjectId, ?int $userId = null): ?array
    {
        $userId ??= Auth::id();
        $base = static fn () => (new GradingConfigModel())->where('user_id', $userId)->where('academic_year_id', $yearId)
            ->where('semester_id', $semesterId)->where('subject_id', $subjectId)->where('is_active', 1);

        return $base()->where('class_group_id', $classId)->first()
            ?? $base()->where('class_group_id', null)->first();
    }

    /** @return array<string, float> kode => bobot, hanya kategori aktif */
    public static function weightsOf(?array $config): array
    {
        if (! $config) {
            return [];
        }
        $types = array_column(Lookup::assessmentTypeRows(), 'code', 'id');
        $out   = [];
        foreach ((new GradingConfigItemModel())->where('grading_config_id', $config['id'])->findAll() as $it) {
            if ((int) $it['is_enabled'] && isset($types[$it['assessment_type_id']])) {
                $out[$types[$it['assessment_type_id']]] = (float) $it['weight_percent'];
            }
        }

        return $out;
    }

    public static function enrolledStudents(int $classId): array
    {
        return (new EnrollmentModel())
            ->select('s.id, s.nis, s.full_name, s.gender')
            ->join('students s', 's.id = student_enrollments.student_id')
            ->where('student_enrollments.class_group_id', $classId)
            ->where('student_enrollments.status', 'aktif')
            ->where('s.deleted_at', null)
            ->orderBy('s.full_name', 'ASC')
            ->findAll();
    }

    public static function build(int $yearId, int $semesterId, int $classId, int $subjectId, ?int $userId = null): array
    {
        $userId ??= Auth::id();
        $types   = Lookup::assessmentTypeRows();
        $typeMap = array_column($types, 'code', 'id');
        $names   = array_column($types, 'name', 'code');
        $config  = self::resolveConfig($yearId, $semesterId, $classId, $subjectId, $userId);
        $weights = self::weightsOf($config);
        $method  = $config['aggregation_method'] ?? 'mean';
        $rule    = $config['rounding_rule'] ?? '2';

        $assessments = (new AssessmentModel())->where('user_id', $userId)
            ->where('academic_year_id', $yearId)->where('semester_id', $semesterId)
            ->where('class_group_id', $classId)->where('subject_id', $subjectId)
            ->orderBy('assessment_date', 'ASC')->orderBy('id', 'ASC')->findAll();
        $byType = [];
        foreach ($assessments as $a) {
            $byType[$typeMap[$a['assessment_type_id']] ?? '?'][] = $a;
        }

        $students   = self::enrolledStudents($classId);
        $scores     = [];
        $missingMap = [];
        if ($assessments && $students) {
            $rows = (new ScoreModel())->whereIn('assessment_id', array_column($assessments, 'id'))->findAll();
            foreach ($rows as $s) {
                $scores[$s['student_id']][$s['assessment_id']] = $s['score'];
                if ((int) ($s['is_missing'] ?? 0) === 1) {
                    $missingMap[$s['student_id']][$s['assessment_id']] = true;
                }
            }
        }

        $codes = $weights ? array_keys($weights) : array_column($types, 'code');
        $out   = [];
        foreach ($students as $st) {
            $components = [];
            foreach ($codes as $code) {
                foreach ($byType[$code] ?? [] as $a) {
                    // Tidak mengumpulkan: aturan "abaikan" = tidak dihitung sama sekali; aturan "nol" = nilai 0 (tersimpan 0)
                    if (isset($missingMap[$st['id']][$a['id']]) && ($a['missing_policy'] ?? 'nol') === 'abaikan') {
                        continue;
                    }
                    $components[$code][] = ['score' => $scores[$st['id']][$a['id']] ?? null, 'max' => $a['max_score'], 'date' => $a['assessment_date']];
                }
            }
            $calc = $weights
                ? GradeCalculator::compute($weights, $components, $method)
                : ['value' => null, 'status' => GradeCalculator::STATUS_EMPTY, 'components' => array_map(
                    static fn ($items) => ['value' => GradeCalculator::aggregate($items, $method), 'graded' => 0, 'total' => count($items), 'complete' => false],
                    $components
                ), 'missing' => [], 'weights_valid' => false, 'weight_sum' => 0];
            $out[] = ['student' => $st, 'calc' => $calc, 'scores' => $scores[$st['id']] ?? [], 'missing' => $missingMap[$st['id']] ?? []];
        }

        $check = GradeCalculator::validateWeights($weights);

        return [
            'config'      => $config,
            'weights'     => $weights,
            'weightCheck' => $check,
            'codes'       => $codes,
            'names'       => $names,
            'method'      => $method,
            'rule'        => $rule,
            'assessments' => $assessments,
            'byType'      => $byType,
            'rows'        => $out,
            'formula'     => GradeCalculator::formula($weights, $names),
        ];
    }
}
