<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\SemesterModel;

/** Periode kerja aktif (tahun ajaran + semester) yang dipilih guru, disimpan di sesi. */
final class Period
{
    private static ?array $cache = null;

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $years = new AcademicYearModel();

        $year = null;
        if ($yid = session()->get('period_year')) {
            $year = $years->find($yid);
        }
        $year ??= (new AcademicYearModel())->where('is_active', 1)->orderBy('start_date', 'DESC')->first()
            ?? (new AcademicYearModel())->orderBy('start_date', 'DESC')->first();

        $semester = null;
        if ($year) {
            $yid = $year['id'];
            if ($sid = session()->get('period_semester')) {
                $semester = (new SemesterModel())->where('academic_year_id', $yid)->where('id', $sid)->first();
            }
            $today = date('Y-m-d');
            $semester ??= (new SemesterModel())->where('academic_year_id', $yid)->where('is_active', 1)->first()
                ?? (new SemesterModel())->where('academic_year_id', $yid)->where('start_date <=', $today)->where('end_date >=', $today)->first()
                ?? (new SemesterModel())->where('academic_year_id', $yid)->orderBy('start_date', 'ASC')->first();
        }

        return self::$cache = ['year' => $year, 'semester' => $semester];
    }

    public static function yearId(): ?int
    {
        $y = self::get()['year'];

        return $y ? (int) $y['id'] : null;
    }

    public static function semesterId(): ?int
    {
        $s = self::get()['semester'];

        return $s ? (int) $s['id'] : null;
    }

    public static function set(?int $yearId, ?int $semesterId): void
    {
        session()->set('period_year', $yearId);
        session()->set('period_semester', $semesterId);
        self::$cache = null;
        Lookup::reset();
    }

    public static function reset(): void
    {
        self::$cache = null;
        Lookup::reset();
    }

    /** Semester dalam tahun aktif yang memuat tanggal tertentu. */
    public static function semesterForDate(string $date): ?int
    {
        $yid = self::yearId();
        if (! $yid) {
            return null;
        }
        $row = (new SemesterModel())->where('academic_year_id', $yid)
            ->where('start_date <=', $date)->where('end_date >=', $date)->first();

        return $row ? (int) $row['id'] : self::semesterId();
    }
}
