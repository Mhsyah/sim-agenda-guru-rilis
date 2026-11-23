<?php

namespace App\Controllers;

use App\Libraries\Auth;

class AuthController extends BaseController
{
    public function login()
    {
        return $this->render('auth/login', ['title' => 'Masuk']);
    }

    public function attempt()
    {
        if (! $this->validate([
            'email'    => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[150]'],
            'password' => ['label' => 'Kata sandi', 'rules' => 'required|max_length[200]'],
        ])) {
            return $this->back('Periksa kembali isian login.', $this->validator->getErrors());
        }
        $res = Auth::attempt((string) $this->request->getPost('email'), (string) $this->request->getPost('password'), $this->request->getIPAddress());
        if ($res !== true) {
            return redirect()->back()->withInput()->with('error', $res);
        }
        // Tema yang dipilih di halaman login ikut disimpan ke akun agar dashboard memakai tema yang sama.
        $theme = (string) $this->request->getPost('theme');
        if (in_array($theme, ['light', 'dark', 'system'], true) && ($u = Auth::user()) && ($u['theme_pref'] ?? '') !== $theme) {
            (new \App\Models\UserModel())->update((int) $u['id'], ['theme_pref' => $theme]);
            Auth::forget();
        }
        $to = session('intended');
        session()->remove('intended');
        // Hanya izinkan redirect internal.
        if (! is_string($to) || ! str_starts_with($to, base_url())) {
            $to = site_url('/');
        }

        // Penanda sekali pakai: bila halaman tujuan bukan untuk peran akun ini, filter mengalihkan ke Dashboard tanpa peringatan.
        return redirect()->to($to)->with('success', 'Selamat datang kembali.')->with('fresh_login', 1);
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar dengan aman.');
    }
}
