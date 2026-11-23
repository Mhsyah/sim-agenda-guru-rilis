<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Period;
use App\Models\SchoolProfileModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /** @var IncomingRequest */
    protected $request;

    protected $helpers = ['url', 'form', 'app'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        self::$profileLoaded = false; // cache berlaku per request
        \App\Libraries\Lookup::reset();
        Period::reset();
        \App\Libraries\KopSettings::resetOverride();
    }

    private static ?array $profileCache = null;
    private static bool $profileLoaded  = false;

    /** Profil sekolah, dibaca sekali per request. */
    public static function schoolProfile(): ?array
    {
        if (! self::$profileLoaded) {
            self::$profileCache  = (new SchoolProfileModel())->first();
            self::$profileLoaded = true;
        }

        return self::$profileCache;
    }

    public static function forgetProfile(): void
    {
        self::$profileLoaded = false;
    }

    /** Render view dengan data umum layout (pengguna, periode, profil). */
    protected function render(string $view, array $data = []): string
    {
        $common = [
            'authUser' => Auth::user(),
            'period'   => Period::get(),
            'profile'  => self::schoolProfile(),
            'errors'   => session('errors') ?? [],
            'ownKop'   => false,
        ];

        return view($view, $data + $common);
    }

    /** Ambil input POST, string kosong menjadi null. */
    protected function postClean(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $this->request->getPost($k);
            if (is_string($v)) {
                $v = trim($v);
                $v = $v === '' ? null : $v;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    protected function back(string $error, array $errors = [])
    {
        return redirect()->back()->withInput()->with('error', $error)->with('errors', $errors);
    }

    protected function requirePeriod()
    {
        if (! Period::yearId()) {
            return redirect()->to(site_url('academic-years'))
                ->with('error', 'Buat tahun ajaran terlebih dahulu sebelum mengisi data akademik.');
        }

        return null;
    }
}
