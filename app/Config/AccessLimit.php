<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Pembatas akses (rate limiter) & daftar IP. Semua nilai dapat diubah dari .env, contoh:
 *
 *   accesslimit.enabled         = true
 *   accesslimit.perMinute       = 120        ; permintaan halaman per IP per menit
 *   accesslimit.login           = 10/300     ; percobaan login per IP per 300 detik
 *   accesslimit.upload          = 20/60      ; unggahan file per IP per 60 detik
 *   accesslimit.blockedIps      = 203.0.113.5, 198.51.100.*
 *   accesslimit.adminAllowedIps =            ; kosong = admin boleh dari mana saja
 *
 * Pembatas ini melindungi dari tebak sandi & banjir permintaan biasa. Untuk serangan besar (DDoS)
 * tetap gunakan perlindungan hosting/Cloudflare.
 */
class AccessLimit extends BaseConfig
{
    public bool $enabled = true;

    public int $perMinute = 120;

    /** "jumlah/detik" */
    public string $login = '10/300';

    /** "jumlah/detik" */
    public string $upload = '20/60';

    /** Pisahkan dengan koma; dukung awalan dengan "*" (mis. 10.0.0.*). */
    public string $blockedIps = '';

    /** Bila diisi, akun admin teknis hanya boleh dipakai dari IP ini (mis. IP publik sekolah). */
    public string $adminAllowedIps = '';

    public function __construct()
    {
        parent::__construct();
        // Pengujian otomatis mengirim ratusan permintaan dari satu IP: nonaktif kecuali diaktifkan oleh tes.
        if (ENVIRONMENT === 'testing') {
            $this->enabled = false;
        }
    }
}
