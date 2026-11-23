<?php

namespace App\Controllers;

use App\Models\ClassGroupModel;
use CodeIgniter\Model;

class ClassGroups extends CrudController
{
    protected string $modelClass = ClassGroupModel::class;
    protected string $table      = 'class_groups';
    protected string $base       = 'classes';
    protected string $title      = 'Kelas/Rombel';
    protected string $singular   = 'Kelas';
    protected string $entity     = 'class_group';
    protected ?string $intro     = 'Kelas berlaku per tahun ajaran aktif.';
    protected array $searchFields = ['class_groups.name'];
    protected array $order       = ['grade_level', 'ASC', 'name', 'ASC'];
    protected bool $yearScoped   = true;
    protected bool $audit        = true;
    protected string $indexView  = 'classes/index';
    protected int $perPage       = 200;
    protected ?string $deleteLabel   = 'Hapus';

    protected function listQuery(Model $m, array $filters): Model
    {
        $e = db_connect()->prefixTable('student_enrollments');
        $c = db_connect()->prefixTable('class_groups');
        $m->select('class_groups.*')
            ->select("(SELECT COUNT(*) FROM {$e} e JOIN " . db_connect()->prefixTable('students') . " s ON s.id = e.student_id AND s.deleted_at IS NULL WHERE e.class_group_id = {$c}.id AND e.status = 'aktif') AS student_count", false)
            ->select("(SELECT COUNT(*) FROM {$e} e JOIN " . db_connect()->prefixTable('students') . " s ON s.id = e.student_id AND s.deleted_at IS NULL WHERE e.class_group_id = {$c}.id AND e.status = 'aktif' AND s.gender = 'L') AS male_count", false)
            ->select("(SELECT COUNT(*) FROM {$e} e JOIN " . db_connect()->prefixTable('students') . " s ON s.id = e.student_id AND s.deleted_at IS NULL WHERE e.class_group_id = {$c}.id AND e.status = 'aktif' AND s.gender = 'P') AS female_count", false);

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        return [
            'name'        => ['label' => 'Nama kelas/rombel', 'rules' => 'required|max_length[50]', 'placeholder' => 'VII A'],
            'grade_level' => ['label' => 'Tingkat', 'type' => 'number', 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[13]', 'attrs' => ['min' => 1, 'max' => 13], 'placeholder' => '7'],
            'is_active'   => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function columns(): array
    {
        return [
            'name'          => ['label' => 'Kelas', 'fn' => static fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
            'grade_level'   => 'Tingkat',
            'student_count' => ['label' => 'Siswa aktif', 'class' => 'num', 'fn' => static fn ($r) => (int) $r['student_count']],
            'status'        => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif')],
        ];
    }

    /** Guru pengajar per kelas (dari pembagian mengajar) untuk kartu kelas. */
    protected function extraIndexData(array $filters): array
    {
        $teachers = [];
        foreach (db_connect()->table('user_teaching ut')->select('ut.class_group_id, u.name, s.code, s.name AS subject')
            ->join('users u', 'u.id = ut.user_id')->join('subjects s', 's.id = ut.subject_id')
            ->where('u.is_active', 1)->orderBy('s.name', 'ASC')->get()->getResultArray() as $r) {
            $teachers[(int) $r['class_group_id']][] = ($r['code'] ?: $r['subject']) . ' · ' . preg_replace('/,.*$/', '', $r['name']);
        }

        return ['teachers' => $teachers];
    }

    /** Daftar siswa ringkas satu kelas (dimuat ke jendela pop-up di halaman Kelas/Rombel). */
    public function students(int $id)
    {
        $class = $this->findOr404($id);
        $rows  = db_connect()->table('student_enrollments e')->select('s.id, s.full_name, s.nis, s.gender')
            ->join('students s', 's.id = e.student_id')->where('e.class_group_id', $id)->where('e.status', 'aktif')
            ->where('s.deleted_at', null)->orderBy('s.full_name', 'ASC')->get()->getResultArray();

        return view('classes/students', ['class' => $class, 'rows' => $rows]);
    }

    protected function rowLinks(array $row): array
    {
        return [['label' => 'Siswa', 'url' => site_url('students?class_group_id=' . $row['id']), 'icon' => 'users']];
    }

    protected function prepare(array $data, ?array $old)
    {
        $yid = $old['academic_year_id'] ?? $data['academic_year_id'];
        $dup = (new ClassGroupModel())->withDeleted()->where('academic_year_id', $yid)->where('name', $data['name']);
        if ($old) {
            $dup->where('id !=', $old['id']);
        }
        if ($dup->countAllResults() > 0) {
            return 'Kelas "' . $data['name'] . '" sudah ada pada tahun ajaran ini (termasuk yang diarsipkan).';
        }

        return $data;
    }
}
