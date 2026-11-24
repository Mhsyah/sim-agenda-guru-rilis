<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\TeacherActivity;
use App\Models\AgendaModel;
use App\Models\AssessmentModel;
use App\Models\UserModel;
use CodeIgniter\Model;

/** Data guru & pengguna (dikelola admin teknis). */
class Users extends CrudController
{
    /** Status kepegawaian akun: label + warna badge. Hanya "aktif" yang dapat login. */
    public const STATUSES = [
        'aktif'    => ['Aktif', 'success'],
        'nonaktif' => ['Nonaktif', 'warning'],
        'mutasi'   => ['Mutasi', 'info'],
        'keluar'   => ['Keluar', 'danger'],
    ];

    /** @var array<int, array> progres kegiatan per guru (halaman ini) */
    private array $activity = [];

    /** @var array<int, string> kode mapel per id (untuk warna kode mapel) */
    private array $subjectCodes = [];

    protected string $modelClass = UserModel::class;
    protected string $table      = 'users';
    protected string $base       = 'users';
    protected string $title      = 'Data Guru & Pengguna';
    protected string $singular   = 'Pengguna';
    protected string $entity     = 'user';
    protected ?string $intro     = 'Kelola akun guru dan admin teknis. Guru hanya melihat dan mengelola data pembelajarannya sendiri; admin teknis mengelola data induk sekolah.';
    protected array $searchFields = ['users.name', 'users.email', 'users.nip'];
    protected array $order       = ['role', 'DESC', 'name', 'ASC'];
    protected bool $archive      = false;
    protected bool $audit        = true;
    protected string $formView   = 'users/form';
    protected string $indexView  = 'users/index';
    protected int $perPage       = 24;

    protected function filterDefs(): array
    {
        return [
            'role'      => ['label' => 'Peran', 'options' => Auth::ROLES],
            'employment_status' => ['label' => 'Status', 'options' => array_map(static fn ($s) => $s[0], self::STATUSES)],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $ag = db_connect()->prefixTable('teaching_agendas');
        $us = db_connect()->prefixTable('users');
        $m->select('users.id, users.name, users.email, users.nip, users.role, users.is_active, users.last_login_at, users.gender, users.photo, users.employment_status, users.status_date, users.status_note, users.must_change_password')
            ->select("(SELECT COUNT(*) FROM {$ag} a WHERE a.user_id = {$us}.id AND a.deleted_at IS NULL) AS agenda_count", false);

        return parent::listQuery($m, $filters);
    }

    /** Data tambahan untuk tampilan kartu: ringkasan jumlah + mapel & kelas per guru. */
    protected function extraIndexData(array $filters): array
    {
        $db = db_connect();
        $c  = static fn (callable $f) => $f($db->table('users'))->countAllResults();

        return [
            'stats' => [
                'total'    => $c(static fn ($b) => $b),
                'teacher'  => $c(static fn ($b) => $b->where('role', 'teacher')),
                'admin'    => $c(static fn ($b) => $b->where('role', 'admin')),
                'active'   => $c(static fn ($b) => $b->where('is_active', 1)),
                'inactive' => $c(static fn ($b) => $b->where('is_active', 0)),
                'never'    => $c(static fn ($b) => $b->where('is_active', 1)->where('last_login_at', null)),
                'moved'    => $c(static fn ($b) => $b->whereIn('employment_status', ['mutasi', 'keluar'])),
            ],
            'activity'     => $this->activity,
            'newPassword'  => session('new_password'),
            'subjectNames' => $this->subjectNames,
            'subjectCodes' => $this->subjectCodes,
            'classNames'   => $this->classNames,
        ];
    }

    /** "VII A, VII B, VIII E" → ["VII" => "A, B", "VIII" => "E"] agar ringkas. */
    public static function groupClasses(array $names): array
    {
        $out = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if (preg_match('/^(.+?)\s+(\S+)$/u', $n, $m)) {
                $out[$m[1]][] = $m[2];
            } else {
                $out[$n] = $out[$n] ?? [];
            }
        }
        foreach ($out as &$v) {
            natsort($v);
            $v = implode(', ', $v);
        }
        unset($v);
        uksort($out, 'strnatcmp');

        return $out;
    }

    protected function headerLinks(): array
    {
        return [['label' => 'Impor guru (CSV)', 'url' => site_url('imports#teachers'), 'icon' => 'upload']];
    }

    protected function fields(?array $row): array
    {
        return [
            'name'      => ['label' => 'Nama lengkap (dengan gelar)', 'rules' => 'required|max_length[100]', 'span' => 'full'],
            'email'     => ['label' => 'Email login', 'type' => 'email', 'rules' => 'required|valid_email|max_length[150]|is_unique[users.email,id,{id}]'],
            'nip'       => ['label' => 'NIP', 'rules' => 'permit_empty|max_length[30]'],
            'teacher_code' => ['label' => 'Kode guru', 'rules' => 'permit_empty|alpha_numeric|max_length[10]|is_unique[users.teacher_code,id,{id}]', 'placeholder' => 'mis. 15',
                'help' => 'Nomor guru pada jadwal pelajaran sekolah. Tampil di tabel Jadwal Keseluruhan.'],
            'gender'    => ['label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan'], 'rules' => 'permit_empty|in_list[L,P]', 'empty' => '— Pilih —',
                'help' => 'Menentukan foto bawaan bila pengguna belum mengunggah foto.'],
            'role'      => ['label' => 'Peran', 'type' => 'select', 'options' => Auth::ROLES, 'rules' => 'required|in_list[teacher,admin]', 'default' => 'teacher'],
            'password'  => ['label' => $row ? 'Kata sandi baru (opsional)' : 'Kata sandi awal', 'type' => 'password', 'virtual' => true,
                'rules' => ($row ? 'permit_empty' : 'required') . '|min_length[10]|max_length[200]', 'attrs' => ['autocomplete' => 'new-password'],
                'help' => $row ? 'Isi hanya untuk mengatur ulang kata sandi pengguna ini.' : 'Minimal 10 karakter. Minta pengguna menggantinya setelah login.'],
            'is_active' => ['label' => 'Akun aktif (dapat login)', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function formValues(?array $row): array
    {
        if ($row) {
            unset($row['password_hash']);
        }

        return $row ?? [];
    }

    protected function columns(): array
    {
        return [
            'name'      => ['label' => 'Nama', 'class' => 'col-main', 'fn' => static fn ($r) => '<div class="flex items-center gap-3">' . user_avatar($r, 'size-9')
                . '<div class="min-w-0"><strong>' . esc($r['name']) . '</strong><span class="block text-sm text-muted">' . esc($r['email']) . '</span></div></div>'],
            'nip'       => ['label' => 'NIP / Kode', 'fn' => static fn ($r) => esc($r['nip'] ?? '') . (($r['teacher_code'] ?? '') !== '' ? ' <span class="badge badge-neutral">Kode ' . esc($r['teacher_code']) . '</span>' : '')],
            'gender'    => ['label' => 'L/P', 'fn' => static fn ($r) => esc($r['gender'] ?? '—')],
            'subjects'  => ['label' => 'Mapel & kelas diajarkan', 'fn' => fn ($r) => $r['role'] !== 'teacher' ? '<span class="text-muted">—</span>'
                : (($n = $this->subjectNames[(int) $r['id']] ?? []) ? '<ul class="grid max-w-72 gap-1">' . implode('', array_map(
                    fn ($sid, $name) => '<li class="flex flex-wrap items-center gap-1">' . subject_chip($this->subjectCodes[$sid] ?? null, $name)
                        . (($cl = $this->classNames[(int) $r['id']][$sid] ?? []) ? '<span class="text-xs text-muted">' . esc(implode(', ', $cl)) . '</span>' : '<span class="text-xs text-muted">kelas belum diatur</span>') . '</li>',
                    array_keys($n), $n)) . '</ul>' : '<span class="text-xs text-muted">Belum diatur</span>')],
            'role'      => ['label' => 'Peran', 'fn' => static fn ($r) => badge(Auth::ROLES[$r['role']] ?? $r['role'], $r['role'] === 'admin' ? 'neutral' : 'info')],
            'agenda'    => ['label' => 'Agenda', 'class' => 'num', 'fn' => static fn ($r) => $r['role'] === 'teacher' ? (int) $r['agenda_count'] : '—'],
            'last'      => ['label' => 'Login terakhir', 'fn' => static fn ($r) => $r['last_login_at'] ? fmt_date(substr($r['last_login_at'], 0, 10)) : '<span class="text-muted">Belum pernah</span>'],
            'is_active' => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif', 'warning')],
        ];
    }

    protected function prepare(array $data, ?array $old)
    {
        $data['email'] = strtolower(trim((string) $data['email']));
        if (array_key_exists('teacher_code', $data)) {
            $data['teacher_code'] = trim((string) $data['teacher_code']) ?: null;
        }
        $pw = (string) $this->request->getPost('password');
        if ($pw !== '') {
            $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        if (! $old) {
            $data['theme_pref'] = 'system';
        }
        // Centang "akun aktif" di formulir menyelaraskan status kepegawaian (mutasi/keluar tetap dipertahankan)
        if (array_key_exists('is_active', $data)) {
            $cur = $old['employment_status'] ?? 'aktif';
            if ((int) $data['is_active']) {
                $data['employment_status'] = 'aktif';
            } elseif ($cur === 'aktif') {
                $data['employment_status'] = 'nonaktif';
            }
        }
        if ($old && (int) $old['id'] === (int) Auth::id()) {
            if ($data['role'] !== 'admin' || ! (int) $data['is_active']) {
                return 'Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri.';
            }
        }
        if ($old && $old['role'] === 'admin' && ($data['role'] !== 'admin' || ! (int) $data['is_active']) && $this->activeAdmins((int) $old['id']) === 0) {
            return 'Harus ada minimal satu admin teknis aktif.';
        }

        return $data;
    }

    /** @var array<int, array<int, string>> user => [subject_id => nama] */
    private array $subjectNames = [];

    /** @var array<int, array<int, list<string>>> user => [subject_id => [kelas]] */
    private array $classNames = [];

    protected function beforeRenderRows(array $rows): void
    {
        $ids = array_map('intval', array_column($rows, 'id'));
        if (! $ids) {
            return;
        }
        foreach (db_connect()->table('user_subjects us')->select('us.user_id, us.subject_id, s.name, s.code')->join('subjects s', 's.id = us.subject_id')
            ->whereIn('us.user_id', $ids)->orderBy('s.name', 'ASC')->get()->getResultArray() as $r) {
            $this->subjectNames[(int) $r['user_id']][(int) $r['subject_id']] = $r['name'];
            $this->subjectCodes[(int) $r['subject_id']] = (string) ($r['code'] ?? '');
        }
        $classes = Lookup::classes();
        if ($classes) {
            foreach (db_connect()->table('user_teaching')->select('user_id, subject_id, class_group_id')->whereIn('user_id', $ids)
                ->whereIn('class_group_id', array_map('intval', array_keys($classes)))->get()->getResultArray() as $r) {
                $this->classNames[(int) $r['user_id']][(int) $r['subject_id']][] = $classes[$r['class_group_id']];
            }
        }
        $teachers       = array_map('intval', array_column(array_filter($rows, static fn ($r) => ($r['role'] ?? '') === 'teacher'), 'id'));
        $this->activity = TeacherActivity::forUsers($teachers);
    }

    /**
     * Buat kata sandi acak baru; ditampilkan SEKALI ke admin dan pengguna wajib menggantinya saat login.
     * Kata sandi tetap disimpan sebagai hash (tidak dapat dibaca ulang).
     */
    public function resetPassword(int $id)
    {
        $row = $this->findOr404($id);
        if ($id === (int) Auth::id()) {
            return redirect()->to(site_url('users'))->with('error', 'Ganti kata sandi Anda sendiri di menu Keamanan & Kata Sandi.');
        }
        $pw = self::randomPassword();
        (new UserModel())->update($id, ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 1]);
        Audit::log('password_reset', 'user', $id);

        return redirect()->to(site_url('users') . '#user-' . $id)->with('new_password', ['id' => $id, 'name' => $row['name'], 'email' => $row['email'], 'password' => $pw])
            ->with('success', 'Kata sandi baru untuk ' . $row['name'] . ' dibuat. Salin dan berikan kepada yang bersangkutan.');
    }

    /** 12 karakter acak tanpa huruf yang mudah tertukar (0/O, 1/l/I). */
    public static function randomPassword(int $len = 12): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out   = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    /** Ubah status: aktif / nonaktif / mutasi / keluar (riwayat agenda & nilai tetap tersimpan). */
    public function setStatus(int $id)
    {
        $row    = $this->findOr404($id);
        $status = (string) $this->request->getPost('status');
        if (! isset(self::STATUSES[$status])) {
            return redirect()->to(site_url('users'))->with('error', 'Status tidak dikenali.');
        }
        if ($id === (int) Auth::id() && $status !== 'aktif') {
            return redirect()->to(site_url('users'))->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }
        if ($row['role'] === 'admin' && $status !== 'aktif' && $this->activeAdmins($id) === 0) {
            return redirect()->to(site_url('users'))->with('error', 'Harus ada minimal satu admin teknis aktif.');
        }
        $date = (string) $this->request->getPost('status_date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : date('Y-m-d');
        $note = mb_substr(trim((string) $this->request->getPost('status_note')), 0, 255);
        (new UserModel())->update($id, [
            'employment_status' => $status,
            'is_active'         => $status === 'aktif' ? 1 : 0,
            'status_date'       => $status === 'aktif' ? null : $date,
            'status_note'       => $status === 'aktif' || $note === '' ? null : $note,
        ]);
        Audit::log('status_' . $status, 'user', $id);

        return redirect()->to(site_url('users') . '#user-' . $id)->with('success', $row['name'] . ': status diubah menjadi ' . self::STATUSES[$status][0] . '.');
    }

    /** @return list<string> */
    public static function subjectIds(int $userId): array
    {
        return array_map('strval', array_column(db_connect()->table('user_subjects')->select('subject_id')->where('user_id', $userId)->get()->getResultArray(), 'subject_id'));
    }

    /** Simpan ulang daftar mapel yang diajarkan guru. */
    public static function syncSubjects(int $userId, array $subjectIds): void
    {
        $valid = array_map('intval', array_keys(Lookup::subjects()));
        $ids   = array_values(array_unique(array_intersect(array_map('intval', array_filter($subjectIds)), $valid)));
        $db    = db_connect();
        $db->table('user_subjects')->where('user_id', $userId)->delete();
        if ($ids) {
            $db->table('user_subjects')->insertBatch(array_map(static fn ($s) => ['user_id' => $userId, 'subject_id' => $s], $ids));
        }
    }

    /**
     * Kelas yang diajar per mapel (tahun pelajaran aktif).
     *
     * @return array<int, list<string>> subject_id => [class_group_id]
     */
    public static function teachingMap(int $userId): array
    {
        $classes = Lookup::classes();
        if (! $classes) {
            return [];
        }
        $out = [];
        foreach (db_connect()->table('user_teaching')->select('subject_id, class_group_id')->where('user_id', $userId)
            ->whereIn('class_group_id', array_map('intval', array_keys($classes)))->get()->getResultArray() as $r) {
            $out[(int) $r['subject_id']][] = (string) $r['class_group_id'];
        }

        return $out;
    }

    /** Simpan ulang pembagian kelas per mapel untuk tahun pelajaran aktif (tahun lain tidak disentuh). */
    public static function syncTeaching(int $userId, array $teach): void
    {
        $subjects = array_map('intval', array_keys(Lookup::subjects()));
        $classes  = array_map('intval', array_keys(Lookup::classes()));
        $db       = db_connect();
        if ($classes) {
            $db->table('user_teaching')->where('user_id', $userId)->whereIn('class_group_id', $classes)->delete();
        }
        $rows = [];
        foreach ($teach as $sid => $cids) {
            if (! in_array((int) $sid, $subjects, true) || ! is_array($cids)) {
                continue;
            }
            foreach (array_unique(array_map('intval', $cids)) as $cid) {
                if (in_array($cid, $classes, true)) {
                    $rows[] = ['user_id' => $userId, 'subject_id' => (int) $sid, 'class_group_id' => $cid];
                }
            }
        }
        if ($rows) {
            $db->table('user_teaching')->insertBatch($rows);
        }
    }

    protected function formExtra(?array $row): array
    {
        $oldS = old('subject_ids', null, false);
        $oldT = old('teach', null, false);

        return [
            'subjects' => Lookup::subjects(),
            'classes'  => Lookup::classes(),
            'checked'  => is_array($oldS) ? array_map('strval', $oldS) : ($row ? self::subjectIds((int) $row['id']) : []),
            'teach'    => is_array($oldT) ? $oldT : ($row ? self::teachingMap((int) $row['id']) : []),
        ];
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        $teach    = (array) ($this->request->getPost('teach') ?? []);
        $subjects = array_merge((array) ($this->request->getPost('subject_ids') ?? []),
            array_keys(array_filter($teach, static fn ($c) => is_array($c) && array_filter($c))));
        self::syncSubjects($id, $subjects);
        self::syncTeaching($id, $teach);
        if (isset($data['password_hash']) && $old) {
            Audit::log('password_reset', 'user', $id);
        }
    }

    private function activeAdmins(int $exceptId): int
    {
        return (new UserModel())->where('role', 'admin')->where('is_active', 1)->where('id !=', $exceptId)->countAllResults();
    }

    protected function deleteBlocker(array $row): ?string
    {
        if ((int) $row['id'] === (int) Auth::id()) {
            return 'Anda tidak dapat menghapus akun Anda sendiri.';
        }
        if ($row['role'] === 'admin' && $this->activeAdmins((int) $row['id']) === 0) {
            return 'Harus ada minimal satu admin teknis aktif.';
        }
        $used = (new AgendaModel())->withDeleted()->where('user_id', $row['id'])->countAllResults()
            + (new AssessmentModel())->withDeleted()->where('user_id', $row['id'])->countAllResults();
        if ($used) {
            return 'Guru ini sudah memiliki agenda/asesmen. Nonaktifkan akunnya agar riwayat tetap tersimpan.';
        }

        return null;
    }
}
