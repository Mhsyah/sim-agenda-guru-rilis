<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Activities;

$semName = $sems[$semId] ?? '';
$query   = http_build_query(array_filter(['semester_id' => $semId ?: null, 'user_id' => $uid ?: null, 'activity_type' => $type ?: null]));
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 max-w-2xl text-muted">Kegiatan guru di luar kegiatan belajar mengajar (rapat, upacara, piket, pendampingan, pelatihan, kepanitiaan, …) yang dicatat guru dari Kalender &amp; Agenda.</p>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        <a class="btn btn-ghost" href="<?= site_url('teacher-activities') . '?' . $query . ($query ? '&' : '') . 'format=csv' ?>"><?= icon('download', 'size-4') ?> Ekspor CSV</a>
        <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?> Cetak</button>
    </div>
</div>

<form method="get" class="filters card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4 no-print" aria-label="Saring rekap kegiatan">
    <div class="field min-w-36"><label class="label" for="ar-sem">Semester</label>
        <select class="input" id="ar-sem" name="semester_id" data-autosubmit><?php foreach ($sems as $id => $n): ?><option value="<?= $id ?>" <?= (int) $id === $semId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select></div>
    <div class="field min-w-52"><label class="label" for="ar-user">Guru</label>
        <select class="input" id="ar-user" name="user_id" data-autosubmit><option value="">Semua guru</option><?php foreach ($teachers as $id => $n): ?><option value="<?= $id ?>" <?= $id === $uid ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select></div>
    <div class="field min-w-52"><label class="label" for="ar-type">Jenis kegiatan</label>
        <select class="input" id="ar-type" name="activity_type" data-autosubmit><option value="">Semua jenis</option><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $k === $type ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-ghost"><?= icon('search', 'size-4') ?> Tampilkan</button>
</form>

<section class="card mb-5 overflow-hidden p-0 sm:p-0" aria-labelledby="ar-sum">
    <div class="border-b border-line px-5 py-3">
        <h2 id="ar-sum" class="text-[0.95rem] font-semibold tracking-tight">Ringkasan per guru · <?= esc($semName) ?></h2>
        <p class="text-xs text-muted"><?= count($rows) ?> kegiatan dari <?= count($summary) ?> guru</p>
    </div>
    <?php if (! $summary): ?>
        <p class="px-6 py-10 text-center text-sm text-muted">Belum ada kegiatan di luar KBM yang dicatat pada saringan ini.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table min-w-[40rem]">
                <caption class="sr-only">Jumlah kegiatan per guru per kelompok</caption>
                <thead><tr><th scope="col">Guru</th><?php foreach ($groups as $g): ?><th scope="col" class="text-right"><?= esc($g) ?></th><?php endforeach ?><th scope="col" class="text-right">Total</th><th scope="col">Terakhir</th></tr></thead>
                <tbody>
                <?php foreach ($summary as $id => $s): ?>
                    <tr>
                        <td><a href="<?= site_url('teacher-activities?' . http_build_query(['semester_id' => $semId, 'user_id' => $id])) ?>"><?= esc($s['name']) ?></a></td>
                        <?php foreach ($groups as $g): ?><td class="text-right tabular-nums"><?= $s[$g] ?: '<span class="text-muted">0</span>' ?></td><?php endforeach ?>
                        <td class="text-right font-bold tabular-nums"><?= $s['total'] ?></td>
                        <td class="whitespace-nowrap text-xs text-muted"><?= fmt_date($s['last']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<?php if ($rows): ?>
<section class="card overflow-hidden p-0 sm:p-0" aria-labelledby="ar-list">
    <div class="border-b border-line px-5 py-3"><h2 id="ar-list" class="text-[0.95rem] font-semibold tracking-tight">Daftar kegiatan</h2></div>
    <div class="overflow-x-auto">
        <table class="table min-w-[48rem]">
            <caption class="sr-only">Daftar kegiatan guru di luar KBM</caption>
            <thead><tr><th scope="col">Tanggal</th><th scope="col">Guru</th><th scope="col">Jenis</th><th scope="col">Kegiatan</th><th scope="col">Uraian</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="whitespace-nowrap"><?= fmt_date($r['activity_date'], false, true) ?><?php if ($t = Activities::timeRange($r)): ?><span class="block text-xs text-muted"><?= esc($t) ?></span><?php endif ?></td>
                    <td><?= esc($r['teacher_name']) ?></td>
                    <td><span class="badge badge-info"><?= esc($types[$r['activity_type']] ?? $r['activity_type']) ?></span></td>
                    <td><strong><?= esc($r['title']) ?></strong><?php if ($r['location']): ?><span class="block text-xs text-muted"><?= esc($r['location']) ?></span><?php endif ?></td>
                    <td class="text-sm"><?= $r['description'] ? excerpt($r['description'], 140) : '<span class="text-muted">—</span>' ?><?= ! empty($r['material_file']) ? ' <span class="badge badge-neutral">Ada bukti</span>' : '' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
