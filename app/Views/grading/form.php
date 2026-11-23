<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\GradeCalculator;

$v = static fn (string $k, $default = '') => old($k, $row[$k] ?? $default, false);
$fieldErr = static fn (string $k) => isset($errors[$k]) ? '<p class="error-text" id="e-' . $k . '">' . esc($errors[$k]) . '</p>' : '';
?>
<nav class="mb-3 text-sm" aria-label="Breadcrumb"><a href="<?= site_url('grading') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Pengaturan Bobot</a></nav>
<h1 class="page-title mb-6"><?= esc($title) ?></h1>

<form method="post" action="<?= $row ? site_url('grading/' . $row['id']) : site_url('grading') ?>" class="card card-organic max-w-3xl" novalidate>
    <?= csrf_field() ?>
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field sm:col-span-2">
            <label class="label" for="name">Nama pengaturan <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="name" name="name" required value="<?= esc((string) $v('name', 'Bobot penilaian'), 'attr') ?>" <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="e-name"' : '' ?>>
            <?= $fieldErr('name') ?>
        </div>
        <div class="field">
            <label class="label" for="f-subject_id">Mata pelajaran <span class="req" aria-hidden="true">*</span></label>
            <select class="input" id="f-subject_id" name="subject_id" required>
                <?php foreach ($subjects as $id => $n): ?><option value="<?= $id ?>" <?= (string) $id === (string) $v('subject_id') ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="class_group_id">Cakupan kelas</label>
            <select class="input" id="class_group_id" name="class_group_id" aria-describedby="cg-help">
                <option value="">Semua kelas</option>
                <?php foreach ($classes as $id => $n): ?><option value="<?= $id ?>" <?= (string) $id === (string) $v('class_group_id') ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
            </select>
            <p class="help" id="cg-help">Pengaturan khusus kelas diprioritaskan daripada “Semua kelas”.</p>
        </div>
    </div>

    <fieldset class="mt-7" aria-describedby="weight-help">
        <legend class="font-display text-lg font-bold">Bobot kategori</legend>
        <p class="help mt-1" id="weight-help">Hanya kategori yang dicentang yang dihitung. Contoh (tidak wajib): Tugas 20%, UH 30%, ATS 20%, AAS 30%.</p>
        <div class="mt-3 grid gap-3">
            <?php foreach ($types as $t): ?>
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-line p-3">
                    <label class="check min-w-56 flex-1" for="en-<?= $t['id'] ?>">
                        <input type="checkbox" id="en-<?= $t['id'] ?>" name="enabled[<?= $t['id'] ?>]" value="1" data-weight-enabled <?= $t['enabled'] ? 'checked' : '' ?>>
                        <span><strong><?= esc($t['code']) ?></strong> — <?= esc($t['name']) ?></span>
                    </label>
                    <label class="sr-only" for="w-<?= $t['id'] ?>">Bobot <?= esc($t['code']) ?> dalam persen</label>
                    <div class="flex items-center gap-1.5">
                        <input class="input w-24 text-right tabular-nums" id="w-<?= $t['id'] ?>" name="weight[<?= $t['id'] ?>]" inputmode="decimal" value="<?= esc((string) $t['weight'], 'attr') ?>" data-weight="<?= $t['id'] ?>">
                        <span aria-hidden="true">%</span>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <p class="mt-3 flex flex-wrap items-center gap-2 text-sm" aria-live="polite">
            Total: <strong id="weight-sum" class="font-display text-xl">—</strong>
            <span id="weight-status" class="badge">Total harus tepat 100%.</span>
        </p>
    </fieldset>

    <div class="mt-7 grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="aggregation_method">Metode jika ada beberapa asesmen per kategori</label>
            <select class="input" id="aggregation_method" name="aggregation_method">
                <?php foreach (GradeCalculator::METHODS as $k => $l): ?><option value="<?= $k ?>" <?= $k === $v('aggregation_method', 'mean') ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="rounding_rule">Pembulatan tampilan</label>
            <select class="input" id="rounding_rule" name="rounding_rule">
                <?php foreach (GradeCalculator::ROUNDING as $k => $l): ?><option value="<?= $k ?>" <?= (string) $k === (string) $v('rounding_rule', '2') ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="sm:col-span-2">
            <input type="hidden" name="is_active" value="0">
            <label class="check" for="is_active"><input type="checkbox" id="is_active" name="is_active" value="1" <?= (string) $v('is_active', '1') === '1' ? 'checked' : '' ?>> <span class="text-sm font-medium">Aktif (hanya satu pengaturan aktif per cakupan)</span></label>
        </div>
    </div>

    <?php if ($hasScores || $needConfirm): ?>
        <div class="alert alert-warning mt-6" role="<?= $needConfirm ? 'alert' : 'note' ?>">
            <?= icon('alert', 'size-5 shrink-0') ?>
            <div>
                <p>Sudah ada nilai pada cakupan ini. Mengubah bobot akan mengubah hasil rekap, dan perubahan dicatat di log audit.</p>
                <label class="check mt-1"><input type="checkbox" name="confirm_change" value="1"> <span class="font-semibold">Saya memahami dan ingin menyimpan perubahan bobot.</span></label>
            </div>
        </div>
    <?php endif ?>

    <div class="mt-7 flex flex-wrap gap-2 border-t border-line pt-5">
        <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan</button>
        <a class="btn btn-ghost" href="<?= site_url('grading') ?>">Batal</a>
    </div>
</form>
<?= $this->endSection() ?>
