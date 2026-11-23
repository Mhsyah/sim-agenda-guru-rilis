<?php

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! Auth::check()) {
            if (strtolower($request->getMethod()) === 'get') {
                session()->set('intended', current_url());
            }

            return redirect()->to(site_url('login'))->with('error', 'Silakan login terlebih dahulu.');
        }
        $denied = ($arguments && in_array('teacher', $arguments, true) && ! Auth::isTeacher())
            || ($arguments && in_array('admin', $arguments, true) && ! Auth::isAdmin());
        // Tepat setelah login, tujuan lama (mis. halaman guru dari sesi sebelumnya) dialihkan diam-diam ke Dashboard.
        if ($denied && session('fresh_login')) {
            session()->keepFlashdata('success');
            session()->remove('fresh_login');

            return redirect()->to(site_url('/'));
        }
        if ($arguments && in_array('teacher', $arguments, true) && ! Auth::isTeacher()) {
            return redirect()->to(site_url('/'))->with('error', 'Halaman ini khusus guru. Admin teknis mengelola data induk sekolah.');
        }
        if ($arguments && in_array('admin', $arguments, true) && ! Auth::isAdmin()) {
            return redirect()->to(site_url('/'))->with('error', 'Halaman ini khusus admin teknis.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Halaman privat tidak boleh di-cache oleh browser/proxy.
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }
}
