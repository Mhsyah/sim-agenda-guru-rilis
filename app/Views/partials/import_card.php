<?php
/**
 * Kartu unggah satu jenis impor (Impor Data admin & halaman kurikulum guru).
 *
 * @var string      $key       jenis impor (MasterImporter::TYPES)
 * @var array       $t         definisi jenis
 * @var int|null    $no        nomor urut langkah (null = tanpa nomor)
 * @var string      $action    URL unggah
 * @var string      $template  URL template
 * @var string|null $current   ringkasan data saat ini, mis. "24 kelas"
 * @var array       $subjects  (curriculum) pilihan mapel bawaan
 */
?>
<li id="<?= esc($key, 'attr') ?>" class="imp-card">
    <div class="imp-card-head">
        <?php if (! empty($no)): ?><span class="imp-no" aria-hidden="true"><?= (int) $no ?></span><?php endif ?>
        <div class="min-w-0 flex-1">
            <h2 class="imp-card-title"><?= esc($t['title']) ?></h2>
            <p class="imp-card-desc"><?= esc($t['desc']) ?></p>
        </div>
        <?php if (! empty($current)): ?><span class="imp-now" title="Data saat ini"><?= esc($current) ?></span><?php endif ?>
    </div>

    <details class="imp-cols">
        <summary>Kolom template (<?= count($t['columns']) ?>)</summary>
        <dl><?php foreach ($t['columns'] as $c => $d): ?><dt><code><?= esc($c) ?></code></dt><dd><?= esc($d) ?></dd><?php endforeach ?></dl>
    </details>

    <form method="post" action="<?= esc($action, 'attr') ?>" enctype="multipart/form-data" class="imp-form" data-progress="Memeriksa file <?= esc(mb_strtolower($t['title']), 'attr') ?>">
        <?= csrf_field() ?>
        <?php if ($key === 'curriculum' && ! empty($subjects)): ?>
            <div class="field">
                <label class="label" for="sub-<?= esc($key, 'attr') ?>">Mapel (bila kolom <code>mapel</code> kosong)</label>
                <select class="input" id="sub-<?= esc($key, 'attr') ?>" name="subject_id">
                    <option value="">— Ambil dari kolom mapel —</option>
                    <?php foreach ($subjects as $id => $n): ?><option value="<?= $id ?>"><?= esc($n) ?></option><?php endforeach ?>
                </select>
            </div>
        <?php endif ?>
        <label class="imp-drop">
            <?= icon('upload', 'size-5') ?>
            <span><b data-imp-fname>Pilih file CSV</b><small>Excel → Simpan sebagai CSV · maks. 2 MB</small></span>
            <input class="sr-only" type="file" name="csv" accept=".csv,text/csv" required data-imp-file>
        </label>
        <div class="imp-actions">
            <button class="btn btn-primary"><?= icon('search', 'size-4') ?> Periksa &amp; pratinjau</button>
            <a class="btn btn-ghost" href="<?= esc($template, 'attr') ?>"><?= icon('download', 'size-4') ?> Template</a>
        </div>
    </form>
</li>
