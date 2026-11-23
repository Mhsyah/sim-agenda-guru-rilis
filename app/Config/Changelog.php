<?php

namespace Config;

/**
 * Catatan rilis bawaan aplikasi (offline). Versi terbaru di urutan pertama.
 * Saat merilis versi baru: tambahkan entri di atas dan ubah app_version().
 */
class Changelog
{
    /** @var list<array{version: string, date: string, notes: list<string>}> */
    public static array $releases = [
        [
            'version' => '0.2.4',
            'date'    => '2026-11-23',
            'notes'   => [
                'Perbaikan pembaruan otomatis: unduhan GitHub yang dialihkan (302) kini diikuti.',
                'Folder vendor ikut diperbarui dari paket rilis; instalasi lama dari repo kode dikenali & diperbarui.',
            ],
        ],
        [
            'version' => '0.2.3',
            'date'    => '2026-11-23',
            'notes'   => [
                'Pembaruan otomatis TANPA token GitHub: aplikasi memperbarui diri dari repo rilis publik (kode development tetap private).',
                'Folder development (clone repo kode) tidak dipasang otomatis; rilis diterbitkan dengan build-release.php --publish.',
            ],
        ],
        [
            'version' => '0.2.2',
            'date'    => '2026-11-23',
            'notes'   => [
                '"Cek & pasang pembaruan": versi/kode baru langsung dipasang otomatis dalam satu klik (dapat dimatikan: appupdate.autoInstall).',
                'Instalasi hasil git clone kini bisa diperbarui dari aplikasi (backup → git pull → migrasi), dengan pengaman bila ada berkas diubah manual.',
            ],
        ],
        [
            'version' => '0.2.1',
            'date'    => '2026-11-23',
            'notes'   => [
                'Menu "Kalender & Agenda": Agenda & Jurnal diisi langsung dari popup tanggal (tampilan tabel: TP, kehadiran, status, Ubah/Hapus).',
                'Semester (Ganjil/Genap/Keduanya) pada CP, TP, unggah CP/TP/ATP/KKTP, dan arsip dokumen; pilihan TP mengikuti semester aktif.',
                'Halaman ATP & KKTP (saringan mapel/kelas/semester, cetak dengan kop) dan rekap Ketercapaian KKTP per semester.',
                'Tombol "Pasang pembaruan" dari GitHub (backup, pasang, migrasi otomatis) dan "Jalankan migrasi" di Status Sistem.',
                'Cek pembaruan juga mendeteksi commit baru; alamat rilis dikunci; pesan token lebih jelas.',
            ],
        ],
        [
            'version' => '0.2.0',
            'date'    => '2026-11-12',
            'notes'   => [
                'Nilai Tugas Harian, aturan "tidak kumpul", dan tab asesmen (Tugas Harian · UH · ATS · AAS).',
                'Menu Penilaian berbentuk pohon di sidebar guru.',
                'Peringatan rombel melebihi 36 peserta didik dan kelas akhir yang belum diluluskan.',
                'Cetak jadwal keseluruhan dengan tulisan lebih besar dan pilihan ukuran kertas.',
                'Galat di Status Sistem dikelompokkan dan bisa ditandai sudah ditangani.',
                'Info versi aplikasi dan pengecekan pembaruan.',
                'Blanko sampul Buku Agenda: default dari admin, guru dapat memakai blanko sendiri.',
                'Unggah CP, TP, ATP & KKTP (data Excel/CSV dengan pratinjau + arsip dokumen PDF/DOCX); tingkat ketercapaian KKTP di input nilai.',
                'Impor Data diperbarui: tanda duplikat & galat per baris di pratinjau sebelum disimpan.',
                'Status Sistem lebih sederhana, header keamanan tambahan, dan pemeriksa keutuhan file pada paket rilis.',
                'Kode mata pelajaran berwarna di daftar mapel, guru, CP, dan TP.',
                'Sinkron Dapodik: Web Service atau berkas ekspor, dengan pratinjau perbedaan sebelum diterapkan.',
                'Pembatas akses (rate limiter) & daftar IP diatur dari .env.',
            ],
        ],
        [
            'version' => '0.1.0',
            'date'    => '2026-10-29',
            'notes'   => [
                'Rilis awal: agenda & jurnal mengajar, jadwal, kalender, asesmen & rekap nilai, impor data, backup.',
            ],
        ],
    ];
}
