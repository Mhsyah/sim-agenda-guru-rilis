<?php

namespace App\Controllers;

use App\Libraries\Period;
use App\Models\AcademicYearModel;
use App\Models\ClassGroupModel;
use App\Models\SemesterModel;

class AcademicYears extends CrudController
{
    protected string $modelClass = AcademicYearModel::class;
    protected string $table      = 'academic_years';
    protected string $base       = 'academic-years';
    protected string $title      = 'Tahun Ajaran';
    protected string $singular   = 'Tahun ajaran';
    protected string $entity     = 'academic_year';
    protected ?string $intro     = 'Semua data akademik terikat pada tahun ajaran dan semester. Tandai satu tahun ajaran sebagai aktif.';
    protected array $order       = ['start_date', 'DESC'];
    protected bool $archive      = false;
    protected bool $audit        = true;

    protected function fields(?array $row): array
    {
        $y = (int) date('Y') - ((int) date('n') < 7 ? 1 : 0);

        return [
            'name'       => ['label' => 'Nama tahun ajaran', 'rules' => 'required|regex_match[/^\d{4}\/\d{4}$/]|is_unique[academic_years.name,id,{id}]', 'placeholder' => $y . '/' . ($y + 1), 'help' => 'Format: 2026/2027'],
            'start_date' => ['label' => 'Tanggal mulai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => $y . '-07-13'],
            'end_date'   => ['label' => 'Tanggal selesai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => ($y + 1) . '-06-30'],
            'is_active'  => ['label' => 'Jadikan tahun ajaran aktif', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function columns(): array
    {
        return [
            'name'   => ['label' => 'Tahun ajaran', 'fn' => static fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
            'period' => ['label' => 'Rentang', 'fn' => static fn ($r) => fmt_date($r['start_date']) . ' – ' . fmt_date($r['end_date'])],
            'status' => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Tidak aktif')],
        ];
    }

    protected function rowLinks(array $row): array
    {
        return [['label' => 'Semester', 'url' => site_url('semesters?academic_year_id=' . $row['id']), 'icon' => 'layers']];
    }

    protected function prepare(array $data, ?array $old)
    {
        if ($data['start_date'] > $data['end_date']) {
            return 'Tanggal mulai tidak boleh setelah tanggal selesai.';
        }

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        if ((int) $data['is_active'] === 1) {
            (new AcademicYearModel())->where('id !=', $id)->set('is_active', 0)->update();
            Period::set($id, null);
        }
        if (! $old) {
            // Buat semester Ganjil & Genap secara otomatis.
            $startYear = (int) substr($data['start_date'], 0, 4);
            $ganjilEnd = $startYear . '-12-31';
            $genapStart = ($startYear + 1) . '-01-01';
            if ($ganjilEnd < $data['end_date']) {
                $sem = new SemesterModel();
                $sem->insert(['academic_year_id' => $id, 'name' => 'Ganjil', 'start_date' => $data['start_date'], 'end_date' => $ganjilEnd, 'is_active' => 1]);
                $sem->insert(['academic_year_id' => $id, 'name' => 'Genap', 'start_date' => $genapStart, 'end_date' => $data['end_date'], 'is_active' => 0]);
            }
        }
        Period::reset();
    }

    protected function deleteBlocker(array $row): ?string
    {
        if ((new ClassGroupModel())->withDeleted()->where('academic_year_id', $row['id'])->countAllResults() > 0) {
            return 'Tahun ajaran sudah memiliki kelas dan data terkait sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        return null;
    }

    /** Ganti periode kerja yang sedang dilihat (dipanggil dari pemilih periode di header). */
    public function switch()
    {
        $yid = (int) $this->request->getPost('academic_year_id');
        $sid = (int) $this->request->getPost('semester_id');
        $sem = $sid ? (new SemesterModel())->where('academic_year_id', $yid)->where('id', $sid)->first() : null;
        Period::set($yid ?: null, $sem ? (int) $sem['id'] : null);

        return redirect()->back()->with('success', 'Periode kerja diperbarui.');
    }
}
