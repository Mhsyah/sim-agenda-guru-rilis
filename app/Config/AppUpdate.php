<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Pengecekan & pemasangan pembaruan aplikasi. Sumber = repo RILIS PUBLIK (hanya berisi paket rilis siap pakai),
 * sehingga TIDAK memerlukan token GitHub. Kode development tetap di repo private terpisah.
 * Nilai dapat diganti dari .env oleh pengelola server:
 *
 *   appupdate.source      = Mhsyah/sim-agenda-guru-rilis
 *   appupdate.autoCheck   = true
 *   appupdate.githubToken =                  ; opsional, hanya bila sumber adalah repo private
 *
 * Buat di GitHub: Settings → Developer settings → Fine-grained tokens → pilih repo ini saja,
 * izin "Contents: Read-only". Token tidak pernah ditampilkan di aplikasi.
 */
class AppUpdate extends BaseConfig
{
    /** Repo GitHub "pemilik/repo" atau URL https ke berkas JSON versi. */
    public string $source = 'Mhsyah/sim-agenda-guru-rilis';

    /** Cek otomatis 1× sehari (notifikasi lonceng admin bila ada versi baru). */
    public bool $autoCheck = true;

    /** Saat admin menekan "Cek pembaruan" dan ada versi/kode baru: langsung dipasang (backup → pasang → migrasi). */
    public bool $autoInstall = true;

    public string $githubToken = '';
}
