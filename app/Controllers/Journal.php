<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\AgendaModel;
use App\Models\AttendanceModel;
use App\Models\PrincipalReviewModel;
use App\Models\SemesterModel;

/**
 * Buku "Jurnal Agenda Guru" siap cetak, mengikuti format buku agenda sekolah:
 * 1) Sampul + petunjuk + Kesan-kesan Kepala Sekolah, 2) Jadwal Mengajar, 3) Agenda Guru per kelas & mapel.
 */
class Journal extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $classes  = Lookup::teachingClasses(); // hanya kelas yang diajar (guru)
        $subjects = Lookup::teachingSubjects();
        $sems     = Lookup::semesters();
        $f        = $this->request->getGet();
        $classId  = (int) ($f['class_group_id'] ?? 0) ?: (int) array_key_first($classes);
        $subjectId = (int) ($f['subject_id'] ?? 0) ?: (int) array_key_first($subjects);
        $semId    = (int) ($f['semester_id'] ?? 0) ?: (int) Period::semesterId();
        $from     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? '')) ? $f['from'] : null;
        $to       = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? '')) ? $f['to'] : null;
        $parts    = array_filter(explode(',', (string) ($f['parts'] ?? 'cover,schedule,agenda,activity')));

        $m = (new AgendaModel())
            ->select('teaching_agendas.*, lo.code AS tp_code')
            ->join('learning_objectives lo', 'lo.id = teaching_agendas.learning_objective_id', 'left')
            ->where('teaching_agendas.user_id', Auth::id())
            ->where('teaching_agendas.class_group_id', $classId)
            ->where('teaching_agendas.subject_id', $subjectId)
            ->where('teaching_agendas.semester_id', $semId)
            ->where('teaching_agendas.status !=', 'draf');
        if ($from) {
            $m->where('agenda_date >=', $from);
        }
        if ($to) {
            $m->where('agenda_date <=', $to);
        }
        $agendas = $m->orderBy('agenda_date', 'ASC')->orderBy('teaching_agendas.id', 'ASC')->findAll();

        // Rekap absensi a/s/i per pertemuan
        $abs = [];
        if ($agendas) {
            foreach ((new AttendanceModel())->whereIn('teaching_agenda_id', array_column($agendas, 'id'))->where('status !=', 'H')->findAll() as $a) {
                $abs[$a['teaching_agenda_id']][$a['status']] = ($abs[$a['teaching_agenda_id']][$a['status']] ?? 0) + 1;
            }
        }

        $semester = (new SemesterModel())->find($semId);
        // Kegiatan di luar KBM pada semester (atau rentang tanggal) terpilih
        $activities = $semester ? Activities::between((int) Auth::id(), $from ?? $semester['start_date'], $to ?? $semester['end_date']) : [];
        $reviews  = (new PrincipalReviewModel())->where('user_id', Auth::id())->where('academic_year_id', Period::yearId())->orderBy('review_date', 'ASC')->findAll();

        Audit::log('print', 'journal', null, null, ['class' => $classId, 'subject' => $subjectId, 'semester' => $semId]);

        return $this->render('reports/journal', [
            'title'     => 'Buku Jurnal Agenda Guru',
            'ownKop'    => true,
            'classes'   => $classes,
            'subjects'  => $subjects,
            'sems'      => $sems,
            'classId'   => $classId,
            'subjectId' => $subjectId,
            'semId'     => $semId,
            'from'      => $from,
            'to'        => $to,
            'parts'     => $parts,
            'agendas'   => $agendas,
            'activities' => $activities,
            'abs'       => $abs,
            'semester'  => $semester,
            'reviews'   => $reviews,
            'grid'      => Schedules::grid(Period::yearId(), Auth::id()),
        ]);
    }
}
