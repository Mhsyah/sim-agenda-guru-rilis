<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<nav class="mb-3 text-sm" aria-label="Breadcrumb"><a href="<?= site_url('students') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Data Siswa</a></nav>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>
<?php $r = ['capacity' => ($result['capacity'] ?? null) ?? \App\Libraries\ClassCapacity::notices()]; ?>
<?php foreach (($r['capacity'] ?? []) as [$tone, $msg, $href, $lbl]): ?>
    <p class="alert alert-<?= $tone ?> mb-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
        <span class="flex-1"><?= esc($msg) ?></span><?php if ($href): ?><a class="btn btn-ghost btn-sm shrink-0" href="<?= esc($href, 'attr') ?>"><?= esc($lbl) ?></a><?php endif ?></p>
<?php endforeach ?>

<div class="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
    <form method="post" action="<?= site_url('imports/students') ?>" enctype="multipart/form-data" data-progress="Memeriksa file siswa" class="card card-organic">
        <?= csrf_field() ?>
        <input type="hidden" name="from" value="students">
        <div class="grid gap-5">
            <div class="field">
                <label class="label" for="csv">File CSV <span class="req" aria-hidden="true">*</span></label>
                <input class="input py-2" type="file" id="csv" name="csv" accept=".csv,text/csv" required aria-describedby="csv-help">
                <p class="help" id="csv-help">Maksimal 2 MB. Pemisah koma (,) atau titik koma (;). Encoding UTF-8.</p>
            </div>
            <div class="field">
                <label class="label" for="class_group_id">Tempatkan ke kelas (bila kolom <code>class</code> kosong)</label>
                <select class="input" id="class_group_id" name="class_group_id">
                    <option value="">— Jangan tempatkan —</option>
                    <?php foreach ($classes as $id => $n): ?><option value="<?= $id ?>"><?= esc($n) ?></option><?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap gap-2">
            <button class="btn btn-primary"><?= icon('search', 'size-4') ?> Periksa &amp; pratinjau</button>
            <a class="btn btn-ghost" href="<?= site_url('students/template') ?>"><?= icon('download', 'size-4') ?> Unduh template</a>
        </div>
    </form>

    <aside class="card text-sm" aria-labelledby="fmt">
        <h2 id="fmt" class="font-display text-lg font-bold">Format kolom</h2>
        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
            <dt><code>full_name</code></dt><dd>Wajib. Alias: <code>nama</code></dd>
            <dt><code>nis</code></dt><dd>Opsional, unik. Siswa dengan NIS sama diperbarui.</dd>
            <dt><code>nisn</code></dt><dd>Opsional, angka, unik.</dd>
            <dt><code>gender</code></dt><dd>L atau P</dd>
            <dt><code>birth_date</code></dt><dd>YYYY-MM-DD atau DD/MM/YYYY</dd>
            <dt><code>class</code></dt><dd>Nama kelas di tahun ajaran aktif, mis. <em>VII A</em></dd>
        </dl>
        <p class="mt-4 text-muted">Kumpulkan hanya data yang diperlukan untuk pembelajaran.</p>
    </aside>
</div>

<?php if ($result): ?>
    <section class="card mt-6" aria-labelledby="res" role="status">
        <h2 id="res" class="font-display text-xl font-bold">Hasil impor</h2>
        <ul class="mt-3 flex flex-wrap gap-2">
            <li><?= badge($result['total'] . ' baris diproses') ?></li>
            <li><?= badge($result['ok'] . ' berhasil (' . $result['created'] . ' baru, ' . $result['updated'] . ' diperbarui)', 'success') ?></li>
            <li><?= badge(count($result['errors']) . ' gagal', count($result['errors']) ? 'danger' : 'neutral') ?></li>
        </ul>
        <?php if ($result['errors']): ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <caption>Baris yang gagal diimpor</caption>
                    <thead><tr><th scope="col" class="num">Baris</th><th scope="col">Alasan</th></tr></thead>
                    <tbody>
                    <?php foreach ($result['errors'] as $e): ?>
                        <tr><td class="num"><?= (int) $e['line'] ?></td><td><?= esc($e['reason']) ?></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
