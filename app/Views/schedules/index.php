<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted">Jadwal mingguan untuk tahun ajaran aktif. Sistem menolak jadwal yang bentrok.</p>
    </div>
    <div class="flex gap-2 no-print">
        <button type="button" class="btn btn-ghost" data-print><?= icon('printer', 'size-4') ?> Cetak jadwal</button>
        <a class="btn btn-primary" href="<?= site_url('schedules/new') ?>"><?= icon('plus', 'size-4') ?> Tambah jadwal</a>
    </div>
</div>
<?php if (! empty($piket)): ?>
    <p class="alert alert-info mb-6 no-print" role="status"><?= icon('shield', 'size-5 shrink-0') ?>
        <span>Jadwal piket Anda: <strong><?= esc(implode(', ', array_map(static fn ($p) => day_names()[$p['day']] . ' (' . (\App\Libraries\DutyRoster::SHIFTS[$p['shift']] ?? $p['shift']) . ')', $piket))) ?></strong>.</span></p>
<?php endif ?>
<section class="paper print-landscape mb-6" aria-labelledby="grid-title">
    <?php if (! empty($piket)): ?><p class="print-only mb-2 text-sm">Piket: <?= esc(implode(', ', array_map(static fn ($p) => day_names()[$p['day']] . ' (' . (\App\Libraries\DutyRoster::SHIFTS[$p['shift']] ?? $p['shift']) . ')', $piket))) ?></p><?php endif ?>
    <h2 id="grid-title" class="sheet-title">JADWAL MENGAJAR</h2>
    <p class="mb-3 text-center text-sm"><?= esc($authUser['name'] ?? '') ?> · T.P. <?= esc($period['year']['name'] ?? '') ?></p>
    <?= view('schedules/grid', ['grid' => $grid]) ?>
</section>

<h2 class="mb-3 font-display text-xl font-bold no-print">Kelola per hari</h2>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 no-print">
    <?php foreach (array_slice(day_names(), 0, 6, true) as $d => $dn): ?>
        <section class="card <?= $d % 2 ? 'card-organic' : '' ?> <?= (int) date('N') === $d ? 'ring-2 ring-accent' : '' ?>" aria-labelledby="day-<?= $d ?>">
            <h2 id="day-<?= $d ?>" class="mb-3 font-display text-lg font-bold"><?= $dn ?><?= (int) date('N') === $d ? ' <span class="badge badge-info">Hari ini</span>' : '' ?></h2>
            <?php if (empty($byDay[$d])): ?>
                <p class="text-sm text-muted">Tidak ada jadwal.</p>
            <?php else: ?>
                <ul class="grid gap-2">
                    <?php foreach ($byDay[$d] as $s): ?>
                        <li class="flex items-center gap-2 rounded-2xl border border-line p-2.5">
                            <span class="rounded-lg bg-surface-2 px-2 py-0.5 font-mono text-xs tabular-nums"><?= $s['lesson_hours'] ? 'Jam ' . esc($s['lesson_hours']) . ' · ' : '' ?><?= fmt_time($s['start_time']) ?>–<?= fmt_time($s['end_time']) ?></span>
                            <span class="min-w-0 flex-1 text-sm"><strong><?= esc($s['class_name']) ?></strong> · <?= esc($s['subject_name']) ?><?= $s['room'] ? ' <span class="text-muted">(' . esc($s['room']) . ')</span>' : '' ?></span>
                            <a class="btn btn-ghost btn-sm btn-icon size-9 no-print" href="<?= site_url('schedules/' . $s['id'] . '/edit') ?>" aria-label="Ubah jadwal <?= esc($s['class_name'] . ' ' . $dn . ' ' . fmt_time($s['start_time']), 'attr') ?>"><?= icon('edit', 'size-4') ?></a>
                            <form method="post" action="<?= site_url('schedules/' . $s['id'] . '/delete') ?>" data-confirm="Hapus jadwal ini?" data-confirm-label="Ya, hapus" class="no-print">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm btn-icon size-9" aria-label="Hapus jadwal <?= esc($s['class_name'] . ' ' . $dn . ' ' . fmt_time($s['start_time']), 'attr') ?>"><?= icon('trash', 'size-4') ?></button>
                            </form>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
