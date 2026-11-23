<?php

namespace App\Controllers;

use App\Libraries\SchoolLogo;
use App\Models\SchoolProfileModel;
use Throwable;

/** Menyajikan logo sekolah (publik, agar tampil juga di halaman login). */
class Branding extends BaseController
{
    /**
     * /favicon.ico (diminta otomatis oleh browser): logo sekolah bila ada,
     * jika tidak ikon bawaan. Cache singkat agar penggantian logo cepat terlihat.
     */
    public function favicon()
    {
        $res = $this->logo();
        if ($res->getStatusCode() === 200) {
            return $res->setHeader('Cache-Control', 'public, max-age=3600');
        }

        return $this->response->setStatusCode(200)
            ->setHeader('Content-Type', 'image/svg+xml')
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody((string) file_get_contents(FCPATH . 'assets/icon.svg'));
    }

    public function logo()
    {
        try {
            $profile = (new SchoolProfileModel())->first();
        } catch (Throwable $e) {
            $profile = null;
        }
        $path = SchoolLogo::path($profile['school_logo'] ?? null);
        if (! $path) {
            return $this->response->setStatusCode(404);
        }
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)];

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'public, max-age=2592000')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    /** Logo instansi (kiri kop) atau foto sekolah untuk sampul Buku Agenda Guru. */
    public function asset(string $kind)
    {
        $col = ['gov' => 'gov_logo', 'cover' => 'school_photo'][$kind] ?? null;
        if (! $col) {
            return $this->response->setStatusCode(404);
        }
        try {
            $profile = (new SchoolProfileModel())->first();
        } catch (Throwable $e) {
            $profile = null;
        }
        $name = (string) ($profile[$col] ?? '');
        $path = str_starts_with($name, $kind . '-') ? SchoolLogo::path($name) : null;
        if (! $path) {
            return $this->response->setStatusCode(404);
        }
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)];

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'public, max-age=2592000')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }
}
