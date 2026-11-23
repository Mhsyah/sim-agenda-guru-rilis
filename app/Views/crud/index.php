<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $hasFilters = $searchable || $filterDefs; ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <?php if ($intro): ?><p class="mt-2 max-w-2xl text-muted"><?= esc($intro) ?></p><?php endif ?>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        <?php foreach ($headerLinks as $l): ?>
            <a class="btn btn-ghost" href="<?= $l['url'] ?>"><?= icon($l['icon'] ?? 'file', 'size-4') ?> <?= esc($l['label']) ?></a>
        <?php endforeach ?>
        <?php
        $newQuery = array_filter(['assessment_type_id' => $filters['assessment_type_id'] ?? null, 'academic_year_id' => $filters['academic_year_id'] ?? null]);
        ?>
        <?php if ($canEdit ?? true): ?><a class="btn btn-primary" href="<?= site_url($base . '/new') . ($newQuery ? '?' . http_build_query($newQuery) : '') ?>"><?= icon('plus', 'size-4') ?> Tambah <?= esc(mb_strtoupper($singular) === $singular ? $singular : mb_strtolower($singular)) ?></a><?php endif ?>
    </div>
</div>

<?php foreach (($notices ?? []) as [$tone, $msg, $href, $lbl]): ?>
    <p class="alert alert-<?= $tone ?> mb-3 no-print" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
        <span class="flex-1"><?= esc($msg) ?></span><?php if ($href): ?><a class="btn btn-ghost btn-sm shrink-0" href="<?= esc($href, 'attr') ?>"><?= esc($lbl) ?></a><?php endif ?></p>
<?php endforeach ?>

<?php if (! empty($agendaTabs)): ?><?= view('partials/agenda_tabs', ['active' => $agendaTabs]) ?><?php endif ?>

<?php if (! empty($typeTabs)): ?>
    <nav class="u-tabs ev-tabs mb-4 no-print" aria-label="Jenis asesmen">
        <?php foreach ($typeTabs as $tb): ?>
            <a href="<?= site_url($base . ($tb['code'] !== '' ? '?type=' . $tb['code'] : '')) ?>" <?= ($typeActive ?? '') === $tb['code'] ? 'aria-current="page"' : '' ?> title="<?= esc($tb['title'] ?? 'Semua jenis', 'attr') ?>"><?= esc($tb['label']) ?> <span><?= (int) $tb['n'] ?></span></a>
        <?php endforeach ?>
    </nav>
<?php endif ?>

<?php if ($hasFilters): ?>
    <form method="get" class="filters card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4" role="search" aria-label="Filter <?= esc($title, 'attr') ?>" data-live-search>
        <?php foreach (['type', 'view'] as $keep): ?>
            <?php if (isset($_GET[$keep]) && is_string($_GET[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= esc($_GET[$keep], 'attr') ?>"><?php endif ?>
        <?php endforeach ?>
        <?php if ($searchable): ?>
            <div class="field min-w-56 flex-1">
                <label class="label" for="q">Cari</label>
                <input class="input" type="search" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Ketik kata kunci… (tekan /)">
            </div>
        <?php endif ?>
        <?php foreach ($filterDefs as $name => $def): ?>
            <?php if ((isset($_GET['type']) || ! empty($typeTabs)) && $name === 'assessment_type_id') continue; // jenis dipilih lewat tab ?>
            <div class="field min-w-40">
                <label class="label" for="flt-<?= $name ?>"><?= esc($def['label']) ?></label>
                <select class="input" id="flt-<?= $name ?>" name="<?= $name ?>">
                    <option value="">Semua</option>
                    <?php foreach ($def['options'] as $v => $l): ?>
                        <option value="<?= esc((string) $v, 'attr') ?>" <?= (string) $v === (string) ($filters[$name] ?? '') ? 'selected' : '' ?>><?= esc($l) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php endforeach ?>
        <button class="btn btn-ghost"><?= icon('search', 'size-4') ?> Terapkan</button>
        <?php if (array_filter($filters, static fn ($v) => $v !== '')): ?>
            <a class="btn btn-ghost" href="<?= current_url() . (isset($_GET['type']) ? '?type=' . esc($_GET['type'], 'url') : '') ?>">Reset</a>
        <?php endif ?>
    </form>
<?php endif ?>

<?php $bulk ??= null; $deleteLabel ??= null; ?>
<?php if ($bulk && $table): ?>
    <!-- Aksi massal untuk baris yang dicentang -->
    <form method="post" action="<?= esc($bulk['url'], 'attr') ?>" id="bulk-form" class="bulk-bar no-print" data-bulk-bar hidden>
        <?= csrf_field() ?>
        <p class="text-sm font-semibold"><span data-bulk-count>0</span> dipilih</p>
        <label class="sr-only" for="bulk-action">Aksi</label>
        <select class="input h-9 min-h-0 w-auto py-1 text-sm" id="bulk-action" name="action" data-bulk-action>
            <option value="">— Pilih aksi —</option>
            <option value="delete">Hapus terpilih</option>
            <?php if (! empty($bulk['statuses'])): ?><option value="status">Ubah status…</option><?php endif ?>
            <?php if (! empty($bulk['classes'])): ?><option value="class">Tempatkan ke kelas…</option><?php endif ?>
        </select>
        <?php if (! empty($bulk['statuses'])): ?>
            <label class="sr-only" for="bulk-status">Status baru</label>
            <select class="input h-9 min-h-0 w-auto py-1 text-sm" id="bulk-status" name="status" data-bulk-for="status" hidden>
                <?php foreach ($bulk['statuses'] as $v => $l): ?><option value="<?= esc($v, 'attr') ?>"><?= esc($l) ?></option><?php endforeach ?>
            </select>
        <?php endif ?>
        <?php if (! empty($bulk['classes'])): ?>
            <label class="sr-only" for="bulk-class">Kelas tujuan</label>
            <select class="input h-9 min-h-0 w-auto py-1 text-sm" id="bulk-class" name="class_group_id" data-bulk-for="class" hidden>
                <?php foreach ($bulk['classes'] as $v => $l): ?><option value="<?= esc((string) $v, 'attr') ?>"><?= esc($l) ?></option><?php endforeach ?>
            </select>
        <?php endif ?>
        <button class="btn btn-primary btn-sm" data-bulk-submit>Terapkan</button>
        <button type="button" class="btn btn-ghost btn-sm" data-bulk-clear>Batal pilih</button>
    </form>
<?php endif ?>

<?php if (! empty($hiddenNote)): ?>
    <p class="alert alert-info mb-4" role="status"><?= icon('info', 'size-5 shrink-0') ?>
        <span><?= esc($hiddenNote) ?> <a href="<?= site_url('settings/profile#teaching') ?>">Atur mapel</a></span></p>
<?php endif ?>
<section class="card p-0 sm:p-0" aria-labelledby="tbl-caption" data-live-region>
    <?php if (! $table): ?>
        <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
            <span class="grid size-14 place-items-center rounded-[1.2rem_1.8rem_1.2rem_2rem] bg-surface-2 text-muted"><?= icon('sparkle', 'size-7') ?></span>
            <p class="font-display text-xl font-bold" id="tbl-caption">Belum ada data</p>
            <p class="max-w-md text-sm text-muted"><?= array_filter($filters, static fn ($v) => $v !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Mulai dengan menambahkan ' . esc(mb_strtoupper($singular) === $singular ? $singular : mb_strtolower($singular)) . ' pertama.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption id="tbl-caption"><?= esc($title) ?></caption>
                <thead>
                <tr>
                    <?php if ($bulk): ?><th scope="col" class="w-10 no-print"><input type="checkbox" class="bulk-check" data-bulk-all aria-label="Pilih semua di halaman ini"></th><?php endif ?>
                    <?php foreach ($cols as $c): ?><th scope="col" class="<?= $c['class'] ?? '' ?>"><?= esc($c['label']) ?></th><?php endforeach ?>
                    <?php if (($canEdit ?? true) || array_filter(array_column($table, 'links'))): ?><th scope="col" class="no-print text-right"><span class="sr-only">Aksi</span></th><?php endif ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($table as $r): ?>
                    <tr>
                        <?php if ($bulk): ?><td class="no-print"><input type="checkbox" class="bulk-check" name="ids[]" value="<?= (int) $r['id'] ?>" form="bulk-form" data-bulk-item aria-label="Pilih baris ini"></td><?php endif ?>
                        <?php foreach ($r['cells'] as $k => $cell): ?><td class="<?= $cols[$k]['class'] ?? '' ?>"><?= $cell ?></td><?php endforeach ?>
                        <?php if (($canEdit ?? true) || array_filter(array_column($table, 'links'))): ?>
                        <td class="no-print">
                            <div class="flex justify-end gap-1.5">
                                <?php foreach ($r['links'] as $l): ?>
                                    <a class="btn btn-ghost btn-sm whitespace-nowrap" href="<?= $l['url'] ?>"><?= icon($l['icon'] ?? 'file', 'size-4') ?> <?= esc($l['label']) ?></a>
                                <?php endforeach ?>
                                <?php if ($canEdit ?? true): ?>
                                <a class="btn btn-ghost btn-sm btn-icon size-9" href="<?= site_url($base . '/' . $r['id'] . '/edit') ?>" aria-label="Ubah data baris ini" title="Ubah"><?= icon('edit', 'size-4') ?></a>
                                <form method="post" action="<?= site_url($base . '/' . $r['id'] . '/delete') ?>"
                                      data-confirm="<?= esc($deleteConfirm ?? ($archive ? 'Arsipkan data ini? Data arsip disembunyikan dari daftar tetapi jejak historis (mis. nilai) tetap disimpan.' : 'Hapus data ini secara permanen?'), 'attr') ?>"
                                      data-confirm-label="<?= $deleteLabel ? 'Ya, hapus' : ($archive ? 'Ya, arsipkan' : 'Ya, hapus') ?>">
                                    <?= csrf_field() ?>
                                    <?php $delWord = $deleteLabel ?: ($archive ? 'Arsipkan' : 'Hapus'); ?>
                                    <button class="btn btn-ghost btn-sm btn-icon size-9 hover:text-bad" aria-label="<?= esc($delWord, 'attr') ?> data baris ini" title="<?= esc($delWord, 'attr') ?>"><?= icon($deleteLabel || ! $archive ? 'trash' : 'archive', 'size-4') ?></button>
                                </form>
                                <?php endif ?>
                            </div>
                        </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-5">
            <p class="pt-5 text-sm text-muted">Menampilkan <?= count($table) ?> dari <?= $pager->getTotal() ?> data</p>
            <?= $pager->links() ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
