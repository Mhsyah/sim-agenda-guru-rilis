<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$pw = static fn ($name, $label, $ac, $help = '') => ['label' => $label, 'type' => 'password', 'required' => true, 'value' => '', 'attrs' => ['autocomplete' => $ac], 'help' => $help];
?>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>
<div class="grid max-w-5xl gap-5 lg:grid-cols-2">
    <section class="card card-organic" aria-labelledby="pw-title">
        <h2 id="pw-title" class="font-display text-xl font-bold">Ganti kata sandi</h2>
        <form method="post" action="<?= site_url('settings/password') ?>" class="mt-4 grid gap-4" novalidate>
            <?= csrf_field() ?>
            <?= view('partials/field', ['name' => 'current_password', 'f' => $pw('current_password', 'Kata sandi saat ini', 'current-password'), 'errors' => $errors]) ?>
            <?= view('partials/field', ['name' => 'new_password', 'f' => $pw('new_password', 'Kata sandi baru', 'new-password', 'Minimal 10 karakter. Gunakan frasa yang mudah diingat namun sulit ditebak.'), 'errors' => $errors]) ?>
            <?= view('partials/field', ['name' => 'new_password2', 'f' => $pw('new_password2', 'Ulangi kata sandi baru', 'new-password'), 'errors' => $errors]) ?>
            <div><button class="btn btn-primary">Simpan kata sandi</button></div>
        </form>
    </section>

    <section class="card" aria-labelledby="sec-title">
        <h2 id="sec-title" class="font-display text-xl font-bold">Perlindungan aktif</h2>
        <ul class="mt-3 grid gap-2 text-sm">
            <li class="flex gap-2"><?= icon('check', 'size-5 shrink-0 text-ok') ?> Kata sandi di-hash dengan <code>password_hash()</code> PHP.</li>
            <li class="flex gap-2"><?= icon('check', 'size-5 shrink-0 text-ok') ?> Proteksi CSRF, escaping output, dan Query Builder dengan parameter binding.</li>
            <li class="flex gap-2"><?= icon('check', 'size-5 shrink-0 text-ok') ?> Login dikunci 5 menit setelah 5 kali gagal.</li>
            <li class="flex gap-2"><?= icon('check', 'size-5 shrink-0 text-ok') ?> Sesi diperbarui saat login dan cookie <code>HttpOnly</code>/<code>SameSite</code>.</li>
            <li class="flex gap-2"><?= icon(service('request')->isSecure() ? 'check' : 'alert', 'size-5 shrink-0 ' . (service('request')->isSecure() ? 'text-ok' : 'text-warn')) ?>
                <?= service('request')->isSecure() ? 'Koneksi HTTPS aktif.' : 'Koneksi belum HTTPS. Aktifkan SSL di hosting Anda.' ?></li>
        </ul>
    </section>

</div>
<?= $this->endSection() ?>
