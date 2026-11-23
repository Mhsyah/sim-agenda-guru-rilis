<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\MasterImporter;
use App\Libraries\Period;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * CP, TP, ATP & KKTP.
 * - Guru: impor data kurikulum mapel yang diajarkan (dengan pratinjau) dan arsip dokumen.
 * - Admin: arsip dokumen semua mapel (dibagikan ke guru pengampu); impor data lewat menu Impor Data.
 */
class Curriculum extends BaseController
{
    public const DOC_TYPES = ['cp' => 'CP', 'tp' => 'TP', 'atp' => 'ATP', 'kktp' => 'KKTP', 'lain' => 'Lainnya'];
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const EXT = ['pdf' => '%PDF', 'docx' => "PK\x03\x04", 'xlsx' => "PK\x03\x04", 'pptx' => "PK\x03\x04"];

    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return $this->render('curriculum/index', [
            'title'    => 'Unggah CP, TP, ATP & KKTP',
            'meta'     => MasterImporter::TYPES['curriculum'],
            'subjects' => Lookup::teachingSubjects(),
            'result'   => session('import_type') === 'curriculum' ? session('import_result') : null,
            'docs'     => $this->docList(),
            'summary'  => $this->summary(),
        ]);
    }

    /** Halaman dokumen untuk admin (guru memakai halaman index). */
    public function docs()
    {
        if (! Auth::isAdmin()) {
            return redirect()->to(site_url('curriculum#dokumen'));
        }

        return $this->render('curriculum/docs', [
            'title'    => 'Dokumen CP, TP, ATP & KKTP',
            'subjects' => Lookup::subjects(false),
            'docs'     => $this->docList(),
        ]);
    }

    /** Mapel yang boleh dipakai pengguna ini. */
    private function allowedSubjects(): array
    {
        return Auth::isAdmin() ? Lookup::subjects(false) : Lookup::teachingSubjects();
    }

    private function docList(): array
    {
        // Dihitung dulu: kueri lain (Lookup/Period/Auth) di tengah penyusunan mereset alias tabel
        $allowed = array_keys($this->allowedSubjects());
        $yid     = (int) Period::yearId();
        $admin   = Auth::isAdmin();
        $uid     = (int) Auth::id();
        $q = db_connect()->table('curriculum_documents d')->select('d.*, s.name AS subject_name, u.name AS uploader')
            ->join('subjects s', 's.id = d.subject_id')->join('users u', 'u.id = d.user_id', 'left')
            ->where('d.academic_year_id', $yid);
        if (! $admin) {
            $q->whereIn('d.subject_id', $allowed ?: [0])
                ->groupStart()->where('d.user_id', null)->orWhere('d.user_id', $uid)->groupEnd();
        }
        if ($sem = (int) $this->request->getGet('semester_id')) {
            $q->groupStart()->where('d.semester_id', $sem)->orWhere('d.semester_id', null)->groupEnd();
        }

        return $q->orderBy('s.name', 'ASC')->orderBy('d.doc_type', 'ASC')->orderBy('d.id', 'DESC')->get()->getResultArray();
    }

    /** Jumlah CP/TP/KKTP per mapel yang diajar (ringkasan di halaman guru). */
    private function summary(): array
    {
        $db  = db_connect();
        $yid = (int) Period::yearId();
        $out = [];
        foreach ($this->allowedSubjects() as $sid => $name) {
            $tp = $db->table('learning_objectives')->where('academic_year_id', $yid)->where('subject_id', $sid)->where('deleted_at', null);
            $out[] = [
                'id' => $sid, 'name' => $name,
                'cp' => $db->table('learning_outcomes')->where('academic_year_id', $yid)->where('subject_id', $sid)->where('deleted_at', null)->countAllResults(),
                'tp' => (clone $tp)->countAllResults(),
                'atp' => (clone $tp)->where('atp_order IS NOT NULL', null, false)->countAllResults(),
                'kktp' => (clone $tp)->groupStart()->where('kktp_criteria IS NOT NULL', null, false)->orWhere('kktp_intervals IS NOT NULL', null, false)->groupEnd()->countAllResults(),
            ];
        }

        return $out;
    }

    private static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'curriculum' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
        }

        return $dir;
    }

    public function upload()
    {
        $back = Auth::isAdmin() ? site_url('curriculum/docs') : site_url('curriculum#dokumen');
        if (! Period::yearId()) {
            return redirect()->to($back)->with('error', 'Belum ada tahun pelajaran aktif.');
        }
        $sid   = (int) $this->request->getPost('subject_id');
        $type  = (string) $this->request->getPost('doc_type');
        $phase = strtoupper(trim((string) $this->request->getPost('phase')));
        $title = trim((string) $this->request->getPost('title'));
        $semId = (int) $this->request->getPost('semester_id') ?: null;
        if ($semId && ! isset(Lookup::semesters()[$semId])) {
            return redirect()->to($back)->with('error', 'Semester tidak valid.');
        }
        if (! isset($this->allowedSubjects()[$sid])) {
            return redirect()->to($back)->with('error', 'Pilih mata pelajaran yang Anda ajarkan.');
        }
        if (! isset(self::DOC_TYPES[$type]) || ($phase !== '' && ! in_array($phase, ['A', 'B', 'C', 'D', 'E', 'F'], true)) || mb_strlen($title) > 150) {
            return redirect()->to($back)->with('error', 'Periksa jenis dokumen, fase, dan judul (maks. 150 karakter).');
        }
        $file = $this->request->getFile('doc');
        if (! $file || $file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
            return redirect()->to($back)->with('error', 'Pilih file dokumen.');
        }
        $ext = strtolower($file->getClientExtension());
        $head = (string) @file_get_contents($file->getTempName(), false, null, 0, 4);
        if (! isset(self::EXT[$ext]) || ! str_starts_with($head, self::EXT[$ext]) || $file->getSize() > self::MAX_BYTES) {
            return redirect()->to($back)->with('error', 'Dokumen harus PDF/DOCX/XLSX/PPTX asli, maks. 5 MB.');
        }
        $name = 'kur-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $size = (int) $file->getSize();
        $orig = mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', $file->getClientName()), 0, 180);
        // move_uploaded_file untuk unggahan asli; salin biasa hanya di lingkungan pengujian
        $ok = is_uploaded_file($file->getTempName()) ? move_uploaded_file($file->getTempName(), self::dir() . $name) : (ENVIRONMENT === 'testing' && copy($file->getTempName(), self::dir() . $name));
        if (! $ok) {
            return redirect()->to($back)->with('error', 'Dokumen gagal disimpan. Coba lagi.');
        }
        $now = date('Y-m-d H:i:s');
        db_connect()->table('curriculum_documents')->insert([
            'academic_year_id' => (int) Period::yearId(), 'user_id' => Auth::isAdmin() ? null : (int) Auth::id(), 'subject_id' => $sid,
            'phase' => $phase ?: null, 'semester_id' => $semId, 'doc_type' => $type, 'title' => $title !== '' ? $title : pathinfo($orig, PATHINFO_FILENAME),
            'file_name' => $name, 'original_name' => $orig, 'file_size' => $size, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Audit::log('create', 'curriculum_document', (int) db_connect()->insertID(), null, ['type' => $type, 'file' => $orig]);

        return redirect()->to($back)->with('success', 'Dokumen ' . self::DOC_TYPES[$type] . ' diunggah.');
    }

    private function findDoc(int $id): array
    {
        $doc = null;
        foreach ($this->docList() as $d) {
            if ((int) $d['id'] === $id) {
                $doc = $d;
                break;
            }
        }
        if (! $doc || ! preg_match('/^kur-[a-f0-9]{20}\.(pdf|docx|xlsx|pptx)$/', $doc['file_name']) || ! is_file(self::dir() . $doc['file_name'])) {
            throw PageNotFoundException::forPageNotFound('Dokumen tidak ditemukan.');
        }

        return $doc;
    }

    public function download(int $id)
    {
        $doc = $this->findDoc($id);

        return $this->response->download(self::dir() . $doc['file_name'], null)->setFileName($doc['original_name'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function delete(int $id)
    {
        $doc  = $this->findDoc($id);
        $back = Auth::isAdmin() ? site_url('curriculum/docs') : site_url('curriculum#dokumen');
        // Guru hanya menghapus dokumen unggahannya sendiri; dokumen sekolah (admin) hanya oleh admin
        if (! Auth::isAdmin() && (int) $doc['user_id'] !== (int) Auth::id()) {
            return redirect()->to($back)->with('error', 'Dokumen sekolah hanya dapat dihapus oleh admin teknis.');
        }
        @unlink(self::dir() . $doc['file_name']);
        db_connect()->table('curriculum_documents')->where('id', $id)->delete();
        Audit::log('delete', 'curriculum_document', $id, ['file' => $doc['original_name']], null);

        return redirect()->to($back)->with('success', 'Dokumen dihapus.');
    }
}
