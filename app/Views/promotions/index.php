<?php
/**
 * @var array      $source  T.P. asal (aktif)
 * @var list<array> $later  T.P. setelahnya
 * @var array|null $target  T.P. tujuan
 * @var list<array> $classes
 * @var array<int, string> $targetClasses
 * @var array $counts
 */
$finals   = array_values(array_filter($classes, static fn ($c) => $c['final']));
$promotes = array_values(array_filter($classes, static fn ($c) => ! $c['final']));
$card = static function (array $c, bool $final, ?array $target, array $targetClasses): string {
    ob_start(); ?>
    <article class="pm-card">
        <header class="pm-card-head">
            <div class="min-w-0">
                <h3><?= esc($c['name']) ?> <span class="pm-n"><?= count($c['students']) ?> siswa</span></h3>
                <?php $done = count(array_filter($c['students'], static fn ($s) => $s['done'])); ?>
                <?php if ($done): ?><p class="text-xs text-ok"><?= icon('check', 'size-3.5 inline') ?> <?= $done ?> sudah diproses</p><?php endif ?>
            </div>
            <?php if ($final): ?>
                <span class="badge badge-success"><?= icon('graduate', 'size-3.5') ?> Lulus</span>
            <?php elseif ($target): ?>
                <label class="pm-to">
                    <span class="text-xs text-muted">Naik ke</span>
                    <select class="input h-9 min-h-0 w-auto text-sm" name="map[<?= $c['id'] ?>]" aria-label="Kelas tujuan untuk <?= esc($c['name'], 'attr') ?>">
                        <?php if (! $c['target_exists']): ?><option value="<?= esc($c['target_name'], 'attr') ?>" selected><?= esc($c['target_name']) ?> (dibuat baru)</option><?php endif ?>
                        <?php foreach ($targetClasses as $tn): ?>
                            <option value="<?= esc($tn, 'attr') ?>" <?= $tn === $c['target_name'] ? 'selected' : '' ?>><?= esc($tn) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
            <?php else: ?>
                <span class="badge badge-neutral">Perlu T.P. tujuan</span>
            <?php endif ?>
        </header>
        <details class="pm-list">
            <summary>Lihat siswa &amp; tandai yang <?= $final ? 'tidak lulus' : 'tinggal kelas' ?></summary>
            <ul>
                <?php foreach ($c['students'] as $s): ?>
                    <li class="<?= $s['done'] ? 'is-done' : '' ?>">
                        <label>
                            <input type="checkbox" class="bulk-check" name="except[]" value="<?= (int) $s['id'] ?>" <?= $s['done'] ? 'disabled' : '' ?>>
                            <span class="min-w-0 flex-1 truncate"><?= esc($s['full_name']) ?></span>
                            <span class="text-xs text-muted tabular-nums"><?= esc($s['nis'] ?: $s['nisn']) ?></span>
                            <?php if ($s['done']): ?><span class="badge badge-success"><?= $final ? 'Lulus' : 'Sudah' ?></span><?php endif ?>
                        </label>
                    </li>
                <?php endforeach ?>
            </ul>
            <p class="px-1 pt-2 text-xs text-muted">Centang = <?= $final ? 'tidak lulus (tetap aktif, ditempatkan di ' . esc($c['name']) . ' T.P. tujuan)' : 'tinggal kelas (ditempatkan di ' . esc($c['name']) . ' T.P. tujuan)' ?>.</p>
        </details>
    </article>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-2 max-w-3xl text-muted">Proses akhir tahun pelajaran: kelas akhir (tingkat <?= $finalGrade ? esc(int_to_roman($finalGrade)) : '—' ?>) diluluskan, kelas lain dinaikkan ke tahun pelajaran berikutnya.
        Riwayat kelas, nilai, dan kehadiran tahun ini tetap tersimpan.</p>
</div>

<!-- Tahun asal → tujuan -->
<form method="get" class="card mb-4 flex flex-wrap items-end gap-3 p-4 sm:p-4" aria-label="Pilih tahun pelajaran tujuan">
    <div>
        <p class="label">Dari T.P.</p>
        <p class="input flex h-10 min-h-0 items-center font-semibold"><?= esc($source['name']) ?></p>
    </div>
    <span class="pb-2.5 text-muted" aria-hidden="true"><?= icon('chevron-right', 'size-5') ?></span>
    <div class="field">
        <label class="label" for="pm-ke">Ke T.P.</label>
        <?php if ($later): ?>
            <select class="input h-10 min-h-0" id="pm-ke" name="ke" data-autosubmit>
                <?php foreach ($later as $y): ?><option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === (int) ($target['id'] ?? 0) ? 'selected' : '' ?>><?= esc($y['name']) ?></option><?php endforeach ?>
            </select>
        <?php else: ?>
            <p class="input flex h-10 min-h-0 items-center text-muted">Belum ada</p>
        <?php endif ?>
    </div>
    <?php if (! $later): ?>
        <p class="alert alert-warning m-0 flex-1 basis-80 text-sm"><?= icon('alert', 'size-4 shrink-0') ?>
            <span>Untuk kenaikan kelas, buat dulu tahun pelajaran berikutnya di <a href="<?= site_url('academic-years/new') ?>"><strong>Tahun Pelajaran</strong></a>. Kelulusan tetap bisa diproses sekarang.</span></p>
    <?php endif ?>
</form>

<ul class="u-stats mb-6" aria-label="Ringkasan">
    <li><span class="u-stat-ico is-indigo"><?= icon('users', 'size-4') ?></span><b><?= $counts['students'] ?></b><span>Siswa aktif</span></li>
    <li><span class="u-stat-ico is-green"><?= icon('graduate', 'size-4') ?></span><b><?= $counts['final'] ?></b><span>Kelas akhir (lulus)</span></li>
    <li><span class="u-stat-ico is-amber"><?= icon('chart', 'size-4') ?></span><b><?= $counts['promote'] ?></b><span>Naik kelas</span></li>
    <li><span class="u-stat-ico"><?= icon('check', 'size-4') ?></span><b><?= $counts['done'] ?></b><span>Sudah diproses</span></li>
</ul>

<?php if (! $classes): ?>
    <div class="card py-14 text-center text-muted">Belum ada siswa aktif di kelas pada T.P. <?= esc($source['name']) ?>.</div>
<?php else: ?>
<form method="post" action="<?= site_url('promotions') ?>" data-confirm="Proses kelulusan<?= $target ? ' & kenaikan kelas ke T.P. ' . esc($target['name'], 'attr') : '' ?> sekarang? Siswa yang dicentang dikecualikan." data-confirm-label="Ya, proses">
    <?= csrf_field() ?>
    <input type="hidden" name="target_year_id" value="<?= (int) ($target['id'] ?? 0) ?>">

    <section class="mb-6" aria-labelledby="pm-final">
        <h2 id="pm-final" class="pm-title"><span class="ov-tag ov-tag-pagi"><?= icon('graduate', 'size-4') ?></span> Kelulusan — kelas <?= esc(int_to_roman($finalGrade)) ?></h2>
        <div class="pm-grid"><?php foreach ($finals as $c) { echo $card($c, true, $target, $targetClasses); } ?></div>
    </section>

    <?php if ($promotes): ?>
        <section class="mb-6" aria-labelledby="pm-up">
            <h2 id="pm-up" class="pm-title"><span class="ov-tag ov-tag-siang"><?= icon('chart', 'size-4') ?></span> Kenaikan kelas<?= $target ? ' ke T.P. ' . esc($target['name']) : '' ?></h2>
            <?php if (! $target): ?><p class="mb-3 text-sm text-muted">Kenaikan kelas dilewati sampai tahun pelajaran tujuan dibuat.</p><?php endif ?>
            <div class="pm-grid"><?php foreach ($promotes as $c) { echo $card($c, false, $target, $targetClasses); } ?></div>
        </section>
    <?php endif ?>

    <div class="pm-bar">
        <?php if ($target): ?>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="bulk-check" name="create_classes" value="1" checked> Buat otomatis kelas yang belum ada di T.P. <?= esc($target['name']) ?></label>
        <?php else: ?><span class="text-sm text-muted">Hanya kelulusan yang akan diproses.</span><?php endif ?>
        <button class="btn btn-primary"><?= icon('graduate', 'size-4') ?> Proses <?= $target ? 'kelulusan & kenaikan' : 'kelulusan' ?></button>
    </div>
</form>
<?php endif ?>
<?= $this->endSection() ?>
