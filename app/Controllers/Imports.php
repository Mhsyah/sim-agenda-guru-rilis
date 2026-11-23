<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\MasterImporter;
use App\Libraries\Period;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * Pusat impor data induk (admin teknis).
 * Alur dua langkah: unggah → PRATINJAU (uji coba yang dibatalkan, tidak ada data tersimpan) → konfirmasi → impor.
 */
class Imports extends BaseController
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function index()
    {
        return $this->render('imports/index', [
            'title'      => 'Impor Data',
            'types'      => MasterImporter::TYPES,
            'result'     => session('import_result'),
            'resultType' => session('import_type'),
            'counts'     => $this->counts(),
        ]);
    }

    /** Ringkasan data saat ini per jenis impor (ditampilkan di kartu). */
    private function counts(): array
    {
        $db  = db_connect();
        $yid = (int) Period::yearId();
        $n   = static fn ($q) => number_format($q->countAllResults(), 0, ',', '.');

        return [
            'years'      => $n($db->table('academic_years')) . ' T.P.',
            'subjects'   => $n($db->table('subjects')->where('deleted_at', null)) . ' mapel',
            'classes'    => $n($db->table('class_groups')->where('academic_year_id', $yid)->where('deleted_at', null)) . ' kelas',
            'teachers'   => $n($db->table('users')->where('role', 'teacher')) . ' guru',
            'students'   => $n($db->table('students')->where('deleted_at', null)->where('status', 'aktif')) . ' siswa aktif',
            'curriculum' => $n($db->table('learning_objectives')->where('academic_year_id', $yid)->where('deleted_at', null)) . ' TP',
        ];
    }

    private function type(string $type): array
    {
        // Guru hanya boleh memakai impor kurikulum (CP/TP/ATP/KKTP); jenis lain khusus admin teknis
        if (! isset(MasterImporter::TYPES[$type]) || (! Auth::isAdmin() && $type !== 'curriculum')) {
            throw PageNotFoundException::forPageNotFound();
        }

        return MasterImporter::TYPES[$type];
    }

    public function template(string $type)
    {
        $t = $this->type($type);

        return csv_response('template-' . $type . '.csv', array_keys($t['columns']), $t['sample']);
    }

    private static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
        }
        // Bersihkan file pratinjau lama (> 1 hari)
        foreach (glob($dir . '*.csv') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }

        return $dir;
    }

    /** Langkah 1: unggah & pratinjau (tidak ada data yang disimpan). */
    public function run(string $type)
    {
        $t    = $this->type($type);
        $back = $type === 'students' && $this->request->getPost('from') === 'students' ? site_url('students/import')
            : ($type === 'curriculum' && ! Auth::isAdmin() ? site_url('curriculum') : site_url('imports#' . $type));
        if (in_array($type, ['students', 'classes'], true) && ! Period::yearId()) {
            return redirect()->to($back)->with('error', 'Buat atau impor tahun pelajaran terlebih dahulu.');
        }
        $file = $this->request->getFile('csv');
        if (! $file || $file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
            return redirect()->to($back)->with('error', 'Pilih file CSV yang valid.');
        }
        if ($file->getSize() > self::MAX_BYTES || ! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->to($back)->with('error', 'File harus berformat .csv dan maksimal 2 MB.');
        }
        $content = (string) file_get_contents($file->getTempName());
        $classId = in_array($type, ['students', 'curriculum'], true) ? ((int) $this->request->getPost($type === 'students' ? 'class_group_id' : 'subject_id') ?: null) : null;
        if ($type === 'curriculum' && $classId && ! isset((Auth::isTeacher() ? Lookup::teachingSubjects() : Lookup::subjects(false))[$classId])) {
            return redirect()->to($back)->with('error', 'Pilih mapel yang Anda ajarkan.');
        }
        if ($type === 'curriculum' && ! Period::yearId()) {
            return redirect()->to($back)->with('error', 'Buat atau impor tahun pelajaran terlebih dahulu.');
        }

        $token = bin2hex(random_bytes(12));
        file_put_contents(self::dir() . $token . '.csv', $content);
        $pending = (array) (session('import_pending') ?? []);
        $pending[$token] = ['type' => $type, 'class' => $classId, 'name' => $file->getClientName(), 'back' => $back];
        session()->set('import_pending', array_slice($pending, -5, null, true));

        // Uji coba di dalam transaksi lalu dibatalkan: hasil persis seperti impor sungguhan, tanpa menyimpan.
        $db = db_connect();
        $db->transBegin();
        try {
            $result = (new MasterImporter())->run($type, $content, $classId);
            if ($type === 'students') {
                $result['capacity'] = \App\Libraries\ClassCapacity::notices(); // keadaan rombel SETELAH impor
            }
        } catch (Throwable $e) {
            log_message('error', 'Pratinjau impor ' . $type . ': ' . $e->getMessage());
            $result = ['ok' => 0, 'created' => 0, 'updated' => 0, 'restored' => 0, 'total' => 0, 'rows' => [], 'ignored' => [],
                'errors' => [['line' => 1, 'reason' => 'File tidak dapat dibaca. Pastikan memakai template CSV.']], 'credentials' => []];
        } finally {
            $db->transRollback();
            Period::reset();
            Lookup::reset();
        }

        return $this->render('imports/preview', [
            'title'   => 'Pratinjau Impor ' . $t['title'],
            'type'    => $type,
            'meta'    => $t,
            'token'   => $token,
            'file'    => $file->getClientName(),
            'result'  => $result,
            'back'    => $back,
            'classId' => $classId,
            'actionBase' => $type === 'curriculum' && ! Auth::isAdmin() ? 'curriculum' : 'imports',
        ]);
    }

    /** Langkah 2: impor sungguhan setelah pratinjau disetujui (baris bermasalah dilewati). */
    public function confirm()
    {
        $token   = (string) $this->request->getPost('token');
        $pending = (array) (session('import_pending') ?? []);
        $job     = $pending[$token] ?? null;
        $path    = self::dir() . (preg_match('/^[a-f0-9]{24}$/', $token) ? $token : 'x') . '.csv';
        if (! $job || ! is_file($path)) {
            return redirect()->to(site_url('imports'))->with('error', 'Pratinjau sudah kedaluwarsa. Unggah ulang file CSV.');
        }
        $type    = $job['type'];
        if (! Auth::isAdmin() && $type !== 'curriculum') {
            throw PageNotFoundException::forPageNotFound();
        }
        $content = (string) file_get_contents($path);
        $result  = (new MasterImporter())->run($type, $content, $job['class'] ?? null);
        if ($type === 'students') {
            Period::reset();
            $result['capacity'] = \App\Libraries\ClassCapacity::notices();
        }
        @unlink($path);
        unset($pending[$token]);
        session()->set('import_pending', $pending);
        Audit::log('import', $type, null, null, ['file' => $job['name'], 'ok' => $result['ok'], 'created' => $result['created'], 'updated' => $result['updated'],
            'restored' => $result['restored'] ?? 0, 'failed' => count($result['errors'])]);
        Period::reset();

        $msg = MasterImporter::TYPES[$type]['title'] . ': ' . $result['ok'] . ' baris berhasil diimpor'
            . ($result['errors'] ? ', ' . count($result['errors']) . ' baris dilewati karena bermasalah.' : '.');
        $to = in_array($job['back'], [site_url('students/import'), site_url('curriculum')], true) ? $job['back'] : site_url('imports#hasil');

        return redirect()->to($to)
            ->with('import_result', $result)->with('import_type', $type)
            ->with($result['errors'] ? 'warning' : 'success', $msg);
    }

    /** Batalkan pratinjau: hapus file sementara. */
    public function cancel()
    {
        $token   = (string) $this->request->getPost('token');
        $pending = (array) (session('import_pending') ?? []);
        $back    = $pending[$token]['back'] ?? site_url('imports');
        if (preg_match('/^[a-f0-9]{24}$/', $token)) {
            @unlink(self::dir() . $token . '.csv');
        }
        unset($pending[$token]);
        session()->set('import_pending', $pending);

        return redirect()->to($back)->with('info', 'Impor dibatalkan. Tidak ada data yang diubah.');
    }
}
