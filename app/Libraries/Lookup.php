<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\AssessmentTypeModel;
use App\Models\ClassGroupModel;
use App\Models\LearningObjectiveModel;
use App\Models\LearningOutcomeModel;
use App\Models\SemesterModel;
use App\Models\SubjectModel;

/** Sumber data pilihan (dropdown) yang dipakai lintas modul. */
final class Lookup
{
    /** Cache hasil per request (dropdown yang sama sering dipanggil berkali-kali dalam satu halaman). */
    private static array $memo = [];

    public static function reset(): void
    {
        self::$memo = [];
    }

    /**
     * Mapel yang diajarkan guru yang sedang login (diatur admin di Guru & Pengguna atau guru di Profil Saya).
     * Admin, atau guru yang belum punya pembagian mapel, tetap melihat semua mapel agar tidak terkunci.
     */
    public static function teachingSubjects(?int $userId = null): array
    {
        $all = self::subjects();
        if ($userId === null) {
            if (! Auth::isTeacher()) {
                return $all;
            }
            $userId = (int) Auth::id();
        }
        $key = __FUNCTION__ . '|' . $userId;
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $ids  = array_column(db_connect()->table('user_subjects')->select('subject_id')->where('user_id', $userId)->get()->getResultArray(), 'subject_id');
        $mine = array_intersect_key($all, array_flip(array_map('intval', $ids)));

        return self::$memo[$key] = $mine ?: $all;
    }

    /**
     * Id mapel yang diajarkan guru yang login (untuk menyaring data laporan),
     * atau null bila tidak perlu disaring (admin / guru tanpa pembagian mapel).
     *
     * @return list<int>|null
     */
    public static function teachingSubjectIds(): ?array
    {
        if (! Auth::isTeacher()) {
            return null;
        }
        $key = __FUNCTION__ . '|' . (int) Auth::id();
        if (! array_key_exists($key, self::$memo)) {
            $ids = array_map('intval', array_column(db_connect()->table('user_subjects')->select('subject_id')->where('user_id', (int) Auth::id())->get()->getResultArray(), 'subject_id'));
            self::$memo[$key] = $ids ?: null; // belum ada pembagian mapel → tidak disaring
        }

        return self::$memo[$key];
    }

    /** Opsi mapel guru + mapel yang sudah tersimpan pada data lama (agar data lama tetap bisa diubah). */
    public static function teachingSubjectsWith(?int $keepId): array
    {
        $opts = self::teachingSubjects();
        if ($keepId && ! isset($opts[$keepId]) && ($name = self::subjects(false)[$keepId] ?? null)) {
            $opts[$keepId] = $name;
        }

        return $opts;
    }

    public static function subjects(bool $activeOnly = true): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $m = new SubjectModel();
        if ($activeOnly) {
            $m->where('is_active', 1);
        }

        return self::$memo[$key] = options($m->orderBy('name', 'ASC')->findAll(), 'id', static fn ($r) => $r['name'] . ($r['code'] ? ' (' . $r['code'] . ')' : ''));
    }

    /**
     * Kelas yang diajarkan guru (dari jadwal mengajarnya pada T.P. aktif).
     * Admin, atau guru yang belum punya jadwal: semua kelas. $keepId = kelas pada data lama agar tetap bisa diubah.
     */
    /**
     * ID kelas yang diajar guru pada T.P. aktif: gabungan pembagian kelas per mapel (Profil → Mapel & kelas yang diajarkan)
     * dan jadwal mengajarnya. null = tidak disaring (admin / guru yang belum punya keduanya).
     */
    public static function teachingClassIds(): ?array
    {
        if (! Auth::isTeacher()) {
            return null;
        }
        $key = __FUNCTION__ . '|' . (int) Auth::id() . '|' . (int) Period::yearId();
        if (! array_key_exists($key, self::$memo)) {
            $db   = db_connect();
            $uid  = (int) Auth::id();
            $yid  = (int) Period::yearId();
            $sch  = array_column($db->table('teaching_schedules')->distinct()->select('class_group_id')
                ->where('user_id', $uid)->where('academic_year_id', $yid)->get()->getResultArray(), 'class_group_id');
            $map  = array_column($db->table('user_teaching t')->distinct()->select('t.class_group_id')
                ->join('class_groups c', 'c.id = t.class_group_id')->where('t.user_id', $uid)->where('c.academic_year_id', $yid)
                ->get()->getResultArray(), 'class_group_id');
            $ids  = array_values(array_unique(array_map('intval', array_merge($sch, $map))));
            self::$memo[$key] = $ids ?: null;
        }

        return self::$memo[$key];
    }

    public static function teachingClasses(?int $keepId = null): array
    {
        $all  = self::classes();
        $ids  = self::teachingClassIds();
        $opts = $ids === null ? $all : array_intersect_key($all, array_flip($ids)); // belum ada jadwal → tidak disaring
        if ($keepId && ! isset($opts[$keepId]) && ($name = self::classes(null, false)[$keepId] ?? null)) {
            $opts[$keepId] = $name;
        }

        return $opts;
    }

    public static function classes(?int $yearId = null, bool $activeOnly = true): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $m = (new ClassGroupModel())->where('academic_year_id', $yearId ?? Period::yearId());
        if ($activeOnly) {
            $m->where('is_active', 1);
        }

        return self::$memo[$key] = options($m->orderBy('grade_level', 'ASC')->orderBy('name', 'ASC')->findAll());
    }

    public static function years(): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = options((new AcademicYearModel())->orderBy('start_date', 'DESC')->findAll());
    }

    public static function semesters(?int $yearId = null): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = options((new SemesterModel())->where('academic_year_id', $yearId ?? Period::yearId())->orderBy('start_date', 'ASC')->findAll());
    }

    public static function assessmentTypes(): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = options((new AssessmentTypeModel())->where('is_active', 1)->orderBy('sort_order', 'ASC')->findAll(), 'id', static fn ($r) => $r['code'] . ' — ' . $r['name']);
    }

    /** @return list<array> */
    public static function assessmentTypeRows(): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = (new AssessmentTypeModel())->where('is_active', 1)->orderBy('sort_order', 'ASC')->findAll();
    }

    /** TP dikelompokkan per mapel: subject_id => [label, items]. */
    public static function objectivesGrouped(): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = self::grouped(new LearningObjectiveModel(), static fn ($r) => trim(($r['code'] ? $r['code'] . ' · ' : '') . mb_strimwidth($r['description'], 0, 90, '…')));
    }

    /**
     * TP untuk formulir agenda & asesmen: semester aktif + TP "keduanya"; $keepId = TP pada data lama tetap tampil.
     */
    public static function objectivesForSemester(?int $keepId = null): array
    {
        $sem = (int) Period::semesterId();
        $key = __FUNCTION__ . '|' . $sem . '|' . (int) $keepId;
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $m = new LearningObjectiveModel();
        if ($sem) {
            $m->groupStart()->where('semester_id', null)->orWhere('semester_id', $sem);
            if ($keepId) {
                $m->orWhere('id', $keepId);
            }
            $m->groupEnd();
        }

        return self::$memo[$key] = self::grouped($m, static fn ($r) => trim(($r['code'] ? $r['code'] . ' · ' : '') . mb_strimwidth($r['description'], 0, 90, '…')));
    }

    /** CP dikelompokkan per mapel. */
    public static function outcomesGrouped(): array
    {
        $key = __FUNCTION__ . '|' . json_encode(func_get_args());
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        return self::$memo[$key] = self::grouped(new LearningOutcomeModel(), static fn ($r) => trim(($r['code'] ? $r['code'] . ' · ' : '') . $r['element_name'] . ' — ' . mb_strimwidth($r['description'], 0, 70, '…')));
    }

    private static function grouped($model, callable $label): array
    {
        $subjects = self::subjects(false);
        $rows     = $model->where('is_active', 1)->orderBy('subject_id', 'ASC')->orderBy('code', 'ASC')->findAll();
        $out      = [];
        foreach ($rows as $r) {
            $sid = $r['subject_id'];
            $out[$sid] ??= ['label' => $subjects[$sid] ?? 'Mapel #' . $sid, 'items' => []];
            $out[$sid]['items'][$r['id']] = $label($r);
        }

        return $out;
    }
}
