<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\BackupService;
use App\Libraries\KopSettings;
use App\Libraries\Period;
use App\Libraries\ProfilePhoto;
use App\Libraries\SchoolLogo;
use App\Models\AuditLogModel;
use App\Models\SchoolProfileModel;
use App\Models\UserModel;
use Throwable;

class Settings extends BaseController
{
    // ------------------------------------------------------------ Profil saya (semua role)

    public function profile()
    {
        return $this->render('settings/profile', ['title' => 'Profil Saya']);
    }

    public function saveProfile()
    {
        $uid = Auth::id();
        if (! $this->validate([
            'name'  => ['label' => 'Nama lengkap', 'rules' => 'required|max_length[100]'],
            'email' => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[150]|is_unique[users.email,id,' . $uid . ']'],
            'nip'   => ['label' => 'NIP', 'rules' => 'permit_empty|max_length[30]'],
            'gender' => ['label' => 'Jenis kelamin', 'rules' => 'permit_empty|in_list[L,P]'],
        ])) {
            return $this->back('Periksa kembali isian yang ditandai.', $this->validator->getErrors());
        }
        $old = Auth::user();
        $u   = $this->postClean(['name', 'nip', 'gender']);
        $u['email'] = strtolower(trim((string) $this->request->getPost('email')));
        (new UserModel())->update($uid, $u);
        Auth::forget();
        Audit::log('update', 'user_profile', $uid, ['name' => $old['name'], 'email' => $old['email'], 'nip' => $old['nip'] ?? null], $u);

        return redirect()->to(site_url('settings/profile'))->with('success', 'Profil tersimpan.');
    }

    /** Guru mengatur mapel & kelas yang diajarkannya sendiri (termasuk mapel tambahan). */
    public function saveTeaching()
    {
        $user = Auth::user();
        if (($user['role'] ?? '') !== 'teacher') {
            return redirect()->to(site_url('settings/profile'))->with('error', 'Pembagian mengajar admin diatur di Guru & Pengguna.');
        }
        $uid    = (int) $user['id'];
        $before = ['subjects' => Users::subjectIds($uid), 'teach' => Users::teachingMap($uid)];
        $teach  = (array) ($this->request->getPost('teach') ?? []);
        $subs   = array_merge((array) ($this->request->getPost('subject_ids') ?? []),
            array_keys(array_filter($teach, static fn ($c) => is_array($c) && array_filter($c))));
        $db = db_connect();
        $db->transException(true)->transStart();
        Users::syncSubjects($uid, $subs);
        Users::syncTeaching($uid, $teach);
        $db->transComplete();
        Audit::log('update', 'user_teaching', $uid, $before, ['subjects' => Users::subjectIds($uid), 'teach' => Users::teachingMap($uid)]);

        return redirect()->to(site_url('settings/profile') . '#teaching')->with('success', 'Mata pelajaran & kelas yang diajarkan tersimpan.');
    }

    /** Unggah foto profil (dari galeri atau kamera HP); otomatis dipotong persegi & dikonversi ke WebP. */
    public function savePhoto()
    {
        $file = $this->request->getFile('photo');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->back('Pilih foto terlebih dahulu.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
            return $this->back('Unggahan foto gagal: ' . $file->getErrorString());
        }
        $user = Auth::user();
        try {
            $name = ProfilePhoto::store($file->getTempName(), (int) $user['id']);
        } catch (Throwable $e) {
            return $this->back($e->getMessage());
        }
        (new UserModel())->update($user['id'], ['photo' => $name]);
        ProfilePhoto::delete($user['photo'] ?? null);
        Auth::forget();
        Audit::log('update', 'user_photo', (int) $user['id']);

        return redirect()->to(site_url('settings/profile'))->with('success', 'Foto profil diperbarui.');
    }

    public function deletePhoto()
    {
        $user = Auth::user();
        ProfilePhoto::delete($user['photo'] ?? null);
        (new UserModel())->update($user['id'], ['photo' => null]);
        Auth::forget();

        return redirect()->to(site_url('settings/profile'))->with('success', 'Foto profil dihapus.');
    }

    /** Sajikan foto profil pengguna (hanya untuk pengguna yang sudah login). */
    public function avatar(int $id)
    {
        $u    = (new UserModel())->select('id, photo')->find($id);
        $path = ProfilePhoto::path($u['photo'] ?? null);
        if (! $path) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'image/webp')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    // ------------------------------------------------------------ Profil & kop sekolah (admin teknis)

    public function school()
    {
        return $this->render('settings/school', ['title' => 'Profil & Kop Sekolah']);
    }

    public function saveSchool()
    {
        if (! $this->validate([
            'school_name'    => ['label' => 'Nama sekolah', 'rules' => 'required|max_length[150]'],
            'school_address' => ['label' => 'Alamat sekolah', 'rules' => 'permit_empty|max_length[1000]'],
            'timezone'       => ['label' => 'Zona waktu', 'rules' => 'required|in_list[Asia/Jakarta,Asia/Makassar,Asia/Jayapura]'],
            'header_line'    => ['label' => 'Baris instansi', 'rules' => 'permit_empty|max_length[200]'],
            'npsn'           => ['label' => 'NPSN', 'rules' => 'permit_empty|max_length[20]'],
            'school_city'    => ['label' => 'Kota/kabupaten', 'rules' => 'permit_empty|max_length[100]'],
            'school_phone'   => ['label' => 'Telepon', 'rules' => 'permit_empty|max_length[50]'],
            'school_email'   => ['label' => 'Email sekolah', 'rules' => 'permit_empty|valid_email|max_length[150]'],
            'school_website' => ['label' => 'Website', 'rules' => 'permit_empty|max_length[150]'],
            'principal_name' => ['label' => 'Nama kepala sekolah', 'rules' => 'permit_empty|max_length[150]'],
            'principal_nip'  => ['label' => 'NIP kepala sekolah', 'rules' => 'permit_empty|max_length[30]'],
        ])) {
            return $this->back('Periksa kembali isian yang ditandai.', $this->validator->getErrors());
        }
        $p = $this->postClean(['school_name', 'school_address', 'timezone', 'header_line', 'npsn',
            'school_city', 'school_phone', 'school_email', 'school_website', 'principal_name', 'principal_nip']);
        $pm  = new SchoolProfileModel();
        $cur = $pm->first();

        // Tukar logo sekolah ⇄ logo instansi (bila terunggah di kolom yang keliru).
        if ($this->request->getPost('swap_logos') && $cur) {
            $toGov  = SchoolLogo::reprefix($cur['school_logo'] ?? null, 'gov');
            $toLogo = SchoolLogo::reprefix($cur['gov_logo'] ?? null, 'logo');
            $pm->update($cur['id'], ['school_logo' => $toLogo, 'gov_logo' => $toGov]);
            BaseController::forgetProfile();
            Audit::log('update', 'school_profile', (int) $cur['id'], ['school_logo' => $cur['school_logo'], 'gov_logo' => $cur['gov_logo']], ['school_logo' => $toLogo, 'gov_logo' => $toGov]);

            return redirect()->to(site_url('settings/school'))->with('success', 'Posisi logo sekolah dan logo instansi sudah ditukar.');
        }

        // Logo sekolah: unggah baru atau hapus.
        $file = $this->request->getFile('school_logo');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            try {
                $p['school_logo'] = SchoolLogo::store($file);
            } catch (Throwable $e) {
                return $this->back($e->getMessage(), ['school_logo' => $e->getMessage()]);
            }
            SchoolLogo::delete($cur['school_logo'] ?? null);
        } elseif ($this->request->getPost('remove_logo')) {
            SchoolLogo::delete($cur['school_logo'] ?? null);
            $p['school_logo'] = null;
        }
        // Logo instansi & tanda tangan kepala sekolah (opsional).
        // Foto sekolah untuk sampul Buku Agenda Guru.
        $cf = $this->request->getFile('school_photo');
        if ($cf && $cf->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($cf->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($cf->getTempName()) || ENVIRONMENT === 'testing')) {
                return $this->back('Unggahan foto sekolah gagal: ' . $cf->getErrorString(), ['school_photo' => 'Unggahan gagal.']);
            }
            try {
                $p['school_photo'] = SchoolLogo::storeCover($cf->getTempName());
            } catch (Throwable $e) {
                return $this->back($e->getMessage(), ['school_photo' => $e->getMessage()]);
            }
            SchoolLogo::delete($cur['school_photo'] ?? null);
        } elseif ($this->request->getPost('remove_school_photo')) {
            SchoolLogo::delete($cur['school_photo'] ?? null);
            $p['school_photo'] = null;
        }

        foreach (['gov_logo' => ['gov', 'remove_gov_logo']] as $col => [$prefix, $remove]) {
            $f = $this->request->getFile($col);
            if ($f && $f->getError() !== UPLOAD_ERR_NO_FILE) {
                try {
                    $p[$col] = SchoolLogo::store($f, $prefix);
                } catch (Throwable $e) {
                    return $this->back($e->getMessage(), [$col => $e->getMessage()]);
                }
                SchoolLogo::delete($cur[$col] ?? null);
            } elseif ($this->request->getPost($remove)) {
                SchoolLogo::delete($cur[$col] ?? null);
                $p[$col] = null;
            }
        }

        // Kop surat & pengaturan cetak.
        $kop = $this->request->getPost('kop');
        if (is_array($kop)) {
            if (($kop['date_mode'] ?? '') === 'manual' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($kop['sign_date'] ?? ''))) {
                return $this->back('Isi tanggal tanda tangan atau pilih tanggal otomatis.', ['kop_sign_date' => 'Wajib diisi.']);
            }
            $p['kop_settings'] = json_encode(KopSettings::clean($kop), JSON_UNESCAPED_UNICODE);
        }
        if (! $cur) {
            $p['teacher_name'] = '';
        }
        $cur ? $pm->update($cur['id'], $p) : $pm->insert($p);
        BaseController::forgetProfile();
        Audit::log('update', 'school_profile', $cur['id'] ?? null, $cur, $p);

        return redirect()->to(site_url('settings/school'))->with('success', 'Profil, kop surat, dan pengaturan cetak tersimpan.');
    }

    // ------------------------------------------------------------ Tema & preferensi

    public function preferences()
    {
        return $this->render('settings/preferences', ['title' => 'Tema & Preferensi']);
    }

    public function saveTheme()
    {
        $theme = (string) $this->request->getPost('theme');
        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            $theme = 'system';
        }
        (new UserModel())->update(Auth::id(), ['theme_pref' => $theme]);
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true, 'theme' => $theme, 'csrf' => csrf_hash()]);
        }

        return redirect()->back()->with('success', 'Preferensi tema tersimpan.');
    }

    // ------------------------------------------------------------ Keamanan

    public function security()
    {
        return $this->render('settings/security', ['title' => 'Keamanan & Kata Sandi']);
    }

    public function changePassword()
    {
        if (! $this->validate([
            'current_password' => ['label' => 'Kata sandi saat ini', 'rules' => 'required'],
            'new_password'     => ['label' => 'Kata sandi baru', 'rules' => 'required|min_length[10]|max_length[200]'],
            'new_password2'    => ['label' => 'Ulangi kata sandi baru', 'rules' => 'required|matches[new_password]'],
        ])) {
            return $this->back('Periksa kembali isian yang ditandai.', $this->validator->getErrors());
        }
        $user = Auth::user();
        if (! password_verify((string) $this->request->getPost('current_password'), $user['password_hash'])) {
            return $this->back('Kata sandi saat ini salah.', ['current_password' => 'Kata sandi saat ini salah.']);
        }
        (new UserModel())->update($user['id'], ['password_hash' => password_hash((string) $this->request->getPost('new_password'), PASSWORD_DEFAULT)]);
        session()->regenerate(true);
        session()->remove('pw_default');
        Audit::log('password_change', 'user', (int) $user['id']);

        return redirect()->to(site_url('settings/security'))->with('success', 'Kata sandi berhasil diganti.');
    }

    // ------------------------------------------------------------ Backup / Restore

    public function backup()
    {
        return $this->render('settings/backup', [
            'title'   => 'Backup & Restore',
            'backups' => BackupService::list(),
            'driver'  => db_connect()->DBDriver,
        ]);
    }

    public function createBackup()
    {
        try {
            $name = (new BackupService())->store();
            Audit::log('backup_create', 'backup', null, null, ['file' => $name]);
        } catch (Throwable $e) {
            log_message('error', 'Backup gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Backup gagal dibuat. Pastikan folder writable/backups dapat ditulis.');
        }

        return redirect()->to(site_url('settings/backup'))->with('success', 'Backup ' . $name . ' berhasil dibuat.');
    }

    public function downloadBackup(string $name)
    {
        $path = BackupService::path($name);
        if (! $path) {
            return redirect()->to(site_url('settings/backup'))->with('error', 'File backup tidak ditemukan.');
        }
        Audit::log('backup_download', 'backup', null, null, ['file' => $name]);

        return $this->response->download($path, null)->setFileName($name);
    }

    public function deleteBackup(string $name)
    {
        $path = BackupService::path($name);
        if ($path) {
            unlink($path);
            Audit::log('backup_delete', 'backup', null, null, ['file' => $name]);
        }

        return redirect()->to(site_url('settings/backup'))->with('success', 'File backup dihapus.');
    }

    public function restore()
    {
        if (strtoupper(trim((string) $this->request->getPost('confirm_text'))) !== 'PULIHKAN') {
            return redirect()->back()->with('error', 'Ketik PULIHKAN untuk mengonfirmasi pemulihan data.');
        }
        $json = null;
        $source = '';
        if ($name = $this->request->getPost('backup_name')) {
            $path = BackupService::path((string) $name);
            $json = $path ? file_get_contents($path) : null;
            $source = (string) $name;
        } else {
            $file = $this->request->getFile('backup_file');
            if ($file && $file->isValid() && $file->getSize() <= 50 * 1024 * 1024) {
                $json = file_get_contents($file->getTempName());
                $source = 'upload:' . $file->getClientName();
            }
        }
        if (! $json) {
            return redirect()->back()->with('error', 'Pilih file backup yang valid (maks. 50 MB).');
        }

        $svc = new BackupService();
        try {
            $svc->validate($json);
            $safety = $svc->store('sebelum-restore');
            $counts = $svc->restore($json);
        } catch (Throwable $e) {
            log_message('error', 'Restore gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Pemulihan dibatalkan: ' . $e->getMessage() . ' Data lama tidak diubah.');
        }
        Period::set(null, null);
        Auth::forget();
        Audit::log('restore', 'backup', null, ['safety_backup' => $safety], ['source' => $source, 'counts' => $counts]);

        if (! Auth::check()) {
            session()->remove('uid');

            return redirect()->to(site_url('login'))->with('success', 'Data berhasil dipulihkan. Silakan login dengan akun dari backup.');
        }

        return redirect()->to(site_url('settings/backup'))->with('success', 'Data berhasil dipulihkan (' . array_sum($counts) . ' baris). Backup pengaman: ' . $safety);
    }

    // ------------------------------------------------------------ Audit

    public function audit()
    {
        $m = (new AuditLogModel())->select('audit_logs.*, u.name AS user_name')->join('users u', 'u.id = audit_logs.user_id', 'left');
        if ($e = $this->request->getGet('entity_type')) {
            $m->where('audit_logs.entity_type', $e);
        }
        $rows = $m->orderBy('audit_logs.id', 'DESC')->paginate(30);
        $types = array_column(db_connect()->table('audit_logs')->select('entity_type')->distinct()->orderBy('entity_type', 'ASC')->get()->getResultArray(), 'entity_type');

        return $this->render('settings/audit', ['title' => 'Log Audit', 'rows' => $rows, 'pager' => $m->pager, 'types' => $types,
            'total' => db_connect()->table('audit_logs')->countAllResults()]);
    }

    /**
     * Hapus log audit: yang dicentang, yang lebih lama dari N hari, atau semua sesuai filter jenis entitas.
     * Penghapusan sendiri selalu dicatat sebagai entri baru (tidak bisa dihapus diam-diam).
     */
    public function deleteAudit()
    {
        $mode = (string) $this->request->getPost('mode');
        $db   = db_connect();
        $b    = $db->table('audit_logs');
        $desc = '';
        if ($mode === 'selected') {
            $ids = array_values(array_filter(array_map('intval', (array) ($this->request->getPost('ids') ?? []))));
            if (! $ids) {
                return redirect()->back()->with('error', 'Centang log yang akan dihapus terlebih dahulu.');
            }
            $b->whereIn('id', $ids);
            $desc = count($ids) . ' log terpilih';
        } elseif (preg_match('/^older_(30|90|180|365)$/', $mode, $mm)) {
            $b->where('created_at <', gmdate('Y-m-d H:i:s', time() - (int) $mm[1] * 86400));
            $desc = 'log lebih lama dari ' . $mm[1] . ' hari';
        } elseif ($mode === 'filtered') {
            $type = (string) $this->request->getPost('entity_type');
            if ($type !== '') {
                $b->where('entity_type', $type);
            }
            $desc = $type !== '' ? 'semua log jenis "' . $type . '"' : 'semua log';
        } else {
            return redirect()->back()->with('error', 'Pilih log yang akan dihapus.');
        }
        $b->where('id >', 0)->delete(); // syarat where wajib di CI (opsi "Semua log")
        $n = $db->affectedRows();
        Audit::log('audit_delete', 'audit_log', null, null, ['mode' => $mode, 'deleted' => $n, 'by' => Auth::user()['name'] ?? null]);

        return redirect()->to(site_url('settings/audit'))->with('success', $n . ' entri dihapus (' . $desc . '). Penghapusan ini tercatat di log.');
    }
}
