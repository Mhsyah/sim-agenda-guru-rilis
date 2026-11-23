<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$rows = array_column($table, 'row');
$byGrade = [];
foreach ($rows as $r) {
    // Kelompok: awalan nama ("VII A" → Kelas VII), atau tingkat bila nama tanpa awalan.
    $g = preg_match('/^(\S+)\s/u', $r['name'], $m) ? 'Kelas ' . $m[1] : ($r['grade_level'] ? 'Tingkat ' . (int) $r['grade_level'] : 'Lainnya');
    $byGrade[$g][] = $r;
}
$totalStudents = array_sum(array_map(static fn ($r) => (int) $r['student_count'], $rows));
$empty = count(array_filter($rows, static fn ($r) => (int) $r['student_count'] === 0));
$q = (string) ($filters['q'] ?? '');
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 max-w-2xl text-muted"><?= esc($intro) ?> Klik <strong>Siswa</strong> untuk melihat daftar siswa per kelas.</p>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        <?php foreach ($headerLinks as $l): ?>
            <a class="btn btn-ghost" href="<?= $l['url'] ?>"><?= icon($l['icon'] ?? 'file', 'size-4') ?> <?= esc($l['label']) ?></a>
        <?php endforeach ?>
        <a class="btn btn-primary" href="<?= site_url('classes/new') ?>"><?= icon('plus', 'size-4') ?> Tambah kelas</a>
    </div>
</div>

<div class="list-toolbar mb-5">
    <ul class="mini-stats" aria-label="Ringkasan kelas">
        <li><b><?= count($rows) ?></b> kelas</li>
        <li><b><?= number_format($totalStudents, 0, ',', '.') ?></b> siswa aktif</li>
        <?php if ($empty): ?><li class="is-warn"><b><?= $empty ?></b> kelas belum berisi siswa</li><?php endif ?>
    </ul>
    <form method="get" class="relative min-w-0 flex-1 sm:max-w-sm" role="search" data-live-search>
        <label for="q" class="sr-only">Cari kelas</label>
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
        <input class="input h-10 min-h-0 pl-9 text-sm" type="search" id="q" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari kelas… (mis. VII A)" autocomplete="off">
    </form>
</div>

<div data-live-region>
<?php if (! $rows): ?>
    <div class="card px-6 py-12 text-center text-sm text-muted"><?= $q !== '' ? 'Tidak ada kelas yang cocok dengan “' . esc($q) . '”.' : 'Belum ada kelas di tahun pelajaran ini.' ?></div>
<?php else: ?>
    <?php foreach ($byGrade as $grade => $list): $n = array_sum(array_map(static fn ($r) => (int) $r['student_count'], $list)); ?>
        <?php $noTeacher = count(array_filter($list, static fn ($r) => empty($teachers[(int) $r['id']]))); $noStudent = count(array_filter($list, static fn ($r) => (int) $r['student_count'] === 0)); ?>
        <details class="grade-group" data-grade="<?= esc($grade, 'attr') ?>" <?= $q !== '' || count($byGrade) === 1 ? 'open' : '' ?>>
            <summary class="grade-sum">
                <span class="grade-chev" aria-hidden="true"><?= icon('chevron-down', 'size-4') ?></span>
                <span class="grade-title"><?= esc($grade) ?></span>
                <span class="grade-meta"><?= count($list) ?> kelas · <?= $n ?> siswa</span>
                <span class="grade-flags">
                    <?php if ($noStudent): ?><span class="grade-flag is-warn"><?= $noStudent ?> tanpa siswa</span><?php endif ?>
                    <?php if ($noTeacher): ?><span class="grade-flag"><?= $noTeacher ?> tanpa guru</span><?php endif ?>
                </span>
                <span class="grade-peek" aria-hidden="true">
                    <?php foreach ($list as $r): ?><span class="<?= (int) $r['student_count'] ? '' : 'is-empty' ?>"><?= esc(preg_replace('/^\S+\s+/u', '', $r['name'])) ?></span><?php endforeach ?>
                </span>
            </summary>
            <ul class="class-grid">
                <?php foreach ($list as $r): $id = (int) $r['id']; $tch = $teachers[$id] ?? []; ?>
                    <li class="class-card<?= (int) $r['is_active'] ? '' : ' is-off' ?>">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="class-name"><?= esc($r['name']) ?></p>
                                <p class="text-xs text-muted"><?= (int) $r['student_count'] ?> siswa · <?= (int) $r['male_count'] ?> L / <?= (int) $r['female_count'] ?> P</p>
                            </div>
                            <?= (int) $r['is_active'] ? '' : badge('Nonaktif', 'warning') ?>
                        </div>
                        <?php $tot = max(1, (int) $r['student_count']); ?>
                        <div class="class-bar" title="Laki-laki <?= (int) $r['male_count'] ?>, Perempuan <?= (int) $r['female_count'] ?>" aria-hidden="true">
                            <span style="width:<?= round((int) $r['male_count'] / $tot * 100) ?>%"></span><span style="width:<?= round((int) $r['female_count'] / $tot * 100) ?>%"></span>
                        </div>
                        <p class="class-teach" title="<?= esc(implode("\n", $tch), 'attr') ?>">
                            <?= icon('users', 'size-3.5 shrink-0') ?>
                            <span class="truncate"><?= $tch ? esc(implode(', ', array_slice($tch, 0, 2))) . (count($tch) > 2 ? ' +' . (count($tch) - 2) : '') : 'Belum ada guru pengajar' ?></span>
                        </p>
                        <div class="class-actions no-print">
                            <button type="button" class="btn btn-ghost btn-sm" data-class-students="<?= site_url('classes/' . $id . '/students') ?>"><?= icon('users', 'size-4') ?> Siswa</button>
                            <a class="btn btn-ghost btn-sm btn-icon size-8" href="<?= site_url('classes/' . $id . '/edit') ?>" title="Ubah" aria-label="Ubah kelas <?= esc($r['name'], 'attr') ?>"><?= icon('edit', 'size-4') ?></a>
                            <form method="post" action="<?= site_url('classes/' . $id . '/delete') ?>" data-confirm="Hapus kelas <?= esc($r['name'], 'attr') ?>? Riwayat nilai & agenda tetap tersimpan." data-confirm-label="Ya, hapus">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm btn-icon size-8 hover:text-bad" title="Hapus" aria-label="Hapus kelas <?= esc($r['name'], 'attr') ?>"><?= icon('trash', 'size-4') ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        </details>
    <?php endforeach ?>
<?php endif ?>
</div>

<dialog id="class-dialog" class="class-dialog" aria-labelledby="cd-title">
    <div data-class-body><p class="p-6 text-sm text-muted">Memuat…</p></div>
</dialog>
<?= $this->endSection() ?>
