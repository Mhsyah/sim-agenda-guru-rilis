<?php

namespace App\Controllers;

use App\Libraries\CurriculumLinks;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\LearningObjectiveModel;
use CodeIgniter\Model;

class LearningObjectives extends CrudController
{
    protected string $modelClass = LearningObjectiveModel::class;
    protected string $table      = 'learning_objectives';
    protected ?string $deleteLabel   = 'Hapus';
    protected ?string $deleteConfirm = 'Hapus Tujuan Pembelajaran ini? Agenda & asesmen lama yang memakainya tetap tersimpan.';
    protected string $base       = 'objectives';
    protected string $title      = 'Tujuan Pembelajaran (TP)';
    protected string $singular   = 'TP';
    protected string $entity     = 'learning_objective';
    protected ?string $intro     = 'TP dapat dipilih saat menulis agenda dan membuat asesmen. Urutan ATP menyusun alur tujuan pembelajaran; KKTP menentukan tingkat ketercapaian siswa.';
    protected array $searchFields = ['learning_objectives.description', 'learning_objectives.code'];
    protected array $order       = ['s.name', 'ASC', 'learning_objectives.atp_order', 'ASC', 'learning_objectives.code', 'ASC'];
    protected bool $audit        = true;

    protected function filterDefs(): array
    {
        return [
            'subject_id'  => ['label' => 'Mapel', 'options' => Lookup::teachingSubjects()],
            'semester_id' => ['label' => 'Semester', 'options' => Lookup::semesters(), 'custom' => true, 'default' => (string) Period::semesterId()],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $oo = db_connect()->prefixTable('outcome_objectives');
        $lo = db_connect()->prefixTable('learning_objectives');
        $ag = db_connect()->prefixTable('teaching_agendas');
        $m->select('learning_objectives.*, s.name AS subject_name, s.code AS subject_code')
            ->select("(SELECT COUNT(*) FROM {$oo} x WHERE x.learning_objective_id = {$lo}.id) AS cp_count,
            (SELECT COUNT(*) FROM {$ag} a WHERE a.learning_objective_id = {$lo}.id AND a.deleted_at IS NULL) AS agenda_count", false)
            ->join('subjects s', 's.id = learning_objectives.subject_id');

        // Saringan semester: semester terpilih + yang berlaku di kedua semester
        if (($filters['semester_id'] ?? '') !== '') {
            $m->groupStart()->where('learning_objectives.semester_id', (int) $filters['semester_id'])->orWhere('learning_objectives.semester_id', null)->groupEnd();
        }

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        return [
            'subject_id'        => ['label' => 'Mata pelajaran', 'type' => 'select', 'options' => Lookup::teachingSubjectsWith(isset($row['subject_id']) ? (int) $row['subject_id'] : null), 'rules' => 'required|is_natural_no_zero', 'empty' => '— Pilih mapel —'],
            'code'              => ['label' => 'Kode TP', 'rules' => 'permit_empty|max_length[30]', 'placeholder' => 'TP-7.1'],
            'description'       => ['label' => 'Rumusan tujuan pembelajaran', 'type' => 'textarea', 'rules' => 'required|max_length[3000]', 'span' => 'full'],
            'semester_id'       => ['label' => 'Semester', 'type' => 'select', 'options' => ($sems = Lookup::semesters()), 'rules' => 'permit_empty|in_list[' . implode(',', array_keys($sems) ?: [0]) . ']', 'empty' => '— Keduanya (Ganjil & Genap) —'],
            'grade_level'       => ['label' => 'Kelas (tingkat)', 'type' => 'select', 'options' => array_combine(range(1, 12), array_map(static fn ($g) => 'Kelas ' . int_to_roman($g) . ' (' . $g . ')', range(1, 12))), 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[13]', 'empty' => '— Semua tingkat —'],
            'atp_order'         => ['label' => 'Urutan ATP', 'type' => 'number', 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[999]', 'attrs' => ['min' => 1, 'step' => 1], 'help' => 'Urutan dalam Alur Tujuan Pembelajaran (1, 2, 3, …).'],
            'kktp_criteria'     => ['label' => 'KKTP — deskripsi kriteria ketercapaian', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[3000]', 'span' => 'full', 'placeholder' => 'Siswa mampu …'],
            'kktp_intervals'    => ['label' => 'KKTP — interval nilai', 'rules' => 'permit_empty|max_length[60]', 'placeholder' => '40,65,85',
                'help' => 'Batas atas tingkat 1–3. Kosong = bawaan: ' . \App\Libraries\Kktp::describe(null) . '.'],
            'estimated_minutes' => ['label' => 'Alokasi waktu (menit)', 'type' => 'number', 'rules' => 'permit_empty|is_natural|less_than_equal_to[100000]', 'attrs' => ['min' => 0, 'step' => 5], 'help' => 'Tidak boleh negatif.'],
            'outcome_ids'       => ['label' => 'CP terkait', 'type' => 'checkboxes', 'groups' => Lookup::outcomesForSemester($row ? CurriculumLinks::outcomesOf((int) $row['id']) : []), 'filterBy' => 'subject_id', 'span' => 'full'],
            'is_active'         => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function formValues(?array $row): array
    {
        return $row ? $row + ['outcome_ids' => CurriculumLinks::outcomesOf((int) $row['id'])] : [];
    }

    protected function columns(): array
    {
        return [
            'code'         => ['label' => 'Kode', 'fn' => static fn ($r) => '<span class="whitespace-nowrap">' . esc($r['code'] ?? '') . '</span>'],
            'description'  => ['label' => 'Tujuan pembelajaran', 'class' => 'col-main', 'fn' => static fn ($r) => excerpt($r['description'], 140)],
            'atp'          => ['label' => 'ATP', 'class' => 'num', 'fn' => static fn ($r) => $r['atp_order'] !== null ? (int) $r['atp_order'] : '—'],
            'subject_name' => ['label' => 'Mapel', 'fn' => static fn ($r) => subject_chip($r['subject_code'] ?? null, $r['subject_name']) . ($r['grade_level'] ? ' <span class="text-xs text-muted">Kls ' . int_to_roman((int) $r['grade_level']) . '</span>' : '')],
            'kktp'         => ['label' => 'KKTP', 'fn' => static fn ($r) => ($r['kktp_criteria'] ?? '') !== '' || ($r['kktp_intervals'] ?? '') !== '' ? badge('Ada', 'success') : badge('Belum', 'neutral')],
            'estimated'    => ['label' => 'Waktu', 'class' => 'num', 'fn' => static fn ($r) => $r['estimated_minutes'] !== null ? (int) $r['estimated_minutes'] . ' mnt' : '—'],
            'semester'     => ['label' => 'Semester', 'fn' => static fn ($r) => $r['semester_id'] ? esc(Lookup::semesters()[$r['semester_id']] ?? '—') : '<span class="text-muted">Keduanya</span>'],
            'links'        => ['label' => 'CP / Agenda', 'class' => 'num', 'fn' => static fn ($r) => (int) $r['cp_count'] . ' / ' . (int) $r['agenda_count']],
        ];
    }

    protected function prepare(array $data, ?array $old)
    {
        if (array_key_exists('semester_id', $data) && ($data['semester_id'] === '' || $data['semester_id'] === null)) {
            $data['semester_id'] = null;
        }
        if (! $old) {
            $data['academic_year_id'] = Period::yearId();
        }
        if (array_key_exists('kktp_intervals', $data)) {
            $raw = trim((string) $data['kktp_intervals']);
            $norm = \App\Libraries\Kktp::normalize($raw);
            if ($raw !== '' && $norm === null) {
                return 'Interval KKTP tidak valid. Contoh: 40,65,85 (batas atas tingkat Perlu bimbingan, Cukup, Baik).';
            }
            $data['kktp_intervals'] = $norm;
        }
        foreach (['grade_level', 'atp_order'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        CurriculumLinks::syncForObjective($id, (array) ($this->request->getPost('outcome_ids') ?? []));
    }
}
