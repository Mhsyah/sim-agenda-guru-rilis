<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= site_url($base) ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> <?= esc($section) ?></a>
</nav>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>

<div class="grid max-w-6xl gap-6 <?= $row ? 'lg:grid-cols-[minmax(0,1fr)_20rem]' : '' ?>">
    <form method="post" action="<?= $action ?>" class="card card-organic max-w-3xl" novalidate>
        <?= csrf_field() ?>
        <p class="mb-5 text-sm text-muted">Isian bertanda <span class="req">*</span> wajib diisi.</p>
        <div class="grid gap-5 sm:grid-cols-2">
            <?php foreach ($fields as $name => $f): ?>
                <?= view('partials/field', ['name' => $name, 'f' => $f, 'errors' => $errors]) ?>
            <?php endforeach ?>
        </div>
        <div class="mt-6">
            <?= view('partials/teach_picker', $extra + ['canCreate' => true,
                'help' => 'Untuk guru. Centang mapel, lalu pilih kelas yang diajar pada T.P. ' . ($period['year']['name'] ?? '') . '. Mapel belum ada? Tambahkan di bawah.'], ['saveData' => false]) ?>
        </div>
        <div class="mt-7 flex flex-wrap gap-2 border-t border-line pt-5">
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan</button>
            <a class="btn btn-ghost" href="<?= site_url($base) ?>">Batal</a>
        </div>
    </form>
    <?php if ($row): ?>
        <aside class="self-start" aria-label="Status akun">
            <?= view('partials/account_status', ['acct' => $row], ['saveData' => false]) ?>
        </aside>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
