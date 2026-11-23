<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<nav class="mb-3 text-sm" aria-label="Breadcrumb"><a href="<?= site_url('imports') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Impor Data</a></nav>
<div class="mb-5">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-1.5 max-w-3xl text-sm text-muted">Dokumen yang diunggah admin dibagikan ke semua guru pengampu mapel tersebut (T.P. <?= esc($period['year']['name'] ?? '') ?>).
        Untuk memasukkan data CP/TP/ATP/KKTP ke aplikasi, gunakan kartu <a href="<?= site_url('imports#curriculum') ?>">CP, TP, ATP &amp; KKTP</a> di Impor Data.</p>
</div>
<section class="card">
    <?= view('partials/curriculum_docs', ['docs' => $docs, 'subjects' => $subjects, 'isAdmin' => true, 'authUser' => $authUser], ['saveData' => false]) ?>
</section>
<?= $this->endSection() ?>
