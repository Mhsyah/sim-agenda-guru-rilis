<?= $this->extend(\App\Controllers\Agendas::isEmbed() ? 'layouts/embed' : 'layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Agendas;

/** @var array $fields */
$v   = static fn (string $k) => (string) ($fields[$k]['value'] ?? '');
$fld = static fn (string $k, array $over = []) => view('partials/field', ['name' => $k, 'f' => $over + $fields[$k], 'errors' => $errors]);
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="error-text mt-1" id="f-' . $k . '-err">' . esc($errors[$k]) . '</p>' : '';

/** Grup tombol pilihan (radio) yang ringkas. */
$chips = static function (string $name, array $options, string $value, string $label, bool $required = false) use ($err) {
    $id = 'g-' . $name;
    ob_start(); ?>
    <fieldset class="min-w-0">
        <legend class="label mb-1.5"><?= esc($label) ?><?php if ($required): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></legend>
        <div class="chips" id="<?= $id ?>">
            <?php foreach ($options as $val => $text): ?>
                <label class="chip-<?= esc((string) ($val === '' ? 'none' : $val), 'attr') ?>"><input type="radio" name="<?= esc($name, 'attr') ?>" value="<?= esc((string) $val, 'attr') ?>" <?= (string) $val === $value ? 'checked' : '' ?>><span><?= esc($text) ?></span></label>
            <?php endforeach ?>
        </div>
        <?= $err($name) ?>
    </fieldset>
    <?php return ob_get_clean();
};

$status     = $v('status') ?: 'selesai';
$completion = $v('completion') ?: 'selesai';
$needKet    = in_array($status, ['terlambat', 'batal'], true) || $v('remarks') !== '' || isset($errors['remarks']);
$needReason = $completion === 'belum' || isset($errors['incomplete_reason']);
$notesOpen  = $v('activities') . $v('reflection') . $v('follow_up') !== '' || isset($errors['activities']) || isset($errors['reflection']) || isset($errors['follow_up']);
$classes    = $fields['class_group_id']['options'];
$subjects   = $fields['subject_id']['options'];
?>
<?php if (! Agendas::isEmbed()): ?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= site_url('agendas') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Agenda & Jurnal</a>
</nav>
<?php endif ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title"><?= $row ? 'Ubah agenda' : 'Tulis agenda' ?></h1>
        <p class="mt-1 text-sm text-muted">Isi yang penting dulu, catatan pembelajaran bisa dilengkapi nanti. Bertanda <span class="req">*</span> wajib.</p>
    </div>
</div>

<?php $embed = Agendas::isEmbed(); ?>
<form method="post" action="<?= $action ?>" class="agenda-form mx-auto grid max-w-4xl gap-4 pb-2" novalidate id="agenda-form" enctype="multipart/form-data">
    <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif ?>
    <?= csrf_field() ?>

    <?php if (! empty($extra['slots']) && ! $row): ?>
        <section class="card p-4 sm:p-5" aria-labelledby="slot-title">
            <h2 id="slot-title" class="mb-2 flex items-center gap-2 text-sm font-bold"><?= icon('clock', 'size-4') ?> Jadwal Anda hari ini — pilih untuk mengisi otomatis</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($extra['slots'] as $s): ?>
                    <button type="button" class="slot-chip" data-slot
                            data-class="<?= (int) $s['class_group_id'] ?>" data-subject="<?= (int) $s['subject_id'] ?>" data-hours="<?= esc((string) ($s['lesson_hours'] ?? ''), 'attr') ?>"
                            aria-pressed="<?= (string) $s['class_group_id'] === $v('class_group_id') && (string) $s['subject_id'] === $v('subject_id') ? 'true' : 'false' ?>">
                        <span class="font-mono text-xs tabular-nums"><?= fmt_time($s['start_time']) ?></span>
                        <strong><?= esc($s['class_name']) ?></strong>
                        <span class="text-muted"><?= esc($s['subject_name']) ?><?= $s['lesson_hours'] ? ' · Jam ' . esc($s['lesson_hours']) : '' ?></span>
                    </button>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <!-- 1. Pertemuan -->
    <section class="card card-organic p-4 sm:p-6" aria-labelledby="sec-1">
        <h2 id="sec-1" class="step-title"><span>1</span> Pertemuan</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_0.6fr_1fr_1.3fr]">
            <?= $fld('agenda_date', ['label' => 'Tanggal']) ?>
            <?= $fld('lesson_hours', ['help' => null]) ?>
            <?= $fld('class_group_id') ?>
            <?= $fld('subject_id') ?>
        </div>
        <div class="mt-4">
            <?= $chips('status', ['selesai' => 'Terlaksana', 'terlambat' => 'Guru terlambat', 'batal' => 'Guru berhalangan', 'draf' => 'Draf'], $status, 'Status pertemuan', true) ?>
        </div>
        <div class="mt-3" data-show-when="status:terlambat,batal" <?= $needKet ? '' : 'hidden' ?>>
            <?= $fld('remarks', ['label' => 'Keterangan (KET) — jelaskan sebab terlambat/berhalangan', 'help' => null]) ?>
        </div>
    </section>

    <!-- 2. Materi -->
    <section class="card p-4 sm:p-6" aria-labelledby="sec-2">
        <h2 id="sec-2" class="step-title"><span>2</span> Materi</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_14rem]">
            <?= $fld('topic', ['label' => 'Pokok bahasan / materi', 'placeholder' => 'Mis. Operasi hitung bilangan bulat']) ?>
            <?= $fld('learning_objective_id', ['label' => 'TP / No. KI/KD', 'span' => null, 'empty' => '— Tanpa TP (opsional) —']) ?>
            <fieldset class="min-w-0">
                <legend class="label mb-1.5">Pertemuan</legend>
                <div class="flex items-center gap-2">
                    <label class="sr-only" for="f-meeting_no">Pertemuan ke</label>
                    <input class="input h-11 min-h-0 w-full text-center tabular-nums" type="number" min="1" max="999" step="1" id="f-meeting_no" name="meeting_no"
                           value="<?= esc($v('meeting_no'), 'attr') ?>" placeholder="ke-" <?= isset($errors['meeting_no']) ? 'aria-invalid="true"' : '' ?>>
                    <span class="shrink-0 text-sm text-muted">dari</span>
                    <label class="sr-only" for="f-meeting_total">Banyak pertemuan</label>
                    <input class="input h-11 min-h-0 w-full text-center tabular-nums" type="number" min="1" max="999" step="1" id="f-meeting_total" name="meeting_total"
                           value="<?= esc($v('meeting_total'), 'attr') ?>" placeholder="total" <?= isset($errors['meeting_total']) ? 'aria-invalid="true"' : '' ?>>
                </div>
                <p class="help mt-1">“ke-” kosong = dihitung otomatis.</p>
                <?= $err('meeting_no') ?><?= $err('meeting_total') ?>
            </fieldset>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <div class="opt-box opt-test">
                <p class="opt-head"><span class="opt-ico"><?= icon('pen', 'size-4') ?></span><span><strong>Tes pada pertemuan ini</strong><small>Jenis penilaian yang diberikan</small></span></p>
                <?= $chips('assessment_kind', ['' => 'Tanpa tes'] + Agendas::ASSESSMENT_KINDS, $v('assessment_kind'), 'Tes pada pertemuan ini') ?>
            </div>
            <div class="opt-box opt-done">
                <p class="opt-head"><span class="opt-ico"><?= icon('check', 'size-4') ?></span><span><strong>Ketuntasan materi</strong><small>Apakah materi selesai hari ini?</small></span></p>
                <?= $chips('completion', ['selesai' => 'Materi selesai', 'belum' => 'Belum selesai'], $completion, 'Ketuntasan materi') ?>
                <div class="mt-3" data-show-when="completion:belum" <?= $needReason ? '' : 'hidden' ?>>
                    <?= $fld('incomplete_reason', ['help' => null, 'placeholder' => 'Mis. waktu habis untuk latihan soal']) ?>
                </div>
            </div>
        </div>

        <!-- Tugas harian (opsional) -->
        <?php $task = $row ? \App\Controllers\Agendas::taskFor($row) : null; ?>
        <div class="opt-box opt-task mt-3">
            <p class="opt-head"><span class="opt-ico"><?= icon('pen', 'size-4') ?></span><span><strong>Tugas harian</strong><small>Nilainya masuk menu Penilaian → Tugas Harian dan rekap nilai</small></span></p>
            <?php if ($task): ?>
                <p class="flex flex-wrap items-center gap-2 text-sm"><?= icon('check', 'size-4 text-ok') ?> <span class="flex-1">Tugas <strong><?= esc($task['title']) ?></strong> sudah dibuat untuk pertemuan ini.</span>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('assessments/' . $task['id'] . '/scores') ?>"><?= icon('chart', 'size-4') ?> Input nilai tugas</a></p>
            <?php else: ?>
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="bulk-check" name="give_task" value="1" data-toggle-target="#task-fields" <?= old('give_task') ? 'checked' : '' ?>> Ada tugas harian di pertemuan ini</label>
                <div id="task-fields" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_16rem]" <?= old('give_task') ? '' : 'hidden' ?>>
                    <div><label class="label" for="f-task_title">Judul tugas</label>
                        <input class="input" id="f-task_title" name="task_title" maxlength="150" value="<?= esc((string) old('task_title'), 'attr') ?>" placeholder="Mis. Latihan soal hal. 24 (kosong = mengikuti pokok bahasan)"></div>
                    <div><label class="label" for="f-task_policy">Siswa tidak mengumpulkan</label>
                        <select class="input" id="f-task_policy" name="task_policy"><?php foreach (\App\Controllers\Assessments::MISSING_POLICIES as $k => $l): ?><option value="<?= $k ?>" <?= old('task_policy') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></div>
                </div>
            <?php endif ?>
        </div>

        <!-- Lampiran materi (opsional) -->
        <?php
        $matFile = $row['material_file'] ?? null;
        $matUrl  = $row && \App\Libraries\MaterialFile::path($matFile) ? site_url('agendas/' . $row['id'] . '/material') : null;
        $matExt  = $matFile ? \App\Libraries\MaterialFile::ext($matFile) : '';
        ?>
        <div class="mt-5" data-material>
            <p class="label mb-1.5">Lampiran materi <span class="text-xs font-normal text-muted">(opsional)</span></p>
            <?php if ($matUrl): ?>
                <div class="mat-card" data-mat-current>
                    <?php if ($matExt === 'webp'): ?>
                        <img src="<?= $matUrl ?>" alt="" class="mat-thumb object-cover" loading="lazy">
                    <?php else: ?>
                        <span class="mat-thumb mat-ext-<?= esc($matExt, 'attr') ?>"><?= strtoupper(esc($matExt)) ?></span>
                    <?php endif ?>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold" title="<?= esc($row['material_name'], 'attr') ?>"><?= esc($row['material_name']) ?></p>
                        <p class="text-xs text-muted"><?= \App\Libraries\MaterialFile::human((int) $row['material_size']) ?> · tersimpan</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1.5">
                        <button type="button" class="btn btn-ghost btn-sm" data-mat-preview="<?= esc($matUrl, 'attr') ?>" data-mat-kind="<?= in_array($matExt, ['webp', 'pdf'], true) ? $matExt : 'office' ?>" data-mat-name="<?= esc($row['material_name'], 'attr') ?>"><?= icon('eye', 'size-4') ?> Pratinjau</button>
                        <a class="btn btn-ghost btn-sm" href="<?= $matUrl ?>?download=1"><?= icon('download', 'size-4') ?><span class="sr-only">Unduh</span></a>
                    </div>
                    <label class="check basis-full text-sm text-muted"><input type="checkbox" name="material_remove" value="1"> Hapus lampiran ini saat disimpan</label>
                </div>
            <?php endif ?>
            <label class="mat-drop <?= $matUrl ? 'mt-2' : '' ?>" for="f-material" data-mat-drop>
                <span class="opt-ico"><?= icon('paperclip', 'size-4') ?></span>
                <span class="min-w-0">
                    <strong class="block text-sm"><?= $matUrl ? 'Ganti lampiran' : 'Pilih atau seret file materi' ?></strong>
                    <small class="block text-xs text-muted">JPG, PNG, PDF, DOCX, XLSX, PPTX · maks. 1 MB (gambar → WebP; file lebih besar dikompres otomatis)</small>
                </span>
            </label>
            <input id="f-material" type="file" name="material" accept="<?= \App\Libraries\MaterialFile::ACCEPT ?>" class="sr-only" data-mat-input>
            <div class="mat-card mt-2" data-mat-new hidden>
                <span class="mat-thumb" data-mat-thumb></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold" data-mat-name></p>
                    <p class="text-xs" data-mat-info></p>
                </div>
                <div class="flex shrink-0 gap-1.5">
                    <button type="button" class="btn btn-ghost btn-sm" data-mat-preview-new><?= icon('eye', 'size-4') ?> Pratinjau</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-mat-clear aria-label="Batalkan file"><?= icon('x', 'size-4') ?></button>
                </div>
            </div>
            <?= $err('material') ?>
        </div>
    </section>

    <!-- 3. Kehadiran -->
    <section class="card p-4 sm:p-6" data-hide-when="status:batal" aria-label="Kehadiran siswa">
        <h2 class="step-title"><span>3</span> Kehadiran</h2>
        <?= view('agendas/attendance', $extra) ?>
    </section>

    <!-- 4. Catatan pembelajaran (dapat dilipat) -->
    <details class="card group p-4 sm:p-6" <?= $notesOpen ? 'open' : '' ?> data-hide-when="status:batal">
        <summary class="step-title cursor-pointer list-none">
            <span>4</span> Catatan pembelajaran <span class="ml-1 text-xs font-medium text-muted">(opsional)</span>
            <?= icon('chevron-down', 'ml-auto size-5 transition group-open:rotate-180') ?>
        </summary>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <?= $fld('activities', ['span' => null, 'label' => 'Kegiatan']) ?>
            <?= $fld('reflection') ?>
            <?= $fld('follow_up') ?>
        </div>
    </details>

    <!-- Bilah aksi menempel -->
    <div class="action-bar no-print" role="region" aria-label="Simpan agenda">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center gap-3">
            <p class="min-w-0 flex-1 truncate text-sm" id="agenda-summary" aria-live="polite">
                <strong data-sum-class><?= esc($classes[$v('class_group_id')] ?? 'Pilih kelas') ?></strong>
                <span class="text-muted" data-sum-subject><?= esc($subjects[$v('subject_id')] ?? '') ?></span>
                <span class="text-muted" data-sum-att></span>
            </p>
            <?php if ($embed): ?>
                <button type="button" class="btn btn-ghost" data-embed-cancel>Kembali</button>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= site_url('agendas') ?>">Batal</a>
            <?php endif ?>
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan agenda</button>
        </div>
    </div>
</form>

<dialog id="mat-dialog" class="mat-dialog" aria-labelledby="mat-dialog-title">
    <div class="flex items-center gap-3 border-b border-line px-4 py-3">
        <p id="mat-dialog-title" class="min-w-0 flex-1 truncate text-sm font-semibold">Pratinjau materi</p>
        <a class="btn btn-ghost btn-sm" data-mat-dl hidden><?= icon('download', 'size-4') ?> Unduh</a>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" data-mat-close aria-label="Tutup pratinjau"><?= icon('x', 'size-4') ?></button>
    </div>
    <div class="mat-dialog-body" data-mat-body></div>
</dialog>
<?= $this->endSection() ?>
