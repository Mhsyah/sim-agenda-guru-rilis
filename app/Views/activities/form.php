<?= $this->extend(\App\Controllers\Activities::isEmbed() ? 'layouts/embed' : 'layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Activities;
use App\Libraries\MaterialFile;

$embed = Activities::isEmbed();
$fld   = static fn (string $k, array $over = []) => view('partials/field', ['name' => $k, 'f' => $over + $fields[$k], 'errors' => $errors]);
$type  = (string) ($fields['activity_type']['value'] ?? '');
?>
<?php if (! $embed): ?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= site_url('activities') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Kegiatan di Luar KBM</a>
</nav>
<?php endif ?>
<div class="mb-5">
    <h1 class="page-title"><?= $row ? 'Ubah kegiatan' : 'Catat kegiatan di luar KBM' ?></h1>
    <p class="mt-1 text-sm text-muted">Rapat, upacara, piket, pendampingan, pelatihan, kepanitiaan, dan kegiatan lain. Bertanda <span class="req">*</span> wajib.</p>
</div>

<form method="post" action="<?= $action ?>" class="mx-auto grid max-w-3xl gap-4 pb-2" novalidate id="activity-form" enctype="multipart/form-data">
    <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif ?>
    <?= csrf_field() ?>

    <section class="card p-4 sm:p-6" aria-label="Data kegiatan">
        <fieldset class="mb-5 min-w-0">
            <legend class="label mb-2">Jenis kegiatan <span class="req" aria-hidden="true">*</span></legend>
            <div class="act-types">
                <?php foreach (Activities::GROUPS as $group => $items): ?>
                    <div class="act-type-group">
                        <p class="act-type-head"><?= esc($group) ?></p>
                        <div class="chips">
                            <?php foreach ($items as $val => $label): ?>
                                <label><input type="radio" name="activity_type" value="<?= esc($val, 'attr') ?>" <?= $type === $val ? 'checked' : '' ?> required><span><?= esc($label) ?></span></label>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <?php if (isset($errors['activity_type'])): ?><p class="error-text mt-1"><?= esc($errors['activity_type']) ?></p><?php endif ?>
        </fieldset>
        <div class="grid gap-5 sm:grid-cols-2">
            <?= $fld('title') ?>
            <?= $fld('activity_date') ?>
            <div class="grid grid-cols-2 gap-3">
                <?= $fld('start_time') ?>
                <?= $fld('end_time') ?>
            </div>
            <?= $fld('location') ?>
            <?= $fld('description', ['attrs' => ['rows' => 4, 'placeholder' => 'Ringkasan jalannya kegiatan, hasil/keputusan, atau tindak lanjut.']]) ?>
        </div>

        <?php
        $matFile = $row['material_file'] ?? null;
        $matUrl  = $row && MaterialFile::path($matFile) ? site_url('activities/' . $row['id'] . '/material') : null;
        $matExt  = $matFile ? MaterialFile::ext($matFile) : '';
        ?>
        <div class="mt-5" data-material>
            <p class="label mb-1.5">Lampiran bukti <span class="text-xs font-normal text-muted">(opsional — foto kegiatan, undangan, notula, sertifikat)</span></p>
            <?php if ($matUrl): ?>
                <div class="mat-card" data-mat-current>
                    <?php if ($matExt === 'webp'): ?>
                        <img src="<?= $matUrl ?>" alt="" class="mat-thumb object-cover" loading="lazy">
                    <?php else: ?>
                        <span class="mat-thumb mat-ext-<?= esc($matExt, 'attr') ?>"><?= strtoupper(esc($matExt)) ?></span>
                    <?php endif ?>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold" title="<?= esc($row['material_name'], 'attr') ?>"><?= esc($row['material_name']) ?></p>
                        <p class="text-xs text-muted"><?= MaterialFile::human((int) $row['material_size']) ?> · tersimpan</p>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= $matUrl ?>" target="_blank" rel="noopener"><?= icon('eye', 'size-4') ?> Lihat</a>
                    <label class="check basis-full text-sm text-muted"><input type="checkbox" name="material_remove" value="1"> Hapus lampiran ini saat disimpan</label>
                </div>
            <?php endif ?>
            <label class="mat-drop <?= $matUrl ? 'mt-2' : '' ?>" for="f-material" data-mat-drop>
                <span class="opt-ico"><?= icon('paperclip', 'size-4') ?></span>
                <span class="min-w-0">
                    <strong class="block text-sm"><?= $matUrl ? 'Ganti lampiran' : 'Pilih atau seret file bukti' ?></strong>
                    <small class="block text-xs text-muted">JPG, PNG, PDF, DOCX, XLSX, PPTX · maks. 1 MB (gambar → WebP; file lebih besar dikompres otomatis)</small>
                </span>
            </label>
            <input id="f-material" type="file" name="material" accept="<?= MaterialFile::ACCEPT ?>" class="sr-only" data-mat-input>
            <div class="mat-card mt-2" data-mat-new hidden>
                <span class="mat-thumb" data-mat-thumb></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold" data-mat-name></p>
                    <p class="text-xs" data-mat-info></p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-mat-clear aria-label="Batalkan file"><?= icon('x', 'size-4') ?></button>
            </div>
            <?php if (isset($errors['material'])): ?><p class="error-text mt-1"><?= esc($errors['material']) ?></p><?php endif ?>
        </div>
    </section>

    <div class="action-bar no-print" role="region" aria-label="Simpan kegiatan">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-end gap-3">
            <?php if ($embed): ?>
                <button type="button" class="btn btn-ghost" data-embed-cancel>Kembali</button>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= site_url('activities') ?>">Batal</a>
            <?php endif ?>
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan kegiatan</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
