<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-2 max-w-3xl text-muted">Unggah PDF jadwal pelajaran yang sudah jadi (format sekolah: kolom kelas berisi <strong>KD</strong> kode guru dan <strong>MP</strong> mapel, satu file per sesi Pagi/Siang).
        Sistem membaca jadwal, jam kegiatan (Pembiasaan, Istirahat, MBG), dan guru piket, lalu menampilkan pratinjau beserta masalah yang ditemukan sebelum disimpan.</p>
</div>
<div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <form method="post" action="<?= site_url('schedule-builder/upload') ?>" enctype="multipart/form-data" class="card" data-progress="Membaca PDF jadwal…">
        <?= csrf_field() ?>
        <label class="label" for="sb-pdf">File PDF jadwal</label>
        <input class="input" type="file" id="sb-pdf" name="pdf" accept="application/pdf,.pdf" required>
        <p class="mt-1 text-xs text-muted">Maks. 10 MB. Halaman pertama berisi tabel jadwal.</p>
        <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" class="bulk-check mt-0.5" name="suggest" value="1" checked>
            <span>Terapkan saran perbaikan otomatis bila kode guru/mapel tidak sesuai pembagian mengajar (mis. salah ketik kode). Semua perubahan tetap ditampilkan di pratinjau.</span></label>
        <div class="mt-5 flex justify-end"><button class="btn btn-primary"><?= icon('upload', 'size-4') ?> Baca & pratinjau</button></div>
    </form>
    <aside class="card text-sm">
        <h2 class="mb-2 font-bold">Agar terbaca dengan baik</h2>
        <ul class="list-disc space-y-1.5 pl-5 text-muted">
            <li>Kode guru di PDF sama dengan <a href="<?= site_url('users?role=teacher') ?>">Kode guru</a> di data guru.</li>
            <li>Nama kelas (mis. 7A) sudah ada di Kelas/Rombel T.P. aktif.</li>
            <li>Singkatan mapel dikenali: B.IND, B.ING, PRKY, SB, dan kode mapel di aplikasi.</li>
            <li>PDF berasal dari Excel/Word (berisi teks), bukan hasil pindai gambar.</li>
        </ul>
    </aside>
</div>
<?= $this->endSection() ?>
