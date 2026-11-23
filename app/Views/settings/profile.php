<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\Auth;

$fields = [
    'name'  => ['label' => 'Nama lengkap (dengan gelar)', 'required' => true, 'value' => old('name', $authUser['name'], false), 'span' => 'full', 'help' => 'Tampil di kop cetakan dan tanda tangan.'],
    'nip'   => ['label' => 'NIP', 'value' => old('nip', $authUser['nip'] ?? '', false), 'help' => 'Kosongkan bila tidak ada.'],
    'email' => ['label' => 'Email login', 'type' => 'email', 'required' => true, 'value' => old('email', $authUser['email'], false)],
    'gender' => ['label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan'], 'empty' => '— Pilih —', 'value' => old('gender', $authUser['gender'] ?? '', false), 'help' => 'Menentukan foto bawaan bila belum mengunggah foto.'],
];
?>
<div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_18rem]">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 mb-6 text-muted">Data diri yang dipakai di cetakan Buku Jurnal, laporan, dan tanda tangan.</p>
        <form method="post" action="<?= site_url('settings/profile') ?>" class="card card-organic" novalidate>
            <?= csrf_field() ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <?php foreach ($fields as $name => $f): $f['type'] ??= 'text'; $f['required'] ??= false; ?>
                    <?= view('partials/field', ['name' => $name, 'f' => $f, 'errors' => $errors]) ?>
                <?php endforeach ?>
            </div>
            <div class="mt-7 flex flex-wrap gap-2 border-t border-line pt-5">
                <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan profil</button>
                <a class="btn btn-ghost" href="<?= site_url('settings/security') ?>"><?= icon('shield', 'size-4') ?> Ganti kata sandi</a>
            </div>
        </form>
        <?php if ($authUser['role'] === 'teacher'): ?>
            <!-- Guru mengatur sendiri mapel & kelas yang diajarkan (termasuk mapel tambahan) -->
            <form method="post" action="<?= site_url('settings/teaching') ?>" class="card mt-6" id="teaching">
                <?= csrf_field() ?>
                <?= view('partials/teach_picker', [
                    'subjects' => \App\Libraries\Lookup::subjects(),
                    'classes'  => \App\Libraries\Lookup::classes(),
                    'checked'  => \App\Controllers\Users::subjectIds((int) $authUser['id']),
                    'teach'    => \App\Controllers\Users::teachingMap((int) $authUser['id']),
                    'help'     => 'Centang mapel yang Anda ajarkan, termasuk mapel tambahan, lalu pilih kelasnya (T.P. ' . ($period['year']['name'] ?? '') . '). Pilihan mapel di Agenda, Jurnal, Jadwal, dan Asesmen mengikuti daftar ini. Mapel belum ada di daftar? Minta admin teknis menambahkannya.',
                ], ['saveData' => false]) ?>
                <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4">
                    <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan mapel &amp; kelas</button>
                </div>
            </form>
        <?php endif ?>
        <div class="mt-6 grid gap-4 xl:grid-cols-2 [&>section]:mt-0"><?= view('partials/account_status', ['acct' => $authUser], ['saveData' => false]) ?></div>
    </div>
    <aside class="card order-first self-start lg:order-none lg:mt-20" aria-labelledby="photo-title">
        <h2 id="photo-title" class="sr-only">Foto profil</h2>
        <div class="flex flex-col items-center text-center">
            <div class="relative">
                <span data-photo-preview><?= user_avatar($authUser, 'size-28', 'text-3xl ring-4 ring-surface-2') ?></span>
                <label for="photo-gallery" class="absolute -right-1 bottom-1 grid size-9 cursor-pointer place-items-center rounded-full border-2 border-surface bg-primary text-primary-ink shadow" title="Ganti foto">
                    <?= icon('camera', 'size-4') ?><span class="sr-only">Ganti foto profil</span>
                </label>
            </div>
            <p class="mt-3 text-lg font-bold tracking-tight"><?= esc($authUser['name']) ?></p>
            <p class="max-w-full truncate text-sm text-muted"><?= esc($authUser['email']) ?></p>
            <p class="mt-2"><?= badge(Auth::ROLES[$authUser['role']] ?? $authUser['role'], $authUser['role'] === 'admin' ? 'neutral' : 'info') ?></p>
        </div>

        <form method="post" action="<?= site_url('settings/photo') ?>" enctype="multipart/form-data" class="mt-5 border-t border-line pt-4" data-photo-form>
            <?= csrf_field() ?>
            <p class="mb-2 text-sm font-semibold">Foto profil</p>
            <div class="grid grid-cols-2 gap-2">
                <label class="btn btn-ghost btn-sm cursor-pointer" for="photo-camera"><?= icon('camera', 'size-4') ?> Kamera</label>
                <label class="btn btn-ghost btn-sm cursor-pointer" for="photo-gallery"><?= icon('image', 'size-4') ?> Galeri/File</label>
            </div>
            <!-- Kamera langsung (HP) & galeri/file; keduanya memakai nama "photo" — yang dipilih saja yang dikirim -->
            <input id="photo-camera" type="file" accept="image/*" capture="user" class="sr-only" data-photo-input>
            <input id="photo-gallery" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/*" class="sr-only" data-photo-input>
            <p class="help mt-2">JPG/PNG/WebP, otomatis dipotong persegi &amp; dikonversi ke WebP.</p>
            <p class="mt-2 hidden text-sm font-medium" data-photo-status aria-live="polite"></p>
            <noscript><button class="btn btn-primary btn-sm mt-2 w-full">Unggah foto</button></noscript>
        </form>
        <?php if (! empty($authUser['photo'])): ?>
            <form method="post" action="<?= site_url('settings/photo/delete') ?>" data-confirm="Hapus foto profil?" data-confirm-label="Ya, hapus" class="mt-2">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm w-full text-bad"><?= icon('trash', 'size-4') ?> Hapus foto</button>
            </form>
        <?php endif ?>

        <div class="mt-4 border-t border-line pt-3 text-xs text-muted">
            <p>Sekolah: <?= esc($profile['school_name'] ?? '—') ?></p>
            <p>Login terakhir: <?= $authUser['last_login_at'] ? esc($authUser['last_login_at']) : '—' ?></p>
        </div>
    </aside>
</div>
<?= $this->endSection() ?>
