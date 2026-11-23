<?php
/**
 * @var string $token
 * @var array  $draft
 * @var array|null $sheet tabel per sesi (format TeacherSchedules::overall)
 * @var array  $legend
 * @var int    $current jumlah blok jadwal lama pada kelas-kelas ini
 * @var array<int, string> $names
 * @var array<int, string> $classes
 */
use App\Libraries\ScheduleKit;

$shiftName = ScheduleKit::SHIFTS[$draft['shift']] ?? $draft['shift'];
$isUpload  = $draft['source'] === 'upload';
$issues    = $draft['issues'] ?? [];
$byLevel   = ['error' => [], 'warning' => [], 'info' => []];
foreach ($issues as $i) {
    $byLevel[$i['level']][] = $i;
}
$st = $draft['stats'] ?? [];
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Pratinjau Jadwal <?= esc($shiftName) ?></h1>
        <p class="mt-2 text-muted"><?= $isUpload ? 'Hasil membaca PDF' . (! empty($draft['file']) ? ' <strong>' . esc($draft['file']) . '</strong>' : '') : 'Hasil proses otomatis' ?> ·
            belum disimpan. Periksa kesesuaiannya, lalu pilih <strong>Simpan</strong> atau <strong>Batalkan</strong>.</p>
    </div>
</div>

<ul class="u-stats mb-4" aria-label="Ringkasan draf">
    <li><span class="u-stat-ico is-indigo"><?= icon('grid', 'size-4') ?></span><b><?= count($draft['class_ids']) ?></b><span>Kelas sesi <?= esc($shiftName) ?></span></li>
    <li><span class="u-stat-ico is-green"><?= icon('calendar', 'size-4') ?></span><b><?= count($draft['blocks']) ?></b><span>Blok jadwal baru (lama: <?= $current ?>)</span></li>
    <?php if (! $isUpload && $st): ?>
        <li><span class="u-stat-ico <?= ($st['missing'] ?? 0) ? 'is-red' : 'is-green' ?>"><?= icon('clock', 'size-4') ?></span><b><?= (int) $st['placed'] ?>/<?= (int) $st['demand'] ?></b><span>JP terjadwal</span></li>
    <?php else: ?>
        <li><span class="u-stat-ico is-amber"><?= icon('clock', 'size-4') ?></span><b><?= (int) ($st['activities'] ?? 0) ?></b><span>Slot kegiatan</span></li>
        <li><span class="u-stat-ico"><?= icon('shield', 'size-4') ?></span><b><?= (int) ($st['piket'] ?? 0) ?></b><span>Guru piket terbaca</span></li>
    <?php endif ?>
    <li><span class="u-stat-ico <?= $byLevel['error'] ? 'is-red' : 'is-green' ?>"><?= icon($byLevel['error'] ? 'alert' : 'check', 'size-4') ?></span><b><?= count($byLevel['error']) ?></b><span>Masalah · <?= count($byLevel['warning']) ?> peringatan</span></li>
</ul>

<?php if ($issues): ?>
    <section class="card mb-5" aria-labelledby="pv-iss">
        <h2 id="pv-iss" class="mb-2 text-base font-bold">Periksa kesesuaian</h2>
        <ul class="pv-issues">
            <?php foreach (['error', 'warning', 'info'] as $lv): foreach ($byLevel[$lv] as $i): ?>
                <li class="is-<?= $lv ?>"><?= icon($lv === 'error' ? 'alert' : ($lv === 'warning' ? 'info' : 'check'), 'size-4 shrink-0') ?>
                    <span><?php if ($i['where'] !== ''): ?><strong><?= esc($i['where']) ?>:</strong> <?php endif ?><?= esc($i['text']) ?></span></li>
            <?php endforeach; endforeach ?>
        </ul>
    </section>
<?php else: ?>
    <p class="alert alert-success mb-5"><?= icon('check', 'size-5 shrink-0') ?> Tidak ada bentrok atau ketidaksesuaian yang ditemukan.</p>
<?php endif ?>

<?php if ($sheet): $n = count($sheet['classes']); ?>
    <section class="ov-sheet mb-5" aria-label="Tabel jadwal draf">
        <header class="ov-head">
            <h2 class="ov-title"><span class="ov-tag ov-tag-<?= esc($draft['shift'], 'attr') ?>"><?= icon($draft['shift'] === 'pagi' ? 'sun' : 'moon', 'size-4') ?></span>JADWAL <?= esc(strtoupper($shiftName)) ?> (DRAF)</h2>
            <p class="ov-sub">Kelas <?= esc(implode(', ', $sheet['classes'])) ?> · pukul <?= esc(str_replace(':', '.', $sheet['range'])) ?></p>
        </header>
        <div class="table-wrap ov-wrap">
            <table class="ov-table">
                <thead>
                <tr><th rowspan="2">Hari</th><th rowspan="2">Jam</th><th rowspan="2">Pukul</th>
                    <?php foreach ($sheet['classes'] as $cn): ?><th colspan="2" class="ov-cls"><?= esc($cn) ?></th><?php endforeach ?></tr>
                <tr><?php for ($i = 0; $i < $n; $i++): ?><th class="ov-sh">KD</th><th class="ov-sh">MP</th><?php endfor ?></tr>
                </thead>
                <?php foreach ($sheet['days'] as $day): ?>
                    <tbody class="ov-dayblock">
                    <?php foreach ($day['rows'] as $ri => $r): ?>
                        <tr class="<?= $r['cells'] ? '' : 'ov-break' ?>">
                            <?php if ($ri === 0): ?><th rowspan="<?= count($day['rows']) ?>" class="ov-dayname"><span><?= esc($day['name']) ?></span></th><?php endif ?>
                            <td class="ov-jam"><?= esc($r['jam']) ?></td>
                            <td class="ov-time"><?= esc(str_replace(':', '.', $r['pukul'])) ?></td>
                            <?php if (! $r['cells']): ?>
                                <td colspan="<?= 2 * $n ?>" class="ov-rest">Istirahat / kegiatan</td>
                            <?php else: foreach (array_keys($sheet['classes']) as $cid): $c = $r['cells'][$cid] ?? null; ?>
                                <?php if ($c): ?>
                                    <td class="ov-kd" style="--mp: <?= esc($c['clr'], 'attr') ?>" title="<?= esc($c['full'], 'attr') ?>"><?= esc($c['kd'] !== '' ? $c['kd'] : '–') ?></td>
                                    <td class="ov-mp" title="<?= esc($c['full'], 'attr') ?>"><b><?= esc($c['mp']) ?></b><small><?= esc($c['name']) ?></small></td>
                                <?php else: ?><td class="ov-kd pv-empty"></td><td class="ov-mp pv-empty"></td><?php endif ?>
                            <?php endforeach; endif ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                <?php endforeach ?>
            </table>
        </div>
        <ul class="ov-legend mt-3"><?php foreach ($legend as $code => $lg): ?><li style="--mp: <?= esc($lg['clr'], 'attr') ?>"><b><?= esc($code) ?></b> <?= esc($lg['name']) ?></li><?php endforeach ?></ul>
    </section>
<?php endif ?>

<?php if ($isUpload && ! empty($draft['slots'])): ?>
    <section class="card mb-5" aria-labelledby="pv-act">
        <h2 id="pv-act" class="mb-2 text-base font-bold">Kegiatan non-mengajar yang terbaca</h2>
        <ul class="flex flex-wrap gap-2 text-xs">
            <?php foreach (array_filter($draft['slots'], static fn ($s) => $s['kind'] === 'kegiatan') as $s): ?>
                <li class="badge badge-neutral"><?= esc(mb_substr(day_names()[$s['day']] ?? '', 0, 3)) ?> <?= esc(str_replace(':', '.', $s['start'] . '–' . $s['end'])) ?> · <?= esc($s['label']) ?></li>
            <?php endforeach ?>
        </ul>
        <?php if (! empty($draft['piket'])): ?>
            <h3 class="mb-2 mt-4 text-sm font-bold">Guru piket</h3>
            <ul class="grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($draft['piket'] as $d => $uids): ?>
                    <li><strong><?= esc(day_names()[(int) $d] ?? '') ?>:</strong> <?= esc(implode(', ', array_map(static fn ($u) => $names[$u] ?? '?', $uids))) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
<?php endif ?>

<div class="pm-bar">
    <form method="post" action="<?= site_url('schedule-builder/discard/' . $token) ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= icon('x', 'size-4') ?> Batalkan</button></form>
    <form method="post" action="<?= site_url('schedule-builder/apply/' . $token) ?>" class="flex flex-wrap items-center gap-3" data-progress="Menyimpan jadwal"
          data-confirm="Simpan jadwal ini? Jadwal lama kelas <?= esc(implode(', ', $classes), 'attr') ?> (sesi <?= esc($shiftName, 'attr') ?>) akan diganti. Data lama dibackup otomatis." data-confirm-label="Ya, simpan">
        <?= csrf_field() ?>
        <?php if ($isUpload && ! empty($draft['slots'])): ?><label class="flex items-center gap-1.5 text-sm"><input type="checkbox" class="bulk-check" name="slots" value="1" checked> Simpan struktur jam & kegiatan</label><?php endif ?>
        <?php if ($isUpload && ! empty($draft['piket'])): ?><label class="flex items-center gap-1.5 text-sm"><input type="checkbox" class="bulk-check" name="piket" value="1" checked> Perbarui piket <?= esc($shiftName) ?></label><?php endif ?>
        <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan & ganti jadwal <?= esc($shiftName) ?></button>
    </form>
</div>
<?= $this->endSection() ?>
