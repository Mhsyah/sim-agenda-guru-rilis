<?php
/**
 * @var list<array> $subjects
 * @var list<int>   $grades
 * @var array<int, array<int, int>> $loads
 * @var string $shift
 * @var array<int, list<array>> $slots
 * @var array<string, int> $counts
 */
use App\Libraries\ScheduleKit;

$days = array_slice(day_names(), 0, 6, true);
$row  = 0;
$slotRow = static function (int $d, ?array $s) use (&$row): string {
    $i  = $row++;
    $k  = $s['kind'] ?? 'pelajaran';
    ob_start(); ?>
    <tr class="sb-slot <?= $k === 'kegiatan' ? 'is-act' : '' ?>">
        <td><input type="hidden" name="slot[<?= $i ?>][day]" value="<?= $d ?>">
            <input class="input h-9 min-h-0 w-24 text-sm" type="time" name="slot[<?= $i ?>][start]" value="<?= esc(substr((string) ($s['start_time'] ?? ''), 0, 5), 'attr') ?>" aria-label="Jam mulai"></td>
        <td><input class="input h-9 min-h-0 w-24 text-sm" type="time" name="slot[<?= $i ?>][end]" value="<?= esc(substr((string) ($s['end_time'] ?? ''), 0, 5), 'attr') ?>" aria-label="Jam selesai"></td>
        <td><select class="input h-9 min-h-0 w-auto text-sm" name="slot[<?= $i ?>][kind]" data-sb-kind aria-label="Jenis">
                <option value="pelajaran" <?= $k === 'pelajaran' ? 'selected' : '' ?>>Jam pelajaran</option>
                <option value="kegiatan" <?= $k === 'kegiatan' ? 'selected' : '' ?>>Kegiatan (bukan mengajar)</option>
            </select></td>
        <td>
            <input class="input h-9 min-h-0 w-16 text-sm sb-no" type="number" min="1" max="15" name="slot[<?= $i ?>][lesson_no]" value="<?= esc((string) ($s['lesson_no'] ?? ''), 'attr') ?>" placeholder="Jam ke" aria-label="Jam ke">
            <input class="input h-9 min-h-0 w-44 text-sm sb-label" list="sb-acts" name="slot[<?= $i ?>][label]" value="<?= esc((string) ($s['label'] ?? ''), 'attr') ?>" placeholder="mis. Pembiasaan" aria-label="Nama kegiatan">
        </td>
        <td class="text-center"><label class="inline-flex items-center gap-1 text-xs text-muted"><input type="checkbox" class="bulk-check" name="slot[<?= $i ?>][hapus]" value="1"> Hapus</label></td>
    </tr>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-2 max-w-3xl text-muted">Bahan untuk <a href="<?= site_url('schedule-builder/generate') ?>">Proses Jadwal Otomatis</a>: jumlah JP tiap mapel per minggu, serta susunan jam pelajaran dan kegiatan non-mengajar (Pembiasaan, Upacara, Istirahat, MBG, dll.) per sesi.</p>
</div>

<!-- Beban JP -->
<section class="card mb-6" aria-labelledby="sb-jp">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="sb-jp" class="text-base font-bold">Beban JP per minggu</h2>
            <p class="text-sm text-muted">Jumlah jam pelajaran tiap mapel untuk satu kelas, per tingkat. Kosong/0 = tidak dijadwalkan.</p>
        </div>
        <form method="post" action="<?= site_url('schedule-builder/loads') ?>" data-confirm="Isi beban JP dari jadwal yang ada sekarang? Isian saat ini diganti." data-confirm-label="Ya, isi">
            <?= csrf_field() ?><input type="hidden" name="auto" value="1">
            <button class="btn btn-ghost btn-sm"><?= icon('refresh', 'size-4') ?> Isi dari jadwal sekarang</button>
        </form>
    </div>
    <form method="post" action="<?= site_url('schedule-builder/loads') ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Beban JP per mapel dan tingkat</caption>
                <thead><tr><th scope="col">Mata pelajaran</th><?php foreach ($grades as $g): ?><th scope="col" class="w-28 text-center">Kelas <?= esc(int_to_roman($g)) ?></th><?php endforeach ?></tr></thead>
                <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><span class="sb-mp" style="--mp: <?= esc(subject_color($s['code']), 'attr') ?>"></span><strong><?= esc($s['code']) ?></strong> <span class="text-sm text-muted"><?= esc($s['name']) ?></span></td>
                        <?php foreach ($grades as $g): ?>
                            <td class="text-center"><input class="input h-9 min-h-0 w-20 text-center text-sm tabular-nums" type="number" min="0" max="20" name="jp[<?= (int) $s['id'] ?>][<?= $g ?>]"
                                value="<?= ($v = $loads[(int) $s['id']][$g] ?? 0) ? $v : '' ?>" placeholder="0" aria-label="JP <?= esc($s['code'], 'attr') ?> kelas <?= $g ?>"></td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot><tr><th scope="row">Total JP per kelas</th>
                    <?php foreach ($grades as $g): ?><td class="text-center font-bold tabular-nums"><?= array_sum(array_map(static fn ($x) => $x[$g] ?? 0, $loads)) ?></td><?php endforeach ?>
                </tr></tfoot>
            </table>
        </div>
        <div class="mt-4 flex justify-end"><button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan beban JP</button></div>
    </form>
</section>

<!-- Struktur jam & kegiatan -->
<section class="card" aria-labelledby="sb-slot">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="sb-slot" class="text-base font-bold">Struktur jam & kegiatan</h2>
            <p class="text-sm text-muted">Jam pelajaran diisi otomatis saat proses; kegiatan tidak diisi pelajaran. Tidak ada jam pelajaran yang melewati kegiatan/jeda.</p>
        </div>
        <form method="post" action="<?= site_url('schedule-builder/slots') ?>" data-confirm="Ambil struktur jam Pagi & Siang dari jadwal yang ada? Struktur saat ini diganti." data-confirm-label="Ya, ambil">
            <?= csrf_field() ?><input type="hidden" name="auto" value="1">
            <button class="btn btn-ghost btn-sm"><?= icon('refresh', 'size-4') ?> Ambil dari jadwal sekarang</button>
        </form>
    </div>
    <nav class="u-tabs mb-4" aria-label="Sesi">
        <?php foreach (ScheduleKit::SHIFTS as $k => $l): ?>
            <a href="<?= site_url('schedule-builder/setup?sesi=' . $k) ?>" <?= $shift === $k ? 'aria-current="page"' : '' ?>><?= icon($k === 'pagi' ? 'sun' : 'moon', 'size-4') ?> <?= $l ?> <span><?= $counts[$k] ?></span></a>
        <?php endforeach ?>
    </nav>
    <p class="mb-3 text-xs text-muted">Tip: kegiatan lengkap (Pembiasaan, MBG, Upacara) juga bisa terisi otomatis lewat <a href="<?= site_url('schedule-builder/upload') ?>">Upload Jadwal (PDF)</a>.</p>
    <datalist id="sb-acts"><?php foreach (ScheduleKit::ACTIVITIES as $a): ?><option value="<?= esc($a, 'attr') ?>"><?php endforeach ?></datalist>
    <form method="post" action="<?= site_url('schedule-builder/slots') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="shift" value="<?= esc($shift, 'attr') ?>">
        <div class="sb-days">
            <?php foreach ($days as $d => $dn): ?>
                <?php if ($d === 6 && empty($slots[6])): continue; endif ?>
                <details class="sb-day" <?= $d === 1 || empty($slots) ? 'open' : '' ?>>
                    <summary><strong><?= $dn ?></strong> <span class="text-xs text-muted"><?= count(array_filter($slots[$d] ?? [], static fn ($s) => $s['kind'] === 'pelajaran')) ?> jam pelajaran · <?= count(array_filter($slots[$d] ?? [], static fn ($s) => $s['kind'] === 'kegiatan')) ?> kegiatan</span></summary>
                    <div class="table-wrap">
                        <table class="table sb-table">
                            <thead><tr><th>Mulai</th><th>Selesai</th><th>Jenis</th><th>Jam ke / Nama kegiatan</th><th class="text-center">Hapus</th></tr></thead>
                            <tbody data-sb-rows="<?= $d ?>">
                            <?php foreach ($slots[$d] ?? [] as $s) { echo $slotRow($d, $s); } ?>
                            <?php echo $slotRow($d, null); // baris kosong untuk tambahan ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm mt-2" data-sb-add="<?= $d ?>"><?= icon('plus', 'size-4') ?> Tambah baris</button>
                </details>
            <?php endforeach ?>
        </div>
        <div class="mt-4 flex justify-end"><button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan struktur sesi <?= ScheduleKit::SHIFTS[$shift] ?></button></div>
    </form>
</section>
<?= $this->endSection() ?>
