<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\CoverBlank;
use App\Libraries\KopSettings;
use App\Libraries\Lookup;
use App\Models\SchoolProfileModel;
use App\Models\UserModel;
use Throwable;

/**
 * Blanko sampul Buku Agenda Guru.
 * Admin mengatur blanko DEFAULT sekolah; guru dapat memakai blanko miliknya sendiri (atau kembali ke default).
 */
class Covers extends BaseController
{
    public function index()
    {
        $user    = Auth::user();
        $profile = self::schoolProfile();
        $admin   = Auth::isAdmin();
        $own     = $admin ? ($profile['cover_blank'] ?? null) : ($user['cover_blank'] ?? null);
        $opts    = CoverBlank::opts($admin ? ($profile['cover_blank_opts'] ?? null) : ($user['cover_blank_opts'] ?? null));
        $active  = CoverBlank::resolve($admin ? null : $user, $profile);
        $period  = \App\Libraries\Period::get();

        return $this->render('covers/index', [
            'title'    => 'Sampul Buku Agenda',
            'isAdmin'  => $admin,
            'ownUrl'   => CoverBlank::url($own),
            'opts'     => $opts,
            'active'   => $active,
            'school'   => CoverBlank::url($profile['cover_blank'] ?? null),
            'kopSet'   => KopSettings::get($profile),
            'teacher'  => $admin ? ['semester' => (string) ($period['semester']['name'] ?? ''), 'year' => (string) ($period['year']['name'] ?? '')] : [
                'name' => (string) $user['name'], 'nip' => (string) ($user['nip'] ?? ''),
                'subject' => implode(', ', Lookup::teachingSubjects()), 'class' => implode(', ', Lookup::teachingClasses()),
                'semester' => (string) ($period['semester']['name'] ?? ''), 'year' => (string) ($period['year']['name'] ?? ''),
            ],
        ]);
    }

    public function save()
    {
        $admin = Auth::isAdmin();
        $user  = Auth::user();
        $prof  = self::schoolProfile();
        $cur   = $admin ? ($prof['cover_blank'] ?? null) : ($user['cover_blank'] ?? null);
        $data  = ['cover_blank_opts' => CoverBlank::encode([
            'fields' => $this->request->getPost('fields') ? 1 : 0,
            'y'      => (int) $this->request->getPost('y'),
            'box'    => $this->request->getPost('box') ? 1 : 0,
        ])];

        $file = $this->request->getFile('blank');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing')) {
                return redirect()->to(site_url('cover'))->with('error', 'Unggahan blanko gagal. Coba lagi.');
            }
            try {
                $data['cover_blank'] = CoverBlank::store($file->getTempName());
            } catch (Throwable $e) {
                return redirect()->to(site_url('cover'))->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Blanko tidak dapat diproses.');
            }
            CoverBlank::delete($cur);
        } elseif ($this->request->getPost('remove')) {
            $data['cover_blank'] = null;
            CoverBlank::delete($cur);
        }

        if ($admin) {
            if ($prof) {
                (new SchoolProfileModel())->update((int) $prof['id'], $data);
            } else {
                (new SchoolProfileModel())->insert($data + ['school_name' => 'Nama Sekolah']);
            }
            self::forgetProfile();
        } else {
            (new UserModel())->update((int) $user['id'], $data);
            Auth::forget();
        }
        Audit::log('update', 'cover_blank', null, null, ['scope' => $admin ? 'sekolah' : 'guru', 'file' => array_key_exists('cover_blank', $data) ? ($data['cover_blank'] ? 'baru' : 'dihapus') : 'tetap']);

        $msg = array_key_exists('cover_blank', $data) && $data['cover_blank'] === null
            ? ($admin ? 'Blanko default dihapus; sampul memakai desain bawaan.' : 'Blanko Anda dihapus; sampul kembali memakai default sekolah.')
            : 'Sampul Buku Agenda disimpan.';

        return redirect()->to(site_url('cover'))->with('success', $msg);
    }

    /** Gambar blanko: default sekolah untuk semua pengguna, blanko guru hanya untuk pemiliknya/admin. */
    public function image(string $name)
    {
        $path = CoverBlank::path($name);
        $user = Auth::user();
        $prof = self::schoolProfile();
        $ok   = $path && ($name === ($prof['cover_blank'] ?? null) || $name === ($user['cover_blank'] ?? null) || Auth::isAdmin());
        if (! $ok) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/webp')
            ->setHeader('Cache-Control', 'private, max-age=2592000')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }
}
