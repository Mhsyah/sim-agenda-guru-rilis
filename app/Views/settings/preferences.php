<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>
<div class="grid max-w-4xl gap-5 md:grid-cols-2">
    <section class="card card-organic" aria-labelledby="t-title">
        <h2 id="t-title" class="font-display text-xl font-bold">Tema tampilan</h2>
        <p class="mt-1 text-sm text-muted">Pilihan disimpan ke akun Anda sehingga berlaku di perangkat lain. “Ikuti sistem” menyesuaikan pengaturan terang/gelap sistem operasi.</p>
        <div class="mt-4 grid gap-2" role="group" aria-label="Pilih tema">
            <button type="button" class="btn btn-ghost justify-start" data-theme-set="light" aria-pressed="false"><?= icon('sun', 'size-4') ?> Terang</button>
            <button type="button" class="btn btn-ghost justify-start" data-theme-set="dark" aria-pressed="false"><?= icon('moon', 'size-4') ?> Gelap</button>
            <button type="button" class="btn btn-ghost justify-start" data-theme-set="system" aria-pressed="false"><?= icon('monitor', 'size-4') ?> Ikuti sistem</button>
        </div>
        <p class="mt-3 text-sm">Aktif: <strong data-theme-label>—</strong></p>
        <noscript>
            <form method="post" action="<?= site_url('preferences/theme') ?>" class="mt-3 flex gap-2">
                <?= csrf_field() ?>
                <select class="input" name="theme" aria-label="Tema"><option value="system">Ikuti sistem</option><option value="light">Terang</option><option value="dark">Gelap</option></select>
                <button class="btn btn-primary">Simpan</button>
            </form>
        </noscript>
    </section>
    <section class="card" aria-labelledby="m-title">
        <h2 id="m-title" class="font-display text-xl font-bold">Gerak &amp; animasi</h2>
        <p class="mt-1 text-sm text-muted">Animasi otomatis berkurang bila sistem Anda mengaktifkan “kurangi gerakan”. Anda juga dapat menonaktifkannya di perangkat ini.</p>
        <label class="check mt-4"><input type="checkbox" data-motion-toggle> <span class="text-sm font-medium">Kurangi animasi di perangkat ini</span></label>
        <h3 class="mt-6 font-display text-lg font-bold">Pintasan keyboard</h3>
        <ul class="mt-2 grid gap-1.5 text-sm">
            <li><span class="kbd">Tab</span> berpindah antar elemen; fokus selalu terlihat.</li>
            <li><span class="kbd">/</span> fokus ke kotak pencarian.</li>
            <li><span class="kbd">Enter</span> / <span class="kbd">↓</span> / <span class="kbd">↑</span> berpindah baris saat input nilai.</li>
            <li><span class="kbd">Esc</span> menutup dialog dan menu pintasan.</li>
        </ul>
    </section>
</div>
<?= $this->endSection() ?>
