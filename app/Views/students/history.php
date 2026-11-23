<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$st      = $student['status'];
$tone    = $st === 'aktif' ? 'success' : ($st === 'pindah' || $st === 'nonaktif' ? 'warning' : 'info');
$names   = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
$current = $placements[0] ?? null;
?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= site_url('students') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Data Siswa</a>
</nav>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div class="flex items-center gap-4">
        <?= user_avatar(['id' => 0, 'name' => $student['full_name'], 'gender' => null], 'size-14', 'text-lg') ?>
        <div>
            <h1 class="page-title"><?= esc($student['full_name']) ?></h1>
            <p class="mt-1 text-sm text-muted">NIS <?= esc($student['nis'] ?: '—') ?> · NISN <?= esc($student['nisn'] ?: '—') ?> · <?= $student['gender'] === 'P' ? 'Perempuan' : ($student['gender'] === 'L' ? 'Laki-laki' : '—') ?></p>
        </div>
    </div>
    <?php if (\App\Libraries\Auth::isAdmin() && empty($student['deleted_at'])): ?>
        <a class="btn btn-ghost" href="<?= site_url('students/' . $student['id'] . '/edit') ?>"><?= icon('edit', 'size-4') ?> Ubah data</a>
    <?php endif ?>
</div>

<div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
    <div class="space-y-4">
        <section class="card" aria-labelledby="st-title">
            <h2 id="st-title" class="text-[0.95rem] font-semibold tracking-tight">Status</h2>
            <p class="mt-3 flex flex-wrap gap-1.5">
                <span class="badge badge-<?= $tone ?> acct-badge"><?= icon($st === 'aktif' ? 'check' : 'info', 'size-3.5') ?> <?= esc(student_statuses()[$st] ?? $st) ?></span>
                <?php if (! empty($student['deleted_at'])): ?><?= badge('Diarsipkan', 'neutral') ?><?php endif ?>
            </p>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-muted">
                <dt>Kelas saat ini</dt><dd class="text-ink"><?= esc($current['class_name'] ?? 'Belum ditempatkan') ?></dd>
                <dt>Tanggal lahir</dt><dd class="text-ink"><?= $student['birth_date'] ? fmt_date($student['birth_date'], true) : '—' ?></dd>
            </dl>
        </section>
        <section class="card" aria-labelledby="hist-title">
            <h2 id="hist-title" class="text-[0.95rem] font-semibold tracking-tight">Riwayat Status</h2>
            <ol class="timeline mt-4">
                <?php foreach ($timeline as $h): ?>
                    <li class="timeline-item tl-<?= esc($h['tone'], 'attr') ?>">
                        <p class="text-sm font-semibold"><?= esc($h['title']) ?></p>
                        <p class="text-xs text-muted"><?= esc($h['actor']) ?> · <?= esc($h['at']) ?></p>
                        <?php if ($h['note']): ?><p class="timeline-note"><?= esc($h['note']) ?></p><?php endif ?>
                    </li>
                <?php endforeach ?>
            </ol>
        </section>
    </div>

    <div class="space-y-4">
        <section class="card" aria-labelledby="pl-title">
            <h2 id="pl-title" class="mb-3 text-[0.95rem] font-semibold tracking-tight">Riwayat kelas</h2>
            <?php if (! $placements): ?>
                <p class="text-sm text-muted">Belum pernah ditempatkan di kelas.</p>
            <?php else: ?>
                <ul class="grid gap-2 md:grid-cols-2">
                    <?php foreach ($placements as $p): ?>
                        <li class="flex items-center justify-between gap-2 rounded-xl border border-line px-3 py-2 text-sm">
                            <span><span class="text-muted">T.P. <?= esc($p['year_name']) ?></span> · <strong><?= esc($p['class_name']) ?></strong></span>
                            <?= badge(ucfirst((string) $p['status']), $p['status'] === 'aktif' ? 'success' : 'neutral') ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="card" aria-labelledby="att-title">
            <h2 id="att-title" class="mb-3 text-[0.95rem] font-semibold tracking-tight">Rekap kehadiran</h2>
            <?php if (! $attendance): ?>
                <p class="text-sm text-muted">Belum ada data kehadiran dari agenda guru.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <caption>Rekap kehadiran per semester</caption>
                        <thead><tr><th scope="col">Periode</th><?php foreach ($names as $c => $n): ?><th scope="col" class="num"><?= $n ?></th><?php endforeach ?><th scope="col" class="num">% Hadir</th></tr></thead>
                        <tbody>
                        <?php foreach ($attendance as $per => $c): $tot = array_sum($c); ?>
                            <tr><td><?= esc($per) ?></td><?php foreach (array_keys($names) as $k): ?><td class="num"><?= $c[$k] ?></td><?php endforeach ?>
                                <td class="num font-semibold"><?= $tot ? fmt_num($c['H'] / $tot * 100, 1) . '%' : '—' ?></td></tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>

        <section class="card" aria-labelledby="abs-title">
            <h2 id="abs-title" class="mb-3 text-[0.95rem] font-semibold tracking-tight">Ketidakhadiran terbaru</h2>
            <?php if (! $absences): ?>
                <p class="text-sm text-muted">Tidak ada catatan sakit, izin, atau alpa.</p>
            <?php else: ?>
                <ul class="grid gap-2">
                    <?php foreach ($absences as $a): ?>
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-line px-3 py-2 text-sm">
                            <span class="att-legend"><b class="lg-<?= strtolower($a['status']) ?>"><?= esc($a['status']) ?></b></span>
                            <span class="font-medium"><?= fmt_date($a['agenda_date'], false, true) ?></span>
                            <span class="text-muted"><?= esc($a['subject_name']) ?><?= $a['lesson_hours'] ? ' · Jam ' . esc($a['lesson_hours']) : '' ?> · <?= esc($a['teacher'] ?? '') ?></span>
                            <?php if ($a['notes']): ?><span class="basis-full text-xs text-muted">Keterangan: <?= esc($a['notes']) ?></span><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
