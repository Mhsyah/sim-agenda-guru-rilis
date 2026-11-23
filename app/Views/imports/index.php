<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array      $types
 * @var array|null $result
 * @var array      $counts  ringkasan data saat ini per jenis
 */
$order = ['years', 'subjects', 'classes', 'teachers', 'students', 'curriculum'];
?>
<div class="mb-5">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-1.5 max-w-3xl text-sm text-muted">Isi data induk sekaligus dari file CSV. Tahun pelajaran aktif: <strong class="text-ink"><?= esc($period['year']['name'] ?? 'belum ada') ?></strong>.</p>
</div>

<ol class="imp-steps mb-5" aria-label="Alur impor">
    <li><span>1</span><div><b>Unduh template</b><small>Isi di Excel, simpan sebagai CSV.</small></div></li>
    <li><span>2</span><div><b>Periksa &amp; pratinjau</b><small>Belum ada yang disimpan. Duplikat &amp; galat ditandai per baris.</small></div></li>
    <li><span>3</span><div><b>Konfirmasi impor</b><small>Hanya baris valid yang disimpan; data lama diperbarui.</small></div></li>
</ol>

<?php if ($result): ?><?= view('partials/import_result', ['result' => $result, 'label' => $types[$resultType]['title'] ?? ''], ['saveData' => false]) ?><?php endif ?>

<p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Urutan yang disarankan</p>
<ol class="imp-grid">
    <?php foreach ($order as $i => $key): ?>
        <?= view('partials/import_card', [
            'key' => $key, 't' => $types[$key], 'no' => $i + 1,
            'action' => site_url('imports/' . $key), 'template' => site_url('imports/' . $key . '/template'),
            'current' => $counts[$key] ?? null, 'subjects' => $key === 'curriculum' ? \App\Libraries\Lookup::subjects(false) : [],
        ], ['saveData' => false]) ?>
    <?php endforeach ?>
</ol>

<section class="card mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Sinkron Dapodik">
    <div>
        <h2 class="text-sm font-bold">Sinkron dengan Dapodik</h2>
        <p class="text-xs text-muted">Ambil GTK, peserta didik, dan rombel langsung dari Web Service Dapodik atau berkas ekspornya, dengan pratinjau perbedaan.</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('dapodik') ?>"><?= icon('refresh', 'size-4') ?> Sinkron Dapodik</a>
</section>

<section class="card mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Dokumen kurikulum">
    <div>
        <h2 class="text-sm font-bold">Dokumen CP, TP, ATP &amp; KKTP (PDF/DOCX)</h2>
        <p class="text-xs text-muted">Arsip dokumen asli per mapel &amp; fase; dapat diunduh guru pengampu.</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('curriculum/docs') ?>"><?= icon('file', 'size-4') ?> Kelola dokumen</a>
</section>
<?= $this->endSection() ?>
