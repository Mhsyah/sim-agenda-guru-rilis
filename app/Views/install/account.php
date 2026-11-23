<?= $this->extend('layouts/plain') ?>
<?= $this->section('content') ?>
<?php
$f = static fn ($label, $type = 'text', $req = true, $help = '', $attrs = []) => ['label' => $label, 'type' => $type, 'required' => $req, 'help' => $help, 'attrs' => $attrs];
$admin = [
    'school_name' => $f('Nama sekolah') + ['span' => 'full'],
    'admin_name'  => $f('Nama admin teknis'),
    'email'       => $f('Email login admin', 'email', true, '', ['autocomplete' => 'username']),
    'password'    => $f('Kata sandi admin', 'password', true, 'Minimal 10 karakter.', ['autocomplete' => 'new-password']),
    'password2'   => $f('Ulangi kata sandi', 'password', true, '', ['autocomplete' => 'new-password']),
];
$teacher = [
    'teacher_name'     => $f('Nama guru', 'text', false),
    'teacher_email'    => $f('Email login guru', 'email', false),
    'teacher_password' => $f('Kata sandi guru', 'password', false, 'Minimal 10 karakter.', ['autocomplete' => 'new-password']),
];
$render = static function (array $fields) use ($errors) {
    foreach ($fields as $name => $def) {
        $def['value'] = str_contains($name, 'password') ? '' : old($name, '', false);
        echo view('partials/field', ['name' => $name, 'f' => $def, 'errors' => $errors]);
    }
};
?>
<p class="text-sm font-semibold text-muted">Langkah 2 dari 2</p>
<h1 class="page-title mt-1">Akun admin teknis</h1>
<p class="mt-2 text-muted">Tabel database dibuat otomatis. Admin teknis mengelola data sekolah, guru, kelas, siswa, impor, backup, dan status sistem. Guru memakai akun masing-masing untuk agenda dan penilaian.</p>

<form method="post" action="<?= site_url('install/finish') ?>" class="card card-organic mt-6" novalidate>
    <?= csrf_field() ?>
    <div class="grid gap-4 sm:grid-cols-2"><?php $render($admin) ?></div>

    <fieldset class="mt-6 rounded-2xl border border-line p-4">
        <legend class="label px-1">Akun guru pertama (opsional)</legend>
        <p class="help mb-3">Lewati bila guru akan ditambahkan nanti oleh admin (satu per satu atau lewat Impor Data).</p>
        <div class="grid gap-4 sm:grid-cols-2"><?php $render($teacher) ?></div>
    </fieldset>

    <label class="check mt-4"><input type="checkbox" name="demo" value="1"> <span class="text-sm font-medium">Isi data contoh fiktif (kelas, siswa, CP/TP, agenda, nilai) untuk mencoba aplikasi</span></label>
    <div class="mt-6 border-t border-line pt-5"><button class="btn btn-primary">Selesaikan instalasi</button></div>
</form>
<?= $this->endSection() ?>
