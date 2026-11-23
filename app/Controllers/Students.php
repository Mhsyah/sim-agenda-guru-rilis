<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Libraries\StudentImporter;
use App\Models\AssessmentModel;
use App\Models\EnrollmentModel;
use App\Models\ScoreModel;
use App\Models\StudentModel;
use CodeIgniter\Model;

class Students extends CrudController
{
    protected string $modelClass = StudentModel::class;
    protected string $table      = 'students';
    protected string $base       = 'students';
    protected string $title      = 'Data Siswa';
    protected string $singular   = 'Siswa';
    protected string $entity     = 'student';
    protected ?string $intro     = 'Kumpulkan hanya data yang diperlukan untuk pembelajaran. Penempatan kelas berlaku untuk tahun ajaran aktif. Data siswa dikelola admin teknis.';
    protected array $searchFields = ['students.full_name', 'students.nis', 'students.nisn'];
    protected array $order       = ['cg.name', 'ASC', 'students.full_name', 'ASC'];
    protected int $perPage       = 25;
    protected bool $audit        = true;

    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return parent::index();
    }

    protected function filterDefs(): array
    {
        return [
            // Guru: hanya kelas yang diajar; admin: semua kelas + "Belum ditempatkan"
            'class_group_id' => ['label' => 'Kelas', 'options' => Lookup::teachingClassIds() !== null ? Lookup::teachingClasses() : Lookup::classes() + ['none' => 'Belum ditempatkan'], 'custom' => true],
            'status'         => ['label' => 'Status', 'options' => student_statuses()],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $yid = (int) Period::yearId();
        $m->select('students.*, cg.name AS class_name, en.class_group_id')
            ->join('student_enrollments en', 'en.student_id = students.id AND en.academic_year_id = ' . $yid, 'left')
            ->join('class_groups cg', 'cg.id = en.class_group_id', 'left');
        if (($taught = Lookup::teachingClassIds()) !== null) {
            $m->whereIn('en.class_group_id', $taught); // guru hanya melihat siswa di kelas yang diajar
        }
        if (($filters['class_group_id'] ?? '') === 'none') {
            $m->where('en.id', null);
        } elseif (($filters['class_group_id'] ?? '') !== '') {
            $m->where('en.class_group_id', (int) $filters['class_group_id']);
        }

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        return [
            'full_name'      => ['label' => 'Nama lengkap', 'rules' => 'required|max_length[150]', 'span' => 'full'],
            'nis'            => ['label' => 'NIS', 'rules' => 'permit_empty|max_length[30]|is_unique[students.nis,id,{id}]'],
            'nisn'           => ['label' => 'NISN', 'rules' => 'permit_empty|numeric|max_length[20]|is_unique[students.nisn,id,{id}]'],
            'gender'         => ['label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan'], 'rules' => 'permit_empty|in_list[L,P]', 'empty' => '— Pilih —'],
            'birth_date'     => ['label' => 'Tanggal lahir', 'type' => 'date', 'rules' => 'permit_empty|valid_date[Y-m-d]', 'help' => 'Opsional.'],
            'status'         => ['label' => 'Status', 'type' => 'select', 'options' => student_statuses(), 'rules' => 'required|in_list[aktif,pindah,lulus,nonaktif]', 'default' => 'aktif'],
            'class_group_id' => ['label' => 'Kelas (tahun ajaran aktif)', 'type' => 'select', 'options' => Lookup::classes(), 'rules' => 'permit_empty|is_natural_no_zero', 'empty' => '— Belum ditempatkan —', 'virtual' => true],
        ];
    }

    protected function formValues(?array $row): array
    {
        if (! $row) {
            return [];
        }
        $en = (new EnrollmentModel())->where('student_id', $row['id'])->where('academic_year_id', Period::yearId())->first();

        return $row + ['class_group_id' => $en['class_group_id'] ?? ''];
    }

    protected function columns(): array
    {
        return [
            'nis'        => 'NIS',
            'full_name'  => ['label' => 'Nama', 'class' => 'col-main', 'fn' => static fn ($r) => '<strong>' . esc($r['full_name']) . '</strong>' . ($r['nisn'] ? '<span class="block text-xs text-muted">NISN ' . esc($r['nisn']) . '</span>' : '')],
            'gender'     => 'L/P',
            'class_name' => ['label' => 'Kelas', 'fn' => static fn ($r) => $r['class_name'] ? esc($r['class_name']) : badge('Belum ditempatkan', 'warning')],
            'status'     => ['label' => 'Status', 'fn' => static fn ($r) => badge(student_statuses()[$r['status']] ?? $r['status'], $r['status'] === 'aktif' ? 'success' : 'neutral')],
        ];
    }

    protected function rowLinks(array $row): array
    {
        return [['label' => 'Riwayat', 'url' => site_url('students/' . $row['id'] . '/history'), 'icon' => 'clock']];
    }

    protected function extraIndexData(array $filters): array
    {
        return Auth::isAdmin() ? [
            'bulk'        => ['url' => site_url('students/bulk'), 'statuses' => student_statuses(), 'classes' => Lookup::classes()],
            'deleteLabel' => 'Hapus',
            'deleteConfirm' => 'Hapus siswa ini dari daftar? Riwayat nilai & kehadiran tetap tersimpan (arsip).',
            'notices'     => \App\Libraries\ClassCapacity::notices(), // rombel > 36 & kelas akhir belum lulus
        ] : [];
    }

    /** Aksi massal untuk siswa yang dicentang: hapus (arsip), ubah status, atau tempatkan ke kelas. */
    public function bulk()
    {
        if (! Auth::isAdmin()) {
            return redirect()->to(site_url('students'))->with('error', 'Hanya admin teknis yang dapat mengubah data siswa.');
        }
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($this->request->getPost('ids') ?? [])))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Centang minimal satu siswa terlebih dahulu.');
        }
        $ids    = array_map('intval', array_column((new StudentModel())->select('id')->whereIn('id', $ids)->findAll(), 'id'));
        $action = (string) $this->request->getPost('action');
        $n      = count($ids);
        $db     = db_connect();
        $db->transException(true)->transStart();
        switch ($action) {
            case 'delete':
                (new StudentModel())->delete($ids);
                foreach ($ids as $id) {
                    Audit::log('archive', 'student', $id);
                }
                $msg = $n . ' siswa dihapus dari daftar (diarsipkan).';
                break;
            case 'status':
                $st = (string) $this->request->getPost('status');
                if (! isset(student_statuses()[$st])) {
                    $db->transComplete();

                    return redirect()->back()->with('error', 'Pilih status yang valid.');
                }
                foreach ((new StudentModel())->whereIn('id', $ids)->findAll() as $row) {
                    if ($row['status'] !== $st) {
                        (new StudentModel())->update($row['id'], ['status' => $st]);
                        Audit::log('update', 'student', (int) $row['id'], ['status' => $row['status']], ['status' => $st]);
                    }
                }
                $msg = 'Status ' . $n . ' siswa diubah menjadi ' . student_statuses()[$st] . '.';
                break;
            case 'class':
                $cid = (int) $this->request->getPost('class_group_id');
                if (! isset(Lookup::classes()[$cid])) {
                    $db->transComplete();

                    return redirect()->back()->with('error', 'Pilih kelas tujuan.');
                }
                foreach ($ids as $id) {
                    self::enroll($id, $cid);
                    Audit::log('enroll', 'student', $id, null, ['class_group_id' => $cid, 'academic_year_id' => Period::yearId()]);
                }
                $msg = $n . ' siswa ditempatkan di kelas ' . Lookup::classes()[$cid] . '.';
                break;
            default:
                $db->transComplete();

                return redirect()->back()->with('error', 'Pilih aksi untuk siswa yang dicentang.');
        }
        $db->transComplete();

        return redirect()->back()->with('success', $msg);
    }

    /** Status & riwayat siswa: perubahan status, penempatan kelas per tahun, ringkasan kehadiran. */
    public function history(int $id)
    {
        $student = (new StudentModel())->withDeleted()->find($id);
        if (! $student) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Siswa tidak ditemukan.');
        }
        // Guru hanya boleh membuka riwayat siswa di kelas yang diajarnya (T.P. aktif)
        if (($taught = Lookup::teachingClassIds()) !== null && ! db_connect()->table('student_enrollments')
            ->where('student_id', $id)->where('academic_year_id', (int) Period::yearId())->whereIn('class_group_id', $taught)->countAllResults()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Siswa tidak ditemukan.');
        }

        return $this->render('students/history', [
            'title'      => 'Riwayat Siswa',
            'student'    => $student,
            'timeline'   => \App\Libraries\StudentHistory::timeline($student),
            'placements' => \App\Libraries\StudentHistory::placements($id),
            'attendance' => \App\Libraries\StudentHistory::attendance($id),
            'absences'   => \App\Libraries\StudentHistory::recentAbsences($id),
        ]);
    }

    protected function canEdit(): bool
    {
        return Auth::isAdmin();
    }

    protected function headerLinks(): array
    {
        $q     = http_build_query(array_filter($this->currentFilters(), static fn ($v) => $v !== ''));
        $links = [['label' => 'Ekspor CSV', 'url' => site_url('students/export' . ($q ? '?' . $q : '')), 'icon' => 'download']];
        if (Auth::isAdmin()) {
            array_unshift($links, ['label' => 'Impor CSV', 'url' => site_url('students/import'), 'icon' => 'upload']);
        }

        return $links;
    }

    protected function prepare(array $data, ?array $old)
    {
        $data['full_name'] = preg_replace('/\s+/', ' ', (string) $data['full_name']);

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        $classId = (int) $this->request->getPost('class_group_id');
        $before  = (new EnrollmentModel())->where('student_id', $id)->where('academic_year_id', Period::yearId())->first();
        self::enroll($id, $classId ?: null);
        if ($classId && (int) ($before['class_group_id'] ?? 0) !== $classId) {
            Audit::log('enroll', 'student', $id, null, ['class_group_id' => $classId, 'academic_year_id' => Period::yearId()]);
        }
    }

    /** Tetapkan/ubah/hapus penempatan siswa pada tahun ajaran aktif (maksimal satu). */
    public static function enroll(int $studentId, ?int $classId): void
    {
        $yid = Period::yearId();
        $em  = new EnrollmentModel();
        $cur = $em->where('student_id', $studentId)->where('academic_year_id', $yid)->first();
        if ($classId === null) {
            if ($cur) {
                (new EnrollmentModel())->delete($cur['id']);
            }

            return;
        }
        if ($cur) {
            (new EnrollmentModel())->update($cur['id'], ['class_group_id' => $classId, 'status' => 'aktif']);
        } else {
            (new EnrollmentModel())->insert(['student_id' => $studentId, 'class_group_id' => $classId, 'academic_year_id' => $yid, 'status' => 'aktif']);
        }
    }

    protected function deleteBlocker(array $row): ?string
    {
        return null; // arsip (soft delete) mempertahankan jejak nilai
    }

    public function export()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $filters = $this->currentFilters();
        $m       = $this->listQuery(new StudentModel(), $filters);
        if (($q = trim((string) $this->request->getGet('q'))) !== '') {
            $m->groupStart()->like($this->prefixed('students.full_name'), $q, 'both', null, true)->orLike($this->prefixed('students.nis'), $q, 'both', null, true)->groupEnd();
        }
        $rows = $m->orderBy('cg.name', 'ASC')->orderBy('students.full_name', 'ASC')->findAll();
        $out  = [];
        foreach ($rows as $i => $r) {
            $out[] = [$i + 1, $r['nis'], $r['nisn'], $r['full_name'], $r['gender'], $r['birth_date'], $r['class_name'], $r['status']];
        }
        Audit::log('export', 'student', null, null, ['rows' => count($out)]);

        return csv_response('siswa-' . date('Ymd') . '.csv', ['No', 'NIS', 'NISN', 'Nama Lengkap', 'L/P', 'Tanggal Lahir', 'Kelas', 'Status'], $out);
    }

    public function importForm()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return $this->render('students/import', [
            'title'   => 'Impor Siswa dari CSV',
            'classes' => Lookup::classes(),
            'result'  => session('import_result'),
        ]);
    }

    public function template()
    {
        return csv_response('template-impor-siswa.csv', ['nis', 'nisn', 'full_name', 'gender', 'birth_date', 'class'], [
            ['2026001', '0123456789', 'Contoh Nama Siswa', 'L', '2013-05-17', 'VII A'],
        ]);
    }

    /** Info pendukung: jumlah nilai yang dimiliki siswa (untuk peringatan arsip). */
    public static function scoreCount(int $studentId): int
    {
        return (new ScoreModel())->where('student_id', $studentId)->countAllResults();
    }
}
