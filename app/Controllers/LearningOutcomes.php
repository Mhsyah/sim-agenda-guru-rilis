<?php

namespace App\Controllers;

use App\Libraries\CurriculumLinks;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\LearningOutcomeModel;
use CodeIgniter\Model;

class LearningOutcomes extends CrudController
{
    protected string $modelClass = LearningOutcomeModel::class;
    protected string $table      = 'learning_outcomes';
    protected ?string $deleteLabel   = 'Hapus';
    protected ?string $deleteConfirm = 'Hapus Capaian Pembelajaran ini? Tautannya ke TP ikut dilepas dari daftar.';
    protected string $base       = 'outcomes';
    protected string $title      = 'Capaian Pembelajaran (CP)';
    protected string $singular   = 'CP';
    protected string $entity     = 'learning_outcome';
    protected ?string $intro     = 'Simpan CP per mapel dan fase, lalu hubungkan dengan TP yang menurunkannya.';
    protected array $searchFields = ['learning_outcomes.element_name', 'learning_outcomes.description', 'learning_outcomes.code'];
    protected array $order       = ['s.name', 'ASC', 'learning_outcomes.code', 'ASC'];
    protected bool $audit        = true;

    protected function filterDefs(): array
    {
        return [
            'subject_id' => ['label' => 'Mapel', 'options' => Lookup::teachingSubjects()],
            'phase'      => ['label' => 'Fase', 'options' => phases()],
            'semester_id' => ['label' => 'Semester', 'options' => Lookup::semesters(), 'custom' => true, 'default' => (string) Period::semesterId()],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $oo = db_connect()->prefixTable('outcome_objectives');
        $lo = db_connect()->prefixTable('learning_outcomes');
        $m->select('learning_outcomes.*, s.name AS subject_name, s.code AS subject_code')
            ->select("(SELECT COUNT(*) FROM {$oo} x WHERE x.learning_outcome_id = {$lo}.id) AS tp_count", false)
            ->join('subjects s', 's.id = learning_outcomes.subject_id');

        // Saringan semester: semester terpilih + yang berlaku di kedua semester
        if (($filters['semester_id'] ?? '') !== '') {
            $m->groupStart()->where('learning_outcomes.semester_id', (int) $filters['semester_id'])->orWhere('learning_outcomes.semester_id', null)->groupEnd();
        }

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        return [
            'subject_id'    => ['label' => 'Mata pelajaran', 'type' => 'select', 'options' => Lookup::teachingSubjectsWith(isset($row['subject_id']) ? (int) $row['subject_id'] : null), 'rules' => 'required|is_natural_no_zero', 'empty' => '— Pilih mapel —'],
            'phase'         => ['label' => 'Fase', 'type' => 'select', 'options' => phases(), 'rules' => 'permit_empty|in_list[A,B,C,D,E,F]', 'empty' => '— Pilih fase —'],
            'element_name'  => ['label' => 'Elemen', 'rules' => 'required|max_length[150]', 'placeholder' => 'Bilangan'],
            'semester_id'   => ['label' => 'Semester', 'type' => 'select', 'options' => ($sems = Lookup::semesters()), 'rules' => 'permit_empty|in_list[' . implode(',', array_keys($sems) ?: [0]) . ']', 'empty' => '— Keduanya (Ganjil & Genap) —'],
            'code'          => ['label' => 'Kode CP', 'rules' => 'permit_empty|max_length[30]', 'placeholder' => 'CP-MTK-D-01'],
            'description'   => ['label' => 'Deskripsi capaian', 'type' => 'textarea', 'rules' => 'required|max_length[5000]', 'span' => 'full'],
            'objective_ids' => ['label' => 'TP terkait', 'type' => 'checkboxes', 'groups' => Lookup::objectivesForSemester(null, $row ? CurriculumLinks::objectivesOf((int) $row['id']) : []), 'filterBy' => 'subject_id', 'span' => 'full', 'help' => 'Hanya TP dari mapel yang dipilih yang ditampilkan.'],
            'is_active'     => ['label' => 'Aktif (hapus centang untuk mengarsipkan sementara)', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function formValues(?array $row): array
    {
        return $row ? $row + ['objective_ids' => CurriculumLinks::objectivesOf((int) $row['id'])] : [];
    }

    protected function columns(): array
    {
        return [
            'code'         => ['label' => 'Kode', 'fn' => static fn ($r) => '<span class="whitespace-nowrap">' . esc($r['code'] ?? '') . '</span>'],
            'element_name' => ['label' => 'Elemen & deskripsi', 'class' => 'col-main', 'fn' => static fn ($r) => '<strong>' . esc($r['element_name']) . '</strong><span class="block text-sm text-muted">' . excerpt($r['description'], 110) . '</span>'],
            'subject_name' => ['label' => 'Mapel', 'fn' => static fn ($r) => subject_chip($r['subject_code'] ?? null, $r['subject_name']) . ($r['phase'] ? ' <span class="text-xs text-muted">Fase ' . esc($r['phase']) . '</span>' : '')],
            'semester'     => ['label' => 'Semester', 'fn' => static fn ($r) => $r['semester_id'] ? esc(Lookup::semesters()[$r['semester_id']] ?? '—') : '<span class="text-muted">Keduanya</span>'],
            'tp_count'     => ['label' => 'TP', 'class' => 'num', 'fn' => static fn ($r) => (int) $r['tp_count']],
            'status'       => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif')],
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

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        CurriculumLinks::syncForOutcome($id, (array) ($this->request->getPost('objective_ids') ?? []));
    }
}
