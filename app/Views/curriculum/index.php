<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Guru: unggah CP, TP, ATP & KKTP untuk mapel yang diajarkan.
 *
 * @var array      $meta
 * @var array      $subjects
 * @var array|null $result
 * @var array      $docs
 * @var array      $summary
 */
?>
<div class="mb-5">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-1.5 max-w-3xl text-sm text-muted">Masukkan Capaian Pembelajaran (CP), Tujuan Pembelajaran (TP), Alur Tujuan Pembelajaran (ATP), dan
        Kriteria Ketercapaian TP (KKTP) sesuai Kurikulum Merdeka untuk mapel yang Anda ajarkan pada T.P. <strong class="text-ink"><?= esc($period['year']['name'] ?? '') ?></strong>.</p>
</div>

<?php if ($result): ?><?= view('partials/import_result', ['result' => $result, 'label' => $meta['title']], ['saveData' => false]) ?><?php endif ?>

<?php if ($summary): ?>
    <ul class="kur-sum mb-5" aria-label="Ringkasan kurikulum per mapel">
        <?php foreach ($summary as $s): ?>
            <li>
                <b class="truncate"><?= esc($s['name']) ?></b>
                <span><a href="<?= site_url('outcomes?subject_id=' . $s['id']) ?>"><?= $s['cp'] ?> CP</a> · <a href="<?= site_url('objectives?subject_id=' . $s['id']) ?>"><?= $s['tp'] ?> TP</a></span>
                <span class="text-xs text-muted">ATP <?= $s['atp'] ?>/<?= $s['tp'] ?> · KKTP <?= $s['kktp'] ?>/<?= $s['tp'] ?></span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<div class="grid gap-5 lg:grid-cols-2">
    <section aria-labelledby="kur-data">
        <h2 id="kur-data" class="mb-2 text-sm font-bold">A. Impor data (Excel/CSV)</h2>
        <ol class="imp-grid is-single">
            <?= view('partials/import_card', [
                'key' => 'curriculum', 't' => $meta, 'no' => null, 'action' => site_url('curriculum/import'), 'template' => site_url('curriculum/template'),
                'current' => null, 'subjects' => $subjects,
            ], ['saveData' => false]) ?>
        </ol>
        <p class="mt-2 text-xs text-muted">Setelah diperiksa, Anda melihat pratinjau per baris (baru, sudah ada → diperbarui, duplikat, galat) sebelum menyimpan.
            KKTP interval kosong memakai bawaan: <?= esc(\App\Libraries\Kktp::describe(null)) ?>.</p>
    </section>

    <section id="dokumen" class="card" aria-labelledby="kur-doc">
        <h2 id="kur-doc" class="mb-1 text-sm font-bold">B. Arsip dokumen (PDF/DOCX)</h2>
        <p class="mb-3 text-xs text-muted">Simpan dokumen asli CP/TP/ATP/KKTP. Dokumen dari sekolah (admin) juga tampil di sini.</p>
        <?= view('partials/curriculum_docs', ['docs' => $docs, 'subjects' => $subjects, 'isAdmin' => false, 'authUser' => $authUser], ['saveData' => false]) ?>
    </section>
</div>
<?= $this->endSection() ?>
