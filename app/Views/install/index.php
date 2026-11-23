<?= $this->extend('layouts/plain') ?>
<?= $this->section('content') ?>
<?php $o = static fn ($k, $d = '') => old($k, $d, false); ?>
<p class="text-sm font-semibold text-muted">Langkah 1 dari 2</p>
<h1 class="page-title mt-1">Instalasi Agenda Guru</h1>
<p class="mt-2 text-muted">Wizard ini memeriksa server, menguji koneksi database, lalu menyimpan konfigurasi ke file <code>.env</code>. Setelah instalasi selesai, wizard otomatis terkunci.</p>

<section class="card mt-6" aria-labelledby="req-title">
    <h2 id="req-title" class="font-display text-xl font-bold">Pemeriksaan server</h2>
    <ul class="mt-3 grid gap-1.5 text-sm">
        <?php foreach ($checks as [$label, $pass, $required]): ?>
            <li class="flex items-start gap-2">
                <?= $pass ? icon('check', 'size-5 shrink-0 text-ok') : icon('alert', 'size-5 shrink-0 ' . ($required ? 'text-bad' : 'text-warn')) ?>
                <span><?= esc($label) ?> — <strong><?= $pass ? 'OK' : ($required ? 'Wajib, belum terpenuhi' : 'Opsional') ?></strong></span>
            </li>
        <?php endforeach ?>
    </ul>
</section>

<?php if ($envText): ?>
    <section class="card mt-6" aria-labelledby="env-title">
        <h2 id="env-title" class="font-display text-xl font-bold">Salin ke file .env</h2>
        <p class="mt-1 text-sm text-muted">Buat/ubah file <code>.env</code> di folder utama aplikasi (sejajar dengan folder <code>app</code>) melalui File Manager hosting, tempel isi berikut, lalu buka <a href="<?= site_url('install/account') ?>">langkah 2</a>.</p>
        <label class="sr-only" for="envtext">Isi file .env</label>
        <textarea id="envtext" class="input mt-3 font-mono text-xs" rows="16" readonly><?= esc($envText) ?></textarea>
    </section>
<?php endif ?>

<form method="post" action="<?= site_url('install/database') ?>" class="card card-organic mt-6" novalidate>
    <?= csrf_field() ?>
    <h2 class="font-display text-xl font-bold">Koneksi database</h2>
    <p class="mt-1 text-sm text-muted">Buat database &amp; user terlebih dahulu di panel hosting (mis. cPanel → MySQL Databases), lalu isi di bawah.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="field sm:col-span-2">
            <label class="label" for="base_url">URL aplikasi <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="base_url" name="base_url" type="url" required value="<?= esc($o('base_url', $baseURL), 'attr') ?>" aria-describedby="bu-help">
            <p class="help" id="bu-help">Alamat lengkap tempat aplikasi diakses, diakhiri garis miring. Gunakan https:// bila SSL aktif.</p>
        </div>
        <div class="field">
            <label class="label" for="driver">Jenis database</label>
            <select class="input" id="driver" name="driver">
                <option value="MySQLi" <?= $o('driver', $db['DBDriver']) === 'MySQLi' ? 'selected' : '' ?>>MariaDB / MySQL</option>
                <option value="Postgre" <?= $o('driver', $db['DBDriver']) === 'Postgre' ? 'selected' : '' ?>>PostgreSQL</option>
            </select>
        </div>
        <div class="field">
            <label class="label" for="hostname">Host</label>
            <input class="input" id="hostname" name="hostname" value="<?= esc($o('hostname', $db['hostname'] ?: 'localhost'), 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="database">Nama database <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="database" name="database" required value="<?= esc($o('database', $db['database']), 'attr') ?>" placeholder="namauser_agendaguru">
        </div>
        <div class="field">
            <label class="label" for="port">Port</label>
            <input class="input" id="port" name="port" inputmode="numeric" value="<?= esc($o('port', (string) $db['port']), 'attr') ?>" aria-describedby="port-help">
            <p class="help" id="port-help">MySQL: 3306 · PostgreSQL: 5432</p>
        </div>
        <div class="field">
            <label class="label" for="username">Username database <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="username" name="username" required autocomplete="off" value="<?= esc($o('username', $db['username']), 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="password">Password database</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password">
        </div>
        <div class="field">
            <label class="label" for="prefix">Awalan tabel (opsional)</label>
            <input class="input" id="prefix" name="prefix" value="<?= esc($o('prefix', $db['DBPrefix']), 'attr') ?>" placeholder="ag_">
        </div>
    </div>
    <div class="mt-6 border-t border-line pt-5">
        <button class="btn btn-primary" <?= $ok ? '' : 'disabled aria-disabled="true"' ?>>Uji koneksi &amp; simpan</button>
        <?php if (! $ok): ?><p class="error-text mt-2">Penuhi persyaratan wajib di atas terlebih dahulu.</p><?php endif ?>
    </div>
</form>
<?= $this->endSection() ?>
