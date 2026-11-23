<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\KopSettings;
use App\Libraries\SchoolLogo;

$kop = KopSettings::get($profile);
// Nilai setelah galat validasi (old input) diutamakan.
$oldKop = old('kop', null, false);
$o = static fn (string $k) => (string) (is_array($oldKop) && array_key_exists($k, $oldKop) ? $oldKop[$k] : $kop[$k]);

$fields = [
    'school_name'    => ['label' => 'Nama sekolah', 'rules' => 'required', 'value' => $profile['school_name'] ?? '', 'span' => 'full', 'help' => 'Tampil di menu, halaman login, dan judul cetakan.'],
    'npsn'           => ['label' => 'NPSN', 'value' => $profile['npsn'] ?? ''],
    'school_city'    => ['label' => 'Kota/kabupaten (tempat tanda tangan)', 'value' => $profile['school_city'] ?? '', 'placeholder' => 'Jakarta'],
    'school_address' => ['label' => 'Alamat sekolah', 'type' => 'textarea', 'value' => $profile['school_address'] ?? ''],
    'school_phone'   => ['label' => 'Telepon', 'value' => $profile['school_phone'] ?? ''],
    'school_email'   => ['label' => 'Email sekolah', 'type' => 'email', 'value' => $profile['school_email'] ?? ''],
    'school_website' => ['label' => 'Website sekolah', 'value' => $profile['school_website'] ?? '', 'placeholder' => 'www.sekolah.sch.id'],
    'principal_name' => ['label' => 'Nama kepala sekolah', 'value' => $profile['principal_name'] ?? '', 'help' => 'Untuk tanda tangan “Mengetahui” di cetakan.'],
    'principal_nip'  => ['label' => 'NIP kepala sekolah', 'value' => $profile['principal_nip'] ?? ''],
    'timezone'       => ['label' => 'Zona waktu', 'type' => 'select', 'options' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'], 'rules' => 'required', 'value' => $profile['timezone'] ?? 'Asia/Jakarta'],
];
/** Kolom teks kop. */
$txt = static function (string $name, string $label, string $ph = '') use ($o, $errors) { ?>
    <div class="field">
        <label class="label" for="k-<?= $name ?>"><?= esc($label) ?></label>
        <input class="input" id="k-<?= $name ?>" name="kop[<?= $name ?>]" value="<?= esc($o($name), 'attr') ?>" placeholder="<?= esc($ph, 'attr') ?>" data-kop-in="<?= $name ?>" maxlength="250">
    </div>
<?php };
/** Pilihan (select). */
$sel = static function (string $name, string $label, array $opts) use ($o) { ?>
    <div class="field">
        <label class="label" for="k-<?= $name ?>"><?= esc($label) ?></label>
        <select class="input" id="k-<?= $name ?>" name="kop[<?= $name ?>]" data-kop-in="<?= $name ?>">
            <?php foreach ($opts as $v => $l): ?><option value="<?= esc($v, 'attr') ?>" <?= $o($name) === (string) $v ? 'selected' : '' ?><?= is_array($l) && str_starts_with($name, 'font') ? ' style="font-family:' . esc($l[1], 'attr') . '"' : '' ?>><?= esc(is_array($l) ? $l[0] : $l) ?></option><?php endforeach ?>
        </select>
    </div>
<?php };
$num = static function (string $name, string $label, int $min, int $max) use ($o) { ?>
    <div class="field">
        <label class="label" for="k-<?= $name ?>"><?= esc($label) ?></label>
        <input class="input" type="number" inputmode="numeric" id="k-<?= $name ?>" name="kop[<?= $name ?>]" min="<?= $min ?>" max="<?= $max ?>" step="1" value="<?= esc($o($name), 'attr') ?>" data-kop-in="<?= $name ?>">
    </div>
<?php };
/** Unggah gambar (logo/foto sekolah) dengan pratinjau. */
$img = static function (string $name, string $label, ?string $url, string $help, string $removeName, bool $wide = false) use ($errors) { ?>
    <div class="img-pick">
        <div class="img-pick-box<?= $wide ? ' is-wide' : '' ?>"><?php if ($url): ?><img src="<?= esc($url, 'attr') ?>" alt="<?= esc($label, 'attr') ?> saat ini"><?php else: ?><span>Belum ada</span><?php endif ?></div>
        <div class="field min-w-0 flex-1">
            <label class="label" for="<?= $name ?>"><?= esc($label) ?></label>
            <input class="input py-2 text-sm" type="file" id="<?= $name ?>" name="<?= $name ?>" accept="image/png,image/jpeg,image/webp" aria-describedby="<?= $name ?>-help">
            <p class="help" id="<?= $name ?>-help"><?= esc($help) ?></p>
            <?php if (isset($errors[$name])): ?><p class="error-text"><?= esc($errors[$name]) ?></p><?php endif ?>
            <?php if ($url): ?><label class="check"><input type="checkbox" name="<?= $removeName ?>" value="1"> <span class="text-sm">Hapus</span></label><?php endif ?>
        </div>
    </div>
<?php };
?>
<h1 class="page-title"><?= esc($title) ?></h1>
<p class="mt-2 mb-5 max-w-2xl text-muted">Data sekolah, logo, kop surat, dan pengaturan cetak dipakai bersama oleh semua guru di semua dokumen cetak (Buku Jurnal, laporan, rekap nilai).</p>

<?= view('partials/school_banner', ['profile' => $profile, 'period' => $period, 'editUrl' => '#sec-school']) ?>

<form method="post" action="<?= site_url('settings/school') ?>" class="grid gap-6 pb-2" enctype="multipart/form-data" novalidate id="school-form">
    <?= csrf_field() ?>

    <!-- 1. Data sekolah -->
    <section class="card" aria-labelledby="sec-school">
        <h2 id="sec-school" class="mb-4 text-[0.95rem] font-semibold tracking-tight">Data Sekolah &amp; Logo</h2>
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <?php $img('gov_logo', 'Logo instansi · kiri kop', SchoolLogo::assetUrl($profile['gov_logo'] ?? null, 'gov'), 'Logo pemerintah daerah (mis. Jaya Raya). Opsional. PNG/JPG/WebP, maks. 1 MB.', 'remove_gov_logo') ?>
            <?php $img('school_logo', 'Logo sekolah · kanan kop', SchoolLogo::url($profile), 'Juga dipakai di menu, halaman login, dan favicon. PNG/JPG/WebP, maks. 1 MB.', 'remove_logo') ?>
            <?php $img('school_photo', 'Foto sekolah · spanduk & sampul', SchoolLogo::assetUrl($profile['school_photo'] ?? null, 'cover'), 'Foto gedung/halaman sekolah untuk spanduk profil (halaman ini & dashboard guru) dan sampul Buku Agenda Guru. Disarankan mendatar (lanskap) min. 1600 px agar tajam; otomatis dikonversi ke WebP.', 'remove_school_photo', true) ?>
        </div>
        <div class="-mt-2 mb-5 flex flex-wrap items-center gap-3 text-xs text-muted">
            <span>Logo tertukar?</span>
            <button type="submit" name="swap_logos" value="1" class="btn btn-ghost btn-sm" formnovalidate data-confirm-btn="Tukar posisi logo sekolah dan logo instansi? Isian lain di halaman ini tidak ikut disimpan."><?= icon('refresh', 'size-4') ?> Tukar logo sekolah ⇄ instansi</button>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <?php foreach ($fields as $name => $f):
                $f['type'] ??= 'text';
                $f['required'] = str_contains($f['rules'] ?? '', 'required');
                $f['value'] = old($name, $f['value'], false); ?>
                <?= view('partials/field', ['name' => $name, 'f' => $f, 'errors' => $errors]) ?>
            <?php endforeach ?>
        </div>
    </section>

    <!-- 2. Kop surat + pratinjau langsung -->
    <section class="card" aria-labelledby="sec-kop" data-kop-editor
             data-defaults="<?= esc(json_encode(KopSettings::textDefaults($profile) + KopSettings::STYLE_DEFAULTS), 'attr') ?>"
             data-fonts="<?= esc(json_encode(array_map(static fn ($f) => $f[1], KopSettings::FONTS)), 'attr') ?>">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="sec-kop" class="text-[0.95rem] font-semibold tracking-tight">Kop Surat</h2>
            <button type="button" class="btn btn-ghost btn-sm" data-kop-reset><?= icon('refresh', 'size-4') ?> Kembalikan bawaan</button>
        </div>
        <div class="grid gap-6">
            <div class="grid content-start gap-4 md:grid-cols-2">
                <?php $txt('line1', 'Baris 1 (pemerintah)', 'PEMERINTAH PROVINSI …') ?>
                <?php $txt('line2', 'Baris 2 (instansi)', 'DINAS PENDIDIKAN') ?>
                <?php $txt('line3', 'Baris 3 (nama sekolah)', 'SEKOLAH MENENGAH PERTAMA …') ?>
                <?php $txt('addr1', 'Baris alamat', 'Jl. …, Kelurahan …, Kecamatan …') ?>
                <?php $txt('addr2', 'Baris alamat 2 (telepon/web/email)', 'Telepon : …, E-mail : …') ?>
                <?php $txt('city', 'Baris kota (mis. JAKARTA)', 'JAKARTA') ?>
                <?php $txt('postal', 'Kode pos, rata kanan (mis. Kode pos 14440)', '14440') ?>
                <div class="grid gap-4 sm:grid-cols-2 md:col-span-2 xl:grid-cols-4">
                    <?php $sel('font', 'Jenis huruf baris 1–3', KopSettings::FONTS) ?>
                    <?php $sel('font_addr', 'Jenis huruf alamat & kota', KopSettings::FONTS) ?>
                    <?php $sel('case', 'Huruf baris 1–3', KopSettings::CASES) ?>
                    <?php $sel('weight', 'Ketebalan baris 1–3', KopSettings::WEIGHTS) ?>
                    <?php $sel('rule', 'Garis bawah kop', KopSettings::RULES) ?>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 md:col-span-2">
                    <?php $num('size1', 'Baris 1 (px)', 8, 28) ?>
                    <?php $num('size2', 'Baris 2 (px)', 8, 28) ?>
                    <?php $num('size3', 'Baris 3 (px)', 8, 32) ?>
                    <?php $num('size_addr', 'Alamat (px)', 7, 20) ?>
                </div>
            </div>
            <div class="order-first">
                <p class="label mb-1.5">Pratinjau kop</p>
                <div class="kop-preview" data-kop-preview>
                    <div class="kop-sheet" data-kop-sheet>
                        <?= view('partials/kop', ['profile' => $profile, 'kop' => $kop], ['saveData' => false]) ?>
                        <div class="kop-sheet-lines" aria-hidden="true"><i></i><i></i><i style="width:72%"></i><i></i><i style="width:55%"></i></div>
                    </div>
                </div>
                <p class="help mt-2">Pratinjau selebar kertas <?= esc(KopSettings::PAPERS[$kop['paper']][0] ?? 'A4') ?> dan mendekati hasil cetak/PDF. Jenis huruf yang tidak terpasang di perangkat ini memakai padanan terdekat.</p>
            </div>
        </div>
    </section>

    <!-- 3. Pengaturan cetak dokumen -->
    <section class="card" aria-labelledby="sec-print">
        <h2 id="sec-print" class="text-[0.95rem] font-semibold tracking-tight">Pengaturan Cetak Dokumen</h2>
        <p class="mb-4 mt-0.5 text-sm text-muted">Bawaan untuk semua cetakan &amp; simpan PDF (Buku Jurnal, laporan, rekap nilai, jadwal).</p>
        <div class="grid gap-4 md:grid-cols-3">
            <?php $sel('paper', 'Ukuran kertas', KopSettings::PAPERS) ?>
            <?php $sel('date_mode', 'Tanggal surat/tanda tangan', KopSettings::DATE_MODES) ?>
            <div class="field" data-show-date <?= $o('date_mode') === 'manual' ? '' : 'hidden' ?>>
                <label class="label" for="k-sign_date">Tanggal tanda tangan</label>
                <input class="input" type="date" id="k-sign_date" name="kop[sign_date]" value="<?= esc($o('sign_date'), 'attr') ?>">
            </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
            <?php $num('margin_top', 'Margin atas (mm)', 0, 50) ?>
            <?php $num('margin_right', 'Margin kanan (mm)', 0, 50) ?>
            <?php $num('margin_bottom', 'Margin bawah (mm)', 0, 50) ?>
            <?php $num('margin_left', 'Margin kiri (mm)', 0, 50) ?>
        </div>
        <div class="mt-5 grid gap-3 border-t border-line pt-4">
            <input type="hidden" name="kop[show_cover_photo]" value="0">
            <label class="check items-start"><input type="checkbox" name="kop[show_cover_photo]" value="1" <?= $o('show_cover_photo') === '1' ? 'checked' : '' ?> data-kop-in="show_cover_photo">
                <span><span class="text-sm font-medium">Tampilkan foto sekolah pada sampul Buku Agenda Guru</span>
                <span class="block text-xs text-muted">Memakai foto sekolah yang diunggah di atas.</span></span></label>
            <input type="hidden" name="kop[show_logo]" value="0">
            <label class="check items-start"><input type="checkbox" name="kop[show_logo]" value="1" <?= $o('show_logo') === '1' ? 'checked' : '' ?> data-kop-in="show_logo">
                <span><span class="text-sm font-medium">Tampilkan logo instansi &amp; logo sekolah pada kop cetak</span>
                <span class="block text-xs text-muted">Tanpa centang = kop hanya memakai logo instansi (kiri). Dicentang = logo instansi di kiri dan logo sekolah di kanan.</span></span></label>
        </div>

        <!-- Pratinjau sampul Buku Agenda Guru (potret, blanko Cover). Data guru terisi otomatis saat guru mencetak. -->
        <div class="mt-6 border-t border-line pt-5">
            <p class="label mb-1">Pratinjau sampul Buku Agenda Guru</p>
            <p class="mb-3 text-xs text-muted">Halaman pertama Buku Jurnal (potret). Garis titik-titik terisi otomatis dengan data guru saat dicetak. Foto latar memakai foto sekolah di atas bila sudah diunggah. Simpan dulu agar perubahan kop/logo terlihat di sini.</p>
            <div class="mx-auto max-w-sm">
                <?= view('partials/journal_cover', ['profile' => $profile, 'kop' => $kop, 'teacher' => [
                    'semester' => (string) ($period['semester']['name'] ?? ''), 'year' => (string) ($period['year']['name'] ?? ''),
                ], 'blank' => \App\Libraries\CoverBlank::resolve(null, $profile)], ['saveData' => false]) ?>
                <p class="mt-2 text-center text-xs"><a href="<?= site_url('cover') ?>">Unggah / ganti blanko sampul default →</a></p>
            </div>
        </div>
    </section>

    <div class="action-bar no-print" role="region" aria-label="Simpan pengaturan">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="mr-auto text-sm text-muted">Perubahan berlaku untuk semua guru setelah disimpan.</p>
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan Pengaturan</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
