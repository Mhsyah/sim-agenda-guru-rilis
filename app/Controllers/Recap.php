<?php

namespace App\Controllers;

use App\Libraries\GradeCalculator;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Libraries\RecapService;

class Recap extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $classes  = Lookup::teachingClasses(); // hanya kelas yang diajar
        $subjects = Lookup::teachingSubjects();
        $classId  = (int) ($this->request->getGet('class_group_id') ?: array_key_first($classes));
        $subjectId = (int) ($this->request->getGet('subject_id') ?: array_key_first($subjects));

        $recap = null;
        if ($classId && $subjectId && Period::semesterId()) {
            $recap = RecapService::build(Period::yearId(), Period::semesterId(), $classId, $subjectId);
        }

        // Tab "Ketercapaian KKTP": siswa × TP semester aktif
        $view = $this->request->getGet('view') === 'kktp' ? 'kktp' : 'nilai';
        $kk   = $view === 'kktp' && $classId && $subjectId && Period::semesterId()
            ? \App\Libraries\KktpRecap::build((int) Period::yearId(), (int) Period::semesterId(), $classId, $subjectId, (int) \App\Libraries\Auth::id()) : null;

        if ($recap && $this->request->getGet('format') === 'csv') {
            return $this->csv($recap, $classes[$classId] ?? '', $subjects[$subjectId] ?? '');
        }

        return $this->render('recap/index', [
            'title'     => 'Rekap Nilai',
            'classes'   => $classes,
            'subjects'  => $subjects,
            'classId'   => $classId,
            'subjectId' => $subjectId,
            'recap'     => $recap,
            'view'      => $view,
            'kk'        => $kk,
        ]);
    }

    private function csv(array $recap, string $class, string $subject)
    {
        $header = ['No', 'NIS', 'Nama'];
        foreach ($recap['codes'] as $c) {
            $header[] = 'Rata-rata ' . $c;
        }
        array_push($header, 'Nilai Akhir', 'Status', 'Komponen belum lengkap');
        $rows = [];
        foreach ($recap['rows'] as $i => $r) {
            $line = [$i + 1, $r['student']['nis'], $r['student']['full_name']];
            foreach ($recap['codes'] as $c) {
                $v      = $r['calc']['components'][$c]['value'] ?? null;
                $line[] = $v === null ? 'Belum diinput' : fmt_score($v, $recap['rule']);
            }
            $line[] = $r['calc']['value'] === null ? '' : fmt_score($r['calc']['value'], $recap['rule']);
            $line[] = match ($r['calc']['status']) {
                GradeCalculator::STATUS_FINAL => 'Final', GradeCalculator::STATUS_PROVISION => 'Sementara', default => 'Belum ada nilai'
            };
            $line[] = implode(', ', $r['calc']['missing']);
            $rows[] = $line;
        }

        return csv_response('rekap-' . $class . '-' . $subject . '-' . date('Ymd') . '.csv', $header, $rows);
    }
}
