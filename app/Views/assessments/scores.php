<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $locked = $a['status'] === 'selesai'; ?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb"><a href="<?= site_url('assessments?type=' . $a['type_code']) ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Asesmen <?= esc($a['type_code']) ?></a></nav>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-semibold text-muted"><?= badge($a['type_code'] . ' · ' . $a['type_name'], 'info') ?> <?= fmt_date($a['assessment_date'], true, true) ?></p>
        <h1 class="page-title mt-2"><?= esc($a['title']) ?></h1>
        <p class="mt-2 text-muted"><?= esc($a['class_name']) ?> · <?= esc($a['subject_name']) ?> · Nilai maksimum <strong><?= fmt_num($a['max_score']) ?></strong>
            <?php if ($a['tp_code']): ?> · TP <?= esc($a['tp_code']) ?><?php endif ?></p>
    </div>
    <div class="flex gap-2 no-print">
        <a class="btn btn-ghost" href="<?= site_url('assessments/' . $a['id'] . '/edit') ?>"><?= icon('edit', 'size-4') ?> Ubah asesmen</a>
        <a class="btn btn-ghost" href="<?= site_url('reports/scores?assessment_id=' . $a['id']) ?>"><?= icon('printer', 'size-4') ?> Cetak</a>
    </div>
</div>

<?php if ($locked): ?>
    <div class="alert alert-info mb-5" role="status"><?= icon('info', 'size-5 shrink-0') ?> Asesmen berstatus selesai sehingga nilai hanya dapat dilihat. Ubah status menjadi terbuka untuk mengedit.</div>
<?php endif ?>

<?php $bands = ! empty($kktp) ? \App\Libraries\Kktp::bands($kktp['kktp_intervals']) : null; ?>
<?php if ($bands): ?>
    <section class="card mb-4 text-sm" aria-label="KKTP">
        <p class="font-semibold">KKTP · <?= esc(trim(($kktp['code'] ? $kktp['code'] . ' — ' : '') . mb_strimwidth($kktp['description'], 0, 120, '…'))) ?></p>
        <?php if (($kktp['kktp_criteria'] ?? '') !== ''): ?><p class="mt-1 text-muted"><?= esc($kktp['kktp_criteria']) ?></p><?php endif ?>
        <p class="mt-2 flex flex-wrap gap-1.5"><?php foreach ($bands as $b): ?><?= badge($b['min'] . '–' . $b['max'] . ' ' . $b['label'], $b['tone']) ?><?php endforeach ?></p>
    </section>
<?php endif ?>
<?php if (! $students): ?>
    <div class="card"><p class="text-muted">Belum ada siswa aktif di kelas ini. <a href="<?= site_url('students?class_group_id=' . $a['class_group_id']) ?>">Kelola data siswa</a>.</p></div>
<?php else: ?>
<form method="post" action="<?= site_url('assessments/' . $a['id'] . '/scores') ?>" class="card p-0 sm:p-0" novalidate>
    <?= csrf_field() ?>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <p id="score-count" class="text-sm font-semibold" aria-live="polite"><?= count($scores) ?> dari <?= count($students) ?> siswa sudah dinilai</p>
        <p class="text-xs text-muted">Kosongkan untuk “Belum diinput” (bukan nol). Centang <strong>Tidak kumpul</strong> bila siswa tidak mengumpulkan/tidak ikut — <?= esc(strtolower(\App\Controllers\Assessments::MISSING_POLICIES[$a['missing_policy'] ?? 'nol'] ?? '')) ?>. <span class="kbd">Enter</span>/<span class="kbd">↓</span> baris berikutnya, <span class="kbd">↑</span> sebelumnya.</p>
    </div>
    <div class="table-wrap">
        <table class="table" id="score-grid" data-max="<?= esc((string) (float) $a['max_score'], 'attr') ?>" <?= $bands ? 'data-kktp="' . esc(json_encode($bands), 'attr') . '"' : '' ?>>
            <caption>Nilai <?= esc($a['title']) ?></caption>
            <thead><tr>
                <th scope="col" class="num">No</th><th scope="col">NIS</th><th scope="col">Nama siswa</th>
                <th scope="col" class="num">Nilai (0–<?= fmt_num($a['max_score']) ?>)</th><th scope="col" class="text-center">Tidak kumpul</th><?php if ($bands): ?><th scope="col">Ketercapaian (KKTP)</th><?php endif ?><th scope="col">Catatan</th>
            </tr></thead>
            <tbody>
            <?php foreach ($students as $i => $st):
                $sid = $st['id'];
                $isMiss = old('missing', null, false) !== null ? isset(old('missing', null, false)[$sid]) : (int) ($scores[$sid]['is_missing'] ?? 0) === 1;
                $val = old('score.' . $sid, null, false) ?? (isset($scores[$sid]) && ! $isMiss ? fmt_num($scores[$sid]['score']) : '');
                $err = $errors['score.' . $sid] ?? null; ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td class="tabular-nums"><?= esc($st['nis'] ?? '—') ?></td>
                    <th scope="row" class="!bg-transparent !px-4 !py-3 text-left text-sm font-medium normal-case tracking-normal text-ink" id="st-<?= $sid ?>"><?= esc($st['full_name']) ?></th>
                    <td class="num">
                        <label class="sr-only" for="sc-<?= $sid ?>">Nilai <?= esc($st['full_name']) ?></label>
                        <input class="input score-input ml-auto" id="sc-<?= $sid ?>" name="score[<?= $sid ?>]" value="<?= esc((string) $val, 'attr') ?>"
                               inputmode="decimal" autocomplete="off" data-msg="msg-<?= $sid ?>" aria-describedby="msg-<?= $sid ?>" <?= $err ? 'aria-invalid="true"' : '' ?> <?= $locked ? 'readonly' : '' ?>>
                        <span class="mt-1 block text-xs <?= $err ? 'error-text' : 'text-muted' ?>" id="msg-<?= $sid ?>"><?= $err ? esc($err) : ($isMiss ? 'Tidak kumpul' : ($val === '' ? 'Belum diinput' : '')) ?></span>
                    </td>
                    <td class="text-center">
                        <label class="sc-miss" title="Tidak mengumpulkan / tidak ikut"><input type="checkbox" class="bulk-check" name="missing[<?= $sid ?>]" value="1" data-sc-miss="sc-<?= $sid ?>" <?= $isMiss ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                            <span class="sr-only">Tidak kumpul: <?= esc($st['full_name']) ?></span></label>
                    </td>
                    <?php if ($bands): $lv = (! $isMiss && $val !== '' && is_numeric(str_replace(',', '.', (string) $val))) ? \App\Libraries\Kktp::level((float) str_replace(',', '.', (string) $val) / max(0.01, (float) $a['max_score']) * 100, $kktp['kktp_intervals']) : null; ?>
                        <td data-kktp-cell="sc-<?= $sid ?>"><?= $lv ? badge($lv['label'], $lv['tone']) : '<span class="text-xs text-muted">—</span>' ?></td>
                    <?php endif ?>
                    <td>
                        <label class="sr-only" for="nt-<?= $sid ?>">Catatan untuk <?= esc($st['full_name']) ?></label>
                        <input class="input min-w-40" id="nt-<?= $sid ?>" name="notes[<?= $sid ?>]" value="<?= esc((string) (old('notes.' . $sid, null, false) ?? ($scores[$sid]['notes'] ?? '')), 'attr') ?>" maxlength="500" <?= $locked ? 'readonly' : '' ?>>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php if (! $locked): ?>
        <div class="sticky bottom-0 flex flex-wrap gap-2 border-t border-line glass px-5 py-4 no-print">
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan nilai</button>
            <a class="btn btn-ghost" href="<?= site_url('recap?' . http_build_query(['class_group_id' => $a['class_group_id'], 'subject_id' => $a['subject_id']])) ?>">Lihat rekap</a>
        </div>
    <?php endif ?>
</form>
<?php endif ?>
<?= $this->endSection() ?>
