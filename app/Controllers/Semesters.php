<?php

namespace App\Controllers;

use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\AcademicYearModel;
use App\Models\AgendaModel;
use App\Models\AssessmentModel;
use App\Models\SemesterModel;
use CodeIgniter\Model;

class Semesters extends CrudController
{
    protected string $modelClass = SemesterModel::class;
    protected string $table      = 'semesters';
    protected string $base       = 'semesters';
    protected string $title      = 'Semester';
    protected string $singular   = 'Semester';
    protected string $entity     = 'semester';
    protected ?string $intro     = 'Rentang tanggal semester harus berada di dalam tahun ajaran terkait.';
    protected array $order       = ['start_date', 'ASC'];
    protected bool $archive      = false;
    protected bool $audit        = true;

    protected function filterDefs(): array
    {
        return ['academic_year_id' => ['label' => 'Tahun ajaran', 'options' => Lookup::years(), 'default' => (string) Period::yearId()]];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $m->select('semesters.*, y.name AS year_name')->join('academic_years y', 'y.id = semesters.academic_year_id');

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        return [
            'academic_year_id' => ['label' => 'Tahun ajaran', 'type' => 'select', 'options' => Lookup::years(), 'rules' => 'required|is_natural_no_zero', 'default' => $this->request->getGet('academic_year_id') ?? Period::yearId()],
            'name'             => ['label' => 'Semester', 'type' => 'select', 'options' => ['Ganjil' => 'Ganjil', 'Genap' => 'Genap'], 'rules' => 'required|in_list[Ganjil,Genap]'],
            'start_date'       => ['label' => 'Tanggal mulai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]'],
            'end_date'         => ['label' => 'Tanggal selesai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]'],
            'is_active'        => ['label' => 'Semester aktif pada tahun ajaran ini', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array
    {
        return [
            'year_name' => 'Tahun ajaran',
            'name'      => ['label' => 'Semester', 'fn' => static fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
            'period'    => ['label' => 'Rentang', 'fn' => static fn ($r) => fmt_date($r['start_date']) . ' – ' . fmt_date($r['end_date'])],
            'status'    => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('—')],
        ];
    }

    protected function prepare(array $data, ?array $old)
    {
        $year = (new AcademicYearModel())->find($data['academic_year_id']);
        if (! $year) {
            return 'Tahun ajaran tidak ditemukan.';
        }
        if ($data['start_date'] > $data['end_date']) {
            return 'Tanggal mulai tidak boleh setelah tanggal selesai.';
        }
        if ($data['start_date'] < $year['start_date'] || $data['end_date'] > $year['end_date']) {
            return 'Rentang semester harus berada di dalam tahun ajaran ' . $year['name'] . ' (' . fmt_date($year['start_date']) . ' – ' . fmt_date($year['end_date']) . ').';
        }
        $dup = (new SemesterModel())->where('academic_year_id', $data['academic_year_id'])->where('name', $data['name']);
        if ($old) {
            $dup->where('id !=', $old['id']);
        }
        if ($dup->countAllResults() > 0) {
            return 'Semester ' . $data['name'] . ' sudah ada pada tahun ajaran ini.';
        }

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        if ((int) $data['is_active'] === 1) {
            (new SemesterModel())->where('academic_year_id', $data['academic_year_id'])->where('id !=', $id)->set('is_active', 0)->update();
        }
        Period::reset();
    }

    protected function deleteBlocker(array $row): ?string
    {
        $used = (new AgendaModel())->withDeleted()->where('semester_id', $row['id'])->countAllResults()
            + (new AssessmentModel())->withDeleted()->where('semester_id', $row['id'])->countAllResults();

        return $used ? 'Semester sudah dipakai oleh agenda atau asesmen sehingga tidak dapat dihapus.' : null;
    }

    protected function redirectAfterSave(int $id): string
    {
        $row = (new SemesterModel())->find($id);

        return site_url('semesters?academic_year_id=' . ($row['academic_year_id'] ?? ''));
    }
}
