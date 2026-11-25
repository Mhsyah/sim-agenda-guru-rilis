<?php

namespace App\Controllers;

use App\Libraries\MaterialFile;
use App\Libraries\Period;
use App\Models\TeacherActivityModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;

/** Kegiatan guru di luar kegiatan belajar mengajar (rapat, upacara, piket, pelatihan, kepanitiaan, …). */
class Activities extends CrudController
{
    /** Jenis kegiatan, dikelompokkan untuk pilihan di formulir. */
    public const GROUPS = [
        'Rapat & upacara' => [
            'rapat'   => 'Rapat (dinas/MGMP/komite)',
            'upacara' => 'Upacara / apel',
        ],
        'Piket & pendampingan' => [
            'piket'        => 'Piket guru',
            'pendampingan' => 'Pendampingan siswa',
            'ekskul'       => 'Ekstrakurikuler',
            'walikelas'    => 'Tugas wali kelas',
        ],
        'Pengembangan diri' => [
            'pelatihan' => 'Pelatihan / workshop / seminar',
            'diklat'    => 'Diklat / In-House Training',
        ],
        'Tugas tambahan & lainnya' => [
            'lomba'      => 'Lomba / pendampingan lomba',
            'panitia'    => 'Kepanitiaan',
            'dinas_luar' => 'Dinas luar',
            'lainnya'    => 'Lainnya',
        ],
    ];

    protected string $modelClass  = TeacherActivityModel::class;
    protected string $table       = 'teacher_activities';
    protected string $base        = 'activities';
    protected string $title       = 'Kegiatan di Luar KBM';
    protected string $singular    = 'Kegiatan';
    protected string $entity      = 'teacher_activity';
    protected ?string $intro      = 'Catat kegiatan di luar kegiatan belajar mengajar: rapat, upacara, piket, pendampingan, pelatihan, kepanitiaan, dan lainnya. Kegiatan ikut tercetak di Buku Jurnal.';
    protected array $searchFields = ['teacher_activities.title', 'teacher_activities.location', 'teacher_activities.description'];
    protected array $order        = ['activity_date', 'DESC', 'start_time', 'ASC'];
    protected bool $yearScoped    = true;
    protected bool $ownerScoped   = true;
    protected bool $archive       = false;
    protected bool $audit         = true;
    protected string $formView    = 'activities/form';

    private ?string $newFile  = null;
    private ?string $dropFile = null;
    private string $fileNote  = '';

    /** @return array<string, string> kode => label */
    public static function types(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** Kelompok dari satu jenis kegiatan (untuk warna/rekap). */
    public static function groupOf(string $type): string
    {
        foreach (self::GROUPS as $g => $items) {
            if (isset($items[$type])) {
                return $g;
            }
        }

        return 'Tugas tambahan & lainnya';
    }

    /** Formulir dibuka di popup Kalender? (sama dengan formulir agenda) */
    public static function isEmbed(): bool
    {
        return Agendas::isEmbed();
    }

    protected function filterDefs(): array
    {
        return ['activity_type' => ['label' => 'Jenis', 'options' => self::types()]];
    }

    protected function fields(?array $row): array
    {
        return [
            'activity_date' => ['label' => 'Tanggal', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => date('Y-m-d')],
            'activity_type' => ['label' => 'Jenis kegiatan', 'type' => 'select', 'options' => self::types(), 'rules' => 'required|in_list[' . implode(',', array_keys(self::types())) . ']', 'empty' => '— Pilih jenis —'],
            'title'         => ['label' => 'Nama kegiatan', 'rules' => 'required|max_length[150]', 'span' => 'full', 'placeholder' => 'mis. Rapat persiapan ATS'],
            'start_time'    => ['label' => 'Jam mulai', 'type' => 'time', 'rules' => 'permit_empty|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'],
            'end_time'      => ['label' => 'Jam selesai', 'type' => 'time', 'rules' => 'permit_empty|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'],
            'location'      => ['label' => 'Tempat', 'rules' => 'permit_empty|max_length[150]', 'span' => 'full', 'placeholder' => 'mis. Ruang guru'],
            'description'   => ['label' => 'Uraian / hasil kegiatan', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[3000]', 'span' => 'full'],
        ];
    }

    protected function columns(): array
    {
        return [
            'date'  => ['label' => 'Tanggal', 'fn' => static fn ($r) => fmt_date($r['activity_date'], false, true)
                . ($r['start_time'] ? '<span class="block text-xs text-muted">' . self::timeRange($r) . '</span>' : '')],
            'title' => ['label' => 'Kegiatan', 'class' => 'col-main', 'fn' => fn ($r) => '<strong>' . esc($r['title']) . '</strong>'
                . ($r['location'] ? '<span class="block text-sm text-muted">' . esc($r['location']) . '</span>' : '')
                . (! empty($r['material_file']) ? '<a class="mt-1 inline-flex items-center gap-1 text-xs font-semibold" target="_blank" rel="noopener" href="' . site_url('activities/' . $r['id'] . '/material') . '">' . icon('paperclip', 'size-3.5') . ' ' . excerpt((string) $r['material_name'], 40) . '</a>' : '')],
            'type'  => ['label' => 'Jenis', 'fn' => static fn ($r) => '<span class="badge badge-info">' . esc(self::types()[$r['activity_type']] ?? $r['activity_type']) . '</span>'],
            'desc'  => ['label' => 'Uraian', 'fn' => static fn ($r) => $r['description'] ? excerpt($r['description'], 80) : '<span class="text-muted">—</span>'],
        ];
    }

    public static function timeRange(array $r): string
    {
        if (empty($r['start_time'])) {
            return '';
        }

        return fmt_time($r['start_time']) . (! empty($r['end_time']) ? '–' . fmt_time($r['end_time']) : '');
    }

    protected function headerLinks(): array
    {
        return [['label' => 'Kalender & Agenda', 'url' => site_url('calendar'), 'icon' => 'calendar']];
    }

    protected function prepare(array $data, ?array $old)
    {
        if (! empty($data['start_time']) && ! empty($data['end_time']) && substr($data['end_time'], 0, 5) < substr($data['start_time'], 0, 5)) {
            return 'Jam selesai harus sama dengan atau setelah jam mulai.';
        }
        $data['semester_id'] = Period::semesterForDate($data['activity_date']);

        // Lampiran bukti (opsional) — foto/dokumen, dikompres seperti lampiran materi agenda.
        $file = $this->request->getFile('material');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
                return 'Unggahan lampiran gagal: ' . $file->getErrorString();
            }
            try {
                $m = MaterialFile::store($file->getTempName(), $file->getClientName());
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
            $this->fileNote = $m['note'];
            unset($m['note']);
            $data           = $m + $data;
            $this->newFile  = $m['material_file'];
            $this->dropFile = $old['material_file'] ?? null;
        } elseif ($old && $this->request->getPost('material_remove')) {
            $data['material_file'] = $data['material_name'] = $data['material_size'] = null;
            $this->dropFile        = $old['material_file'];
        }

        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
        if ($this->dropFile && $this->dropFile !== $this->newFile) {
            MaterialFile::delete($this->dropFile);
        }
        if ($this->fileNote !== '') {
            session()->setFlashdata('warning', 'Lampiran: ' . $this->fileNote);
        }
    }

    protected function redirectAfterSave(int $id): string
    {
        return self::isEmbed() ? site_url('agendas/embed-done') : parent::redirectAfterSave($id);
    }

    public function delete(int $id)
    {
        $row = $this->findOr404($id);
        $res = parent::delete($id);
        MaterialFile::delete($row['material_file'] ?? null);
        $from = (string) $this->request->getPost('from');
        if (preg_match('/^calendar:(\d{4}-\d{2})$/', $from, $m)) {
            return redirect()->to(site_url('calendar?month=' . $m[1]));
        }

        return $res;
    }

    /** Tampilkan/unduh lampiran milik guru yang sedang login. */
    public function material(int $id)
    {
        $row  = $this->findOr404($id);
        $path = MaterialFile::path($row['material_file'] ?? null);
        if (! $path) {
            throw PageNotFoundException::forPageNotFound('Lampiran tidak ditemukan.');
        }
        $ext    = MaterialFile::ext($path);
        $inline = in_array($ext, ['webp', 'pdf'], true) && ! $this->request->getGet('download');
        $name   = (string) ($row['material_name'] ?: 'lampiran.' . $ext);
        if ($ext !== 'pdf') {
            $this->response->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
        }

        return $this->response
            ->setHeader('Content-Type', MaterialFile::MIMES[$ext] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._ ()-]/', '_', $name) . "\"; filename*=UTF-8''" . rawurlencode($name))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=600')
            ->setBody((string) file_get_contents($path));
    }

    /**
     * Kegiatan guru dalam rentang tanggal (untuk kalender, Buku Jurnal, laporan).
     *
     * @return list<array>
     */
    public static function between(int $userId, string $from, string $to): array
    {
        return (new TeacherActivityModel())->where('user_id', $userId)->where('activity_date >=', $from)->where('activity_date <=', $to)
            ->orderBy('activity_date', 'ASC')->orderBy('start_time', 'ASC')->findAll();
    }
}
