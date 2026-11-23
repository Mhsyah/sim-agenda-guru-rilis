<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php \App\Libraries\KopSettings::useRequest(); ?>
<?php
$val = static fn ($k) => (string) ($f[$k] ?? '');
$q   = http_build_query(array_filter($f, static fn ($v) => $v !== '' && is_string($v)) + ['format' => 'csv']);
?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb"><a href="<?= site_url('reports') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Laporan</a></nav>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted"><?= esc($subtitle ?? $meta[1]) ?></p>
    </div>
    <div class="flex gap-2 no-print">
        <a class="btn btn-ghost" href="<?= current_url() . '?' . $q ?>"><?= icon('download', 'size-4') ?> Ekspor CSV</a>
        <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?> Cetak</button>
    </div>
</div>

<form method="get" class="filters card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4" aria-label="Filter laporan">
    <?php foreach ($filters as $k): ?>
        <?php if (in_array($k, ['class_group_id', 'subject_id', 'semester_id', 'assessment_id', 'event_type'], true)):
            $opts  = ['class_group_id' => $classes, 'subject_id' => $subjects, 'semester_id' => $semesters, 'assessment_id' => $assessmentOptions ?? [], 'event_type' => event_types()][$k];
            $label = ['class_group_id' => 'Kelas', 'subject_id' => 'Mapel', 'semester_id' => 'Semester', 'assessment_id' => 'Asesmen', 'event_type' => 'Jenis'][$k]; ?>
            <div class="field <?= $k === 'assessment_id' ? 'min-w-72 flex-1' : 'min-w-40' ?>">
                <label class="label" for="rf-<?= $k ?>"><?= $label ?></label>
                <select class="input" id="rf-<?= $k ?>" name="<?= $k ?>">
                    <?php if ($k !== 'assessment_id' && ! ($k === 'class_group_id' && ! empty($requireClass))): ?><option value="">Semua</option><?php endif ?>
                    <?php foreach ($opts as $v => $l): ?>
                        <option value="<?= esc((string) $v, 'attr') ?>" <?= (string) $v === $val($k) || ($k === 'semester_id' && $val($k) === '' && (int) $v === (int) ($period['semester']['id'] ?? 0) && $type !== 'agenda') ? 'selected' : '' ?>><?= esc($l) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php else: ?>
            <div class="field min-w-40">
                <label class="label" for="rf-<?= $k ?>"><?= $k === 'from' ? 'Dari tanggal' : 'Sampai tanggal' ?></label>
                <input class="input" type="date" id="rf-<?= $k ?>" name="<?= $k ?>" value="<?= esc($val($k), 'attr') ?>">
            </div>
        <?php endif ?>
    <?php endforeach ?>
    <button class="btn btn-ghost"><?= icon('search', 'size-4') ?> Terapkan</button>
    <?= view('partials/print_options', ['profile' => $profile], ['saveData' => false]) ?>
</form>

<p class="print-only mb-2 font-bold"><?= esc($title) ?><?= isset($subtitle) ? ' — ' . esc($subtitle) : '' ?></p>
<section class="card p-0 sm:p-0">
    <?php if (! $body): ?>
        <p class="p-6 text-muted"><?= esc($empty ?? 'Tidak ada data untuk filter ini.') ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption><?= esc($title) ?></caption>
                <thead><tr><?php foreach ($head as $h): ?><th scope="col"><?= esc($h) ?></th><?php endforeach ?></tr></thead>
                <tbody>
                <?php foreach ($body as $row): ?>
                    <tr><?php foreach ($row as $cell): $txt = (string) ($cell ?? ''); ?><td class="whitespace-pre-line <?= mb_strlen($txt) > 40 ? 'cell-long' : '' ?>"><?= esc($txt) ?></td><?php endforeach ?></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <p class="px-5 py-4 text-sm text-muted"><?= count($body) ?> baris</p>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
