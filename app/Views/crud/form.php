<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= site_url($base) ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> <?= esc($section) ?></a>
</nav>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>

<form method="post" action="<?= $action ?>" class="card card-organic max-w-3xl" novalidate>
    <?= csrf_field() ?>
    <p class="mb-5 text-sm text-muted">Isian bertanda <span class="req">*</span> wajib diisi.</p>
    <div class="grid gap-5 sm:grid-cols-2">
        <?php foreach ($fields as $name => $f): ?>
            <?= view('partials/field', ['name' => $name, 'f' => $f, 'errors' => $errors]) ?>
        <?php endforeach ?>
    </div>
    <?php if (! empty($extra['view'])): ?>
        <?= view($extra['view'], $extra) ?>
    <?php endif ?>
    <div class="mt-7 flex flex-wrap gap-2 border-t border-line pt-5">
        <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan</button>
        <a class="btn btn-ghost" href="<?= site_url($base) ?>">Batal</a>
    </div>
</form>
<?= $this->endSection() ?>
