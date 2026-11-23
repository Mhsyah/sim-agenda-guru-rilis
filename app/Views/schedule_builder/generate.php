<?php
/**
 * @var array<int, string> $classes
 * @var array<int, string> $shifts kelas → pagi/siang
 * @var array $ready
 */
use App\Libraries\ScheduleKit;

$ok = $ready['loads'] > 0 && $ready['teach'] > 0;
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-2 max-w-3xl text-muted">Sistem menyusun jadwal satu sesi secara otomatis: tanpa bentrok guru (termasuk dengan sesi lain) maupun kelas, jam kegiatan (Pembiasaan, Istirahat, MBG, …) tidak diisi,
        dan setiap mapel diusahakan 2 jam berurutan, satu kali per hari. Hasilnya ditampilkan dulu untuk Anda periksa sebelum disimpan.</p>
</div>

<ul class="sb-check mb-6" aria-label="Kesiapan data">
    <li class="<?= $ready['loads'] ? 'is-ok' : 'is-bad' ?>"><?= icon($ready['loads'] ? 'check' : 'alert', 'size-4') ?> Beban JP: <?= $ready['loads'] ? $ready['loads'] . ' mapel terisi' : 'belum diisi' ?> <a href="<?= site_url('schedule-builder/setup') ?>">Atur</a></li>
    <?php foreach (ScheduleKit::SHIFTS as $k => $l): ?>
        <li class="<?= $ready['slots'][$k] ? 'is-ok' : 'is-bad' ?>"><?= icon($ready['slots'][$k] ? 'check' : 'alert', 'size-4') ?> Struktur jam <?= $l ?>: <?= $ready['slots'][$k] ?> jam pelajaran/minggu <a href="<?= site_url('schedule-builder/setup?sesi=' . $k) ?>">Atur</a></li>
    <?php endforeach ?>
    <li class="<?= $ready['teach'] ? 'is-ok' : 'is-bad' ?>"><?= icon($ready['teach'] ? 'check' : 'alert', 'size-4') ?> Pembagian mengajar: <?= $ready['teach'] ?> kelas-mapel <a href="<?= site_url('users?role=teacher') ?>">Atur</a></li>
</ul>

<div class="grid gap-4 lg:grid-cols-2">
    <?php foreach (ScheduleKit::SHIFTS as $k => $l): $mine = array_filter($classes, static fn ($id) => ($shifts[$id] ?? 'pagi') === $k, ARRAY_FILTER_USE_KEY); ?>
        <form method="post" action="<?= site_url('schedule-builder/generate') ?>" class="card" aria-labelledby="g-<?= $k ?>" data-progress="Menyusun jadwal <?= $l ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="shift" value="<?= $k ?>">
            <h2 id="g-<?= $k ?>" class="mb-1 flex items-center gap-2 text-base font-bold text-ink"><span class="ov-tag ov-tag-<?= $k ?>"><?= icon($k === 'pagi' ? 'sun' : 'moon', 'size-4') ?></span> Sesi <?= $l ?></h2>
            <p class="mb-3 text-sm text-muted">Centang kelas yang dijadwalkan pada sesi ini.</p>
            <div class="sb-classes">
                <?php foreach ($classes as $id => $n): ?>
                    <label class="<?= isset($mine[$id]) ? 'is-on' : '' ?>"><input type="checkbox" class="bulk-check" name="classes[]" value="<?= (int) $id ?>" <?= isset($mine[$id]) ? 'checked' : '' ?>> <?= esc($n) ?></label>
                <?php endforeach ?>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="btn btn-primary" <?= $ok && $ready['slots'][$k] ? '' : 'disabled' ?>><?= icon('zap', 'size-4') ?> Proses jadwal <?= $l ?></button>
            </div>
        </form>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
