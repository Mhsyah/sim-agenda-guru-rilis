<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Header keamanan tambahan (melengkapi SecureHeaders bawaan CI4):
 * batasi fitur peramban, isolasi jendela, dan HSTS bila diakses lewat HTTPS di production.
 */
class Hardening implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->removeHeader('X-Powered-By');
        if (ENVIRONMENT === 'production' && $request instanceof \CodeIgniter\HTTP\IncomingRequest && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
