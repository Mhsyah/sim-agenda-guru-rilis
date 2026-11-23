<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Notifications as Notif;

/** Lonceng notifikasi: tandai semua pengingat sebagai sudah dibaca. */
class Notifications extends BaseController
{
    /** Klik satu notifikasi: tandai dibaca lalu buka tautannya (atau kembali ke halaman semula). */
    public function open()
    {
        $user = Auth::user();
        $url  = $user ? Notif::markRead($user, (string) $this->request->getPost('key')) : null;

        return redirect()->to($this->safe($url ?? (string) $this->request->getPost('back')));
    }

    public function read()
    {
        $user = Auth::user();
        if ($user) {
            Notif::markAllRead($user);
        }
        return redirect()->to($this->safe((string) $this->request->getPost('back')));
    }

    /** Hanya izinkan tujuan di dalam aplikasi ini. */
    private function safe(string $url): string
    {
        $base = rtrim(site_url('/'), '/');

        return $url !== '' && ($url === $base || str_starts_with($url, $base . '/') || str_starts_with($url, $base . '?')) ? $url : site_url('/');
    }
}
