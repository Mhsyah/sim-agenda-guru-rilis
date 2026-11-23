<?php

namespace App\Controllers;

use App\Libraries\Attendance;
use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\MaterialFile;
use App\Libraries\Period;
use App\Models\AgendaModel;
use CodeIgniter\Model;

class Agendas extends CrudController
{
    protected string $modelClass = AgendaModel::class;
    protected string $table      = 'teaching_agendas';
    protected string $base       = 'agendas';
    protected string $title      = 'Agenda & Jurnal Mengajar';
    protected string $singular   = 'Agenda';
    protected string $entity     = 'teaching_agenda';
    protected ?string $intro     = 'Catat kegiatan, kehadiran siswa, refleksi, dan tindak lanjut setiap pertemuan. Pilih TP agar pembelajaran mudah ditelusuri.';
    protected array $searchFields = ['teaching_agendas.topic', 'teaching_agendas.activities', 'teaching_agendas.reflection'];
    protected array $order       = ['agenda_date', 'DESC', 'teaching_agendas.id', 'DESC'];
    protected bool $audit        = true;
    protected bool $ownerScoped  = true;
    protected bool $taughtOnly   = true;
    protected string $formView   = 'agendas/form';
    protected ?string $deleteLabel   = 'Hapus';
    protected ?string $deleteConfirm = 'Hapus agenda (pokok bahasan) ini? Daftar hadirnya ikut disembunyikan dan agenda tidak lagi muncul di Buku Jurnal.';

    /** @var array<int, list<array{0: string, 1: string}>> */
    private array $absent = [];

    /** File materi lama yang dihapus setelah penyimpanan berhasil / file baru yang dibatalkan bila gagal. */
    private ?string $dropMaterial = null;
    private ?string $newMaterial  = null;
    private string $materialNote  = '';

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
            'class_group_id' => ['label' => 'Kelas', 'options' => Lookup::teachingClasses()],
            'subject_id'     => ['label' => 'Mapel', 'options' => Lookup::teachingSubjects()],
            'semester_id'    => ['label' => 'Semester', 'options' => Lookup::semesters(), 'default' => (string) Period::semesterId()],
            'status'         => ['label' => 'Status', 'options' => ['selesai' => 'Terlaksana', 'terlambat' => 'Guru terlambat', 'batal' => 'Guru berhalangan', 'draf' => 'Draf']],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $at = db_connect()->prefixTable('attendances');
        $ag = db_connect()->prefixTable('teaching_agendas');
        $m->select('teaching_agendas.*, cg.name AS class_name, s.name AS subject_name, lo.code AS tp_code')
            ->select("(SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = {$ag}.id) AS att_total,
                (SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = {$ag}.id AND x.status = 'H') AS att_present", false)
            ->join('class_groups cg', 'cg.id = teaching_agendas.class_group_id')
            ->join('subjects s', 's.id = teaching_agendas.subject_id')
            ->join('semesters sm', 'sm.id = teaching_agendas.semester_id')
            ->join('learning_objectives lo', 'lo.id = teaching_agendas.learning_objective_id', 'left')
            ->where('sm.academic_year_id', Period::yearId());

        return parent::listQuery($m, $filters);
    }

    /** Status pertemuan sesuai petunjuk Jurnal Agenda Guru (berhalangan/terlambat tetap dicatat beserta sebabnya). */
    public const STATUSES = [
        'selesai'   => 'Terlaksana',
        'terlambat' => 'Terlaksana (guru terlambat datang)',
        'batal'     => 'Guru berhalangan / tidak terlaksana',
        'draf'      => 'Draf',
    ];

    public const ASSESSMENT_KINDS = ['formatif' => 'Tes formatif', 'sumatif' => 'Sumatif', 'sub_sumatif' => 'Sub sumatif'];

    protected function fields(?array $row): array
    {
        return [
            'agenda_date'           => ['label' => 'Hari/tanggal', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => date('Y-m-d')],
            'lesson_hours'          => ['label' => 'Jam ke', 'rules' => 'permit_empty|max_length[20]|regex_match[/^[0-9][0-9 ,\-–]*$/]', 'placeholder' => '1-2', 'help' => 'Contoh: 3 atau 1-2. Terisi otomatis dari jadwal mengajar.'],
            'class_group_id'        => ['label' => 'Kelas', 'type' => 'select', 'options' => $cls = Lookup::teachingClasses(isset($row['class_group_id']) ? (int) $row['class_group_id'] : null),
                'rules' => 'required|in_list[' . implode(',', array_keys($cls) ?: [0]) . ']', 'empty' => '— Pilih kelas —', 'errors' => ['in_list' => 'Pilih kelas yang Anda ajar.']],
            'subject_id'            => ['label' => 'Mata pelajaran', 'type' => 'select', 'options' => $subj = Lookup::teachingSubjectsWith(isset($row['subject_id']) ? (int) $row['subject_id'] : null),
                'rules' => 'required|in_list[' . implode(',', array_keys($subj)) . ']', 'empty' => '— Pilih mapel —', 'errors' => ['in_list' => 'Pilih mata pelajaran yang Anda ajarkan.']],
            'learning_objective_id' => ['label' => 'No. KI/KD / TP (opsional)', 'type' => 'select', 'groups' => Lookup::objectivesForSemester(isset($row['learning_objective_id']) ? (int) $row['learning_objective_id'] : null), 'filterBy' => 'subject_id', 'rules' => 'permit_empty|is_natural_no_zero', 'empty' => '— Tanpa TP —', 'span' => 'full'],
            'topic'                 => ['label' => 'Pokok bahasan/materi', 'rules' => 'required|max_length[200]', 'span' => 'full'],
            'assessment_kind'       => ['label' => 'Tes formatif/sumatif/sub sumatif', 'type' => 'select', 'options' => self::ASSESSMENT_KINDS, 'rules' => 'permit_empty|in_list[formatif,sumatif,sub_sumatif]', 'empty' => '— Tidak ada tes —'],
            'status'                => ['label' => 'Status pertemuan', 'type' => 'select', 'options' => self::STATUSES, 'rules' => 'required|in_list[' . implode(',', array_keys(self::STATUSES)) . ']', 'default' => 'selesai'],
            'meeting_no'            => ['label' => 'Pertemuan ke', 'type' => 'number', 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[999]', 'attrs' => ['min' => 1, 'step' => 1], 'help' => 'Kosongkan untuk dihitung otomatis.'],
            'meeting_total'         => ['label' => 'Banyak pertemuan', 'type' => 'number', 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[999]', 'attrs' => ['min' => 1, 'step' => 1], 'help' => 'Rencana jumlah pertemuan untuk materi ini.'],
            'completion'            => ['label' => 'Materi selesai / belum selesai', 'type' => 'select', 'options' => ['selesai' => 'Selesai', 'belum' => 'Belum selesai'], 'rules' => 'permit_empty|in_list[selesai,belum]', 'default' => 'selesai'],
            'incomplete_reason'     => ['label' => 'Alasan belum selesai', 'rules' => 'permit_empty|max_length[255]', 'help' => 'Wajib bila materi belum selesai.'],
            'activities'            => ['label' => 'Kegiatan pembelajaran', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[5000]', 'span' => 'full'],
            'reflection'            => ['label' => 'Refleksi', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[5000]'],
            'follow_up'             => ['label' => 'Tindak lanjut', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[5000]'],
            'remarks'               => ['label' => 'Keterangan (KET)', 'rules' => 'permit_empty|max_length[255]', 'span' => 'full', 'help' => 'Wajib dijelaskan bila guru berhalangan mengajar atau terlambat datang.'],
        ];
    }

    protected function headerLinks(): array
    {
        $q = http_build_query(array_filter($this->currentFilters(), static fn ($v) => $v !== ''));

        return [
            ['label' => 'Cetak Buku Jurnal', 'url' => site_url('reports/journal'), 'icon' => 'printer'],
            ['label' => 'Ekspor CSV', 'url' => site_url('agendas/export' . ($q ? '?' . $q : '')), 'icon' => 'download'],
        ];
    }

    /** Nilai awal formulir baru: jam ke dari jadwal, pertemuan ke & banyak pertemuan dari agenda sebelumnya. */
    protected function formValues(?array $row): array
    {
        if ($row) {
            return $row;
        }
        $classId   = (int) $this->request->getGet('class_group_id');
        $subjectId = (int) $this->request->getGet('subject_id');
        $date      = (string) ($this->request->getGet('agenda_date') ?: date('Y-m-d'));
        $out       = [];
        if ($classId && $subjectId) {
            $sched = (new \App\Models\ScheduleModel())->where('academic_year_id', Period::yearId())->where('user_id', Auth::id())
                ->where('class_group_id', $classId)->where('subject_id', $subjectId)
                ->where('day_of_week', (int) date('N', strtotime($date)))->orderBy('start_time', 'ASC')->first();
            if ($sched && $sched['lesson_hours']) {
                $out['lesson_hours'] = $sched['lesson_hours'];
            }
            $out['meeting_no'] = $this->nextMeetingNo($classId, $subjectId, $date, null);
            $last = (new AgendaModel())->where('user_id', Auth::id())->where('class_group_id', $classId)->where('subject_id', $subjectId)
                ->where('meeting_total IS NOT NULL', null, false)->orderBy('agenda_date', 'DESC')->first();
            if ($last && $last['completion'] === 'belum') {
                $out['meeting_total'] = $last['meeting_total'];
                $out['learning_objective_id'] = $last['learning_objective_id'];
                $out['topic'] = $last['topic'];
            }
        }

        return $out;
    }

    /** Pertemuan ke-n = jumlah pertemuan terlaksana sebelumnya (kelas, mapel, semester) + 1. */
    private function nextMeetingNo(int $classId, int $subjectId, string $date, ?int $excludeId): int
    {
        $m = (new AgendaModel())->where('user_id', Auth::id())->where('class_group_id', $classId)->where('subject_id', $subjectId)
            ->where('semester_id', Period::semesterForDate($date))
            ->whereIn('status', ['selesai', 'terlambat'])->where('agenda_date <', $date);
        if ($excludeId) {
            $m->where('id !=', $excludeId);
        }

        return $m->countAllResults() + 1;
    }

    protected function columns(): array
    {
        return [
            'agenda_date' => ['label' => 'Tanggal', 'fn' => static fn ($r) => '<span class="whitespace-nowrap">' . fmt_date($r['agenda_date'], false, true) . '</span>'],
            'class'       => ['label' => 'Kelas · Mapel', 'fn' => static fn ($r) => esc($r['class_name']) . '<span class="block text-sm text-muted">' . esc($r['subject_name']) . '</span>'
                . '<span class="block text-xs text-muted">' . ($r['lesson_hours'] ? 'Jam ke ' . esc($r['lesson_hours']) : '') . ($r['meeting_no'] ? ($r['lesson_hours'] ? ' · ' : '') . 'Pert. ' . (int) $r['meeting_no'] . ($r['meeting_total'] ? '/' . (int) $r['meeting_total'] : '') : '') . '</span>'],
            'topic'       => ['label' => 'Pokok bahasan', 'class' => 'col-main', 'fn' => static fn ($r) => '<strong>' . esc($r['topic']) . '</strong>' . ($r['tp_code'] ? ' ' . badge($r['tp_code'], 'info') : '')
                . ($r['assessment_kind'] ? ' ' . badge(self::ASSESSMENT_KINDS[$r['assessment_kind']] ?? $r['assessment_kind'], 'neutral') : '')
                . ($r['completion'] === 'belum' ? '<span class="block text-sm text-warn">Belum selesai' . ($r['incomplete_reason'] ? ': ' . esc($r['incomplete_reason']) : '') . '</span>' : '')
                . ($r['remarks'] ? '<span class="block text-sm text-muted">KET: ' . excerpt($r['remarks'], 80) . '</span>' : '')
                . (! empty($r['material_file']) ? '<a class="mt-1 inline-flex items-center gap-1 text-xs font-semibold" target="_blank" rel="noopener" href="' . site_url('agendas/' . $r['id'] . '/material') . '">' . icon('paperclip', 'size-3.5') . ' ' . excerpt((string) $r['material_name'], 40) . '</a>' : '')],
            'attendance'  => ['label' => 'Kehadiran', 'fn' => function ($r) {
                $total = (int) $r['att_total'];
                if ($r['status'] === 'batal') {
                    return '<span class="text-muted">—</span>';
                }
                if ($total === 0) {
                    return badge('Belum diisi', 'neutral');
                }
                $present = (int) $r['att_present'];
                $html    = badge($present . '/' . $total . ' hadir', $present === $total ? 'success' : 'warning');
                if ($names = $this->absent[(int) $r['id']] ?? []) {
                    $list = array_map(static fn ($n) => esc($n[0]) . ' (' . $n[1] . ')', array_slice($names, 0, 4));
                    $html .= '<span class="mt-1 block max-w-56 text-xs text-muted">' . implode(', ', $list) . (count($names) > 4 ? ', +' . (count($names) - 4) : '') . '</span>';
                }

                return $html;
            }],
            'status' => ['label' => 'Status', 'fn' => static fn ($r) => match ($r['status']) {
                'selesai' => badge('Terlaksana', 'success'), 'terlambat' => badge('Guru terlambat', 'warning'),
                'draf' => badge('Draf', 'neutral'), default => badge('Guru berhalangan', 'danger')
            }],
        ];
    }

    protected function prepare(array $data, ?array $old)
    {
        $year = Period::get()['year'];
        if ($data['agenda_date'] < $year['start_date'] || $data['agenda_date'] > $year['end_date']) {
            return 'Tanggal agenda berada di luar tahun ajaran aktif (' . $year['name'] . ').';
        }
        $sid = Period::semesterForDate($data['agenda_date']);
        if (! $sid) {
            return 'Belum ada semester pada tahun ajaran aktif. Tambahkan semester terlebih dahulu.';
        }
        foreach ((array) ($this->request->getPost('att') ?? []) as $v) {
            if (! isset(Attendance::STATUSES[strtoupper((string) $v)])) {
                return 'Status kehadiran tidak valid.';
            }
        }
        if (in_array($data['status'], ['batal', 'terlambat'], true) && empty($data['remarks'])) {
            return 'Jelaskan sebab guru berhalangan mengajar atau terlambat datang pada kolom Keterangan (KET).';
        }
        $data['completion'] = $data['completion'] ?: 'selesai';
        if ($data['completion'] === 'belum' && empty($data['incomplete_reason'])) {
            return 'Isi alasan materi belum selesai.';
        }
        if ($data['completion'] === 'selesai') {
            $data['incomplete_reason'] = null;
        }
        if (! empty($data['meeting_no']) && ! empty($data['meeting_total']) && (int) $data['meeting_no'] > (int) $data['meeting_total']) {
            return 'Pertemuan ke tidak boleh melebihi banyak pertemuan.';
        }
        $data['semester_id'] = $sid;
        if (empty($data['meeting_no']) && $data['status'] !== 'batal') {
            $data['meeting_no'] = $this->nextMeetingNo((int) $data['class_group_id'], (int) $data['subject_id'], $data['agenda_date'], $old ? (int) $old['id'] : null);
        }

        // Lampiran materi (opsional): diproses terakhir agar tidak ada file yatim bila validasi lain gagal.
        $file = $this->request->getFile('material');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
                return 'Unggahan materi gagal: ' . $file->getErrorString();
            }
            try {
                $m = MaterialFile::store($file->getTempName(), $file->getClientName());
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
            $this->materialNote = $m['note'];
            unset($m['note']);
            $data = $m + $data;
            $this->newMaterial  = $m['material_file'];
            $this->dropMaterial = $old['material_file'] ?? null;
        } elseif ($old && $this->request->getPost('material_remove')) {
            $data['material_file'] = $data['material_name'] = $data['material_size'] = null;
            $this->dropMaterial    = $old['material_file'];
        }

        return $data;
    }

    /** Tugas harian yang sudah dibuat untuk pertemuan ini (guru, tanggal, kelas, mapel sama). */
    public static function taskFor(array $agenda): ?array
    {
        $type = (new \App\Models\AssessmentTypeModel())->where('code', 'TGS')->first();
        if (! $type) {
            return null;
        }

        return (new \App\Models\AssessmentModel())->where('assessment_type_id', $type['id'])->where('user_id', (int) ($agenda['user_id'] ?? Auth::id()))
            ->where('assessment_date', $agenda['agenda_date'])->where('class_group_id', (int) $agenda['class_group_id'])
            ->where('subject_id', (int) $agenda['subject_id'])->first();
    }

    /** "Ada tugas harian" di agenda → buat asesmen TGS (bila belum ada) dan tampilkan tautan input nilai. */
    private function createDailyTask(array $data): void
    {
        $type = (new \App\Models\AssessmentTypeModel())->where('code', 'TGS')->first();
        if (! $type) {
            return;
        }
        $data['user_id'] = (int) Auth::id();
        $task = self::taskFor($data);
        if (! $task) {
            $title = trim((string) $this->request->getPost('task_title')) ?: 'Tugas — ' . mb_substr((string) $data['topic'], 0, 120);
            $tid   = (int) (new \App\Models\AssessmentModel())->insert([
                'assessment_type_id' => $type['id'], 'academic_year_id' => Period::yearId(), 'semester_id' => $data['semester_id'] ?? Period::semesterId(),
                'class_group_id' => $data['class_group_id'], 'subject_id' => $data['subject_id'], 'learning_objective_id' => $data['learning_objective_id'] ?: null,
                'title' => mb_substr($title, 0, 150), 'assessment_date' => $data['agenda_date'], 'max_score' => 100, 'status' => 'terbuka', 'user_id' => $data['user_id'],
                'missing_policy' => $this->request->getPost('task_policy') === 'abaikan' ? 'abaikan' : 'nol',
            ]);
            Audit::log('create', 'assessment', $tid, null, ['title' => $title, 'dari' => 'agenda']);
            $task = ['id' => $tid, 'title' => $title];
        }
        session()->setFlashdata('action', ['text' => 'Tugas harian "' . $task['title'] . '" tercatat untuk pertemuan ini.', 'url' => site_url('assessments/' . $task['id'] . '/scores'), 'label' => 'Input nilai tugas']);
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        if ($this->dropMaterial) {
            MaterialFile::delete($this->dropMaterial);
        }
        if ($this->materialNote !== '') {
            session()->setFlashdata('warning', 'Lampiran materi: ' . $this->materialNote);
        }
        if ($data['status'] === 'batal') {
            Attendance::clear($id);

            return;
        }
        if ($this->request->getPost('give_task')) {
            $this->createDailyTask($data);
        }
        $before = $old ? Attendance::byStudent($id) : [];
        // Bila kelas berubah, daftar lama tidak relevan lagi.
        if ($old && (int) $old['class_group_id'] !== (int) $data['class_group_id']) {
            Attendance::clear($id);
            $before = [];
        }
        $count = Attendance::save(
            $id,
            (int) $data['class_group_id'],
            (array) ($this->request->getPost('att') ?? []),
            (array) ($this->request->getPost('att_note') ?? [])
        );
        $after   = Attendance::byStudent($id);
        $changed = [];
        foreach ($after as $sid => $a) {
            if (($before[$sid]['status'] ?? null) !== $a['status']) {
                $changed[$sid] = [$before[$sid]['status'] ?? null, $a['status']];
            }
        }
        if ($changed) {
            Audit::log('attendance_update', 'teaching_agenda', $id,
                array_map(static fn ($c) => $c[0], $changed), array_map(static fn ($c) => $c[1], $changed));
        }
        $total = array_sum($count);
        if ($total > 0) {
            session()->setFlashdata('info', 'Kehadiran: ' . $count['H'] . '/' . $total . ' hadir (' . Attendance::summaryText($count) . ').');
        }
    }

    protected function formExtra(?array $row): array
    {
        $classId = (int) (old('class_group_id', null, false) ?? $row['class_group_id'] ?? $this->request->getGet('class_group_id') ?? 0);
        $date    = (string) (old('agenda_date', null, false) ?? $row['agenda_date'] ?? $this->request->getGet('agenda_date') ?? date('Y-m-d'));

        return [
            'view'     => 'agendas/attendance',
            'slots'    => Schedules::grouped(Period::yearId(), (int) date('N', strtotime($date)), Auth::id())[(int) date('N', strtotime($date))] ?? [],
            'roster'   => $classId ? $this->rosterWithOld($classId, $date, $row ? (int) $row['id'] : null) : [],
            'classId'  => $classId,
            'agendaId' => $row['id'] ?? null,
        ];
    }

    /** Pertahankan pilihan pengguna bila formulir dikembalikan karena galat validasi. */
    private function rosterWithOld(int $classId, string $date, ?int $agendaId): array
    {
        $roster = Attendance::roster($classId, $date, $agendaId);
        $oldAtt = old('att', null, false);
        $oldNt  = old('att_note', null, false);
        if (is_array($oldAtt)) {
            foreach ($roster as &$r) {
                $r['status'] = $oldAtt[$r['id']] ?? $r['status'];
                $r['notes']  = $oldNt[$r['id']] ?? $r['notes'];
            }
            unset($r);
        }

        return $roster;
    }

    /** Tampilkan/unduh lampiran materi milik guru yang sedang login. */
    public function material(int $id)
    {
        $row  = $this->findOr404($id);
        $path = MaterialFile::path($row['material_file'] ?? null);
        if (! $path) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Lampiran tidak ditemukan.');
        }
        $ext    = MaterialFile::ext($path);
        $inline = in_array($ext, ['webp', 'pdf'], true) && ! $this->request->getGet('download');
        $name   = (string) ($row['material_name'] ?: 'materi.' . $ext);

        if ($ext !== 'pdf') { // CSP sandbox akan memblokir penampil PDF bawaan browser
            $this->response->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
        }

        return $this->response
            ->setHeader('Content-Type', MaterialFile::MIMES[$ext] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._ ()-]/', '_', $name) . "\"; filename*=UTF-8''" . rawurlencode($name))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=600')
            ->setBody((string) file_get_contents($path));
    }

    /** Fragmen daftar hadir (dipanggil JS saat kelas/tanggal diganti). */
    public function attendanceFragment()
    {
        $classId  = (int) $this->request->getGet('class_group_id');
        $date     = (string) $this->request->getGet('agenda_date');
        $agendaId = (int) $this->request->getGet('agenda_id') ?: null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        return view('agendas/attendance_list', [
            'roster' => $classId ? Attendance::roster($classId, $date, $agendaId) : [],
        ]);
    }

    public function export()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $filters = $this->currentFilters();
        $rows    = $this->listQuery((new AgendaModel())->where('teaching_agendas.user_id', Auth::id()), $filters)->orderBy('agenda_date', 'ASC')->findAll();
        $absent  = Attendance::absentees(array_map('intval', array_column($rows, 'id')));

        return csv_response('agenda-' . date('Ymd') . '.csv', ['Tanggal', 'Jam ke', 'Kelas', 'Mapel', 'No. KI/KD/TP', 'Pokok bahasan', 'Tes', 'Banyak pertemuan', 'Pertemuan ke', 'Selesai/Belum', 'Alasan belum selesai', 'Kegiatan', 'Refleksi', 'Tindak lanjut', 'Status', 'KET', 'Hadir', 'Tidak hadir'], array_map(
            static fn ($r) => [$r['agenda_date'], $r['lesson_hours'], $r['class_name'], $r['subject_name'], $r['tp_code'], $r['topic'], self::ASSESSMENT_KINDS[$r['assessment_kind'] ?? ''] ?? '',
                $r['meeting_total'], $r['meeting_no'], $r['completion'] === 'belum' ? 'Belum selesai' : 'Selesai', $r['incomplete_reason'],
                $r['activities'], $r['reflection'], $r['follow_up'], self::STATUSES[$r['status']] ?? $r['status'], $r['remarks'],
                $r['att_present'] . '/' . $r['att_total'],
                implode(', ', array_map(static fn ($n) => $n[0] . ' (' . $n[1] . ')', $absent[(int) $r['id']] ?? []))],
            $rows
        ));
    }

    /** Hook dari CrudController: siapkan daftar siswa tidak hadir untuk baris tabel. */
    protected function beforeRenderRows(array $rows): void
    {
        $this->absent = Attendance::absentees(array_map('intval', array_column($rows, 'id')));
    }

    /** Tab "Kalender | Daftar Agenda" di atas daftar agenda. */
    protected function extraIndexData(array $filters): array
    {
        return ['agendaTabs' => 'list'];
    }

    /** Hapus dari popup Kalender: kembali ke bulan kalender yang sama. */
    public function delete(int $id)
    {
        $res  = parent::delete($id);
        $from = (string) $this->request->getPost('from');
        if (preg_match('/^calendar:(\d{4}-\d{2})$/', $from, $m)) {
            return redirect()->to(site_url('calendar?month=' . $m[1]));
        }

        return $res;
    }

    /** Formulir dibuka dari popup Kalender (iframe)? */
    public static function isEmbed(): bool
    {
        $r = service('request');

        return $r->getGet('embed') === '1' || $r->getPost('embed') === '1';
    }

    protected function redirectAfterSave(int $id): string
    {
        return self::isEmbed() ? site_url('agendas/embed-done') : parent::redirectAfterSave($id);
    }

    /** Setelah simpan dari popup: pesan sukses tetap disimpan untuk halaman kalender yang dimuat ulang. */
    public function embedDone()
    {
        // Pesan diteruskan ke kalender lewat postMessage (bukan sesi), lalu ditampilkan setelah kalender dimuat ulang
        $act = session()->getFlashdata('action');

        return view('agendas/embed_done', [
            'msg'    => (string) (session()->getFlashdata('success') ?? 'Agenda tersimpan.'),
            'action' => is_array($act) && ! empty($act['url']) ? ['text' => (string) ($act['text'] ?? ''), 'url' => (string) $act['url'], 'label' => (string) ($act['label'] ?? 'Buka')] : null,
        ]);
    }
}
