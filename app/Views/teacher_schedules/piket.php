<?php
/**
 * @var list<array{id: int|string, name: string, teacher_code: ?string}> $teachers
 * @var array<string, array<int, list<array{id: int, name: string}>>> $piket
 */
use App\Libraries\DutyRoster;

$days  = array_slice(day_names(), 0, 6, true);
$slots = 3; // jumlah isian petugas per hari
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted">Guru piket per hari untuk sesi Pagi dan Siang, T.P. <?= esc($period['year']['name'] ?? '') ?>. Kosongkan isian yang tidak dipakai.</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('teacher-schedules') ?>"><?= icon('chevron-left', 'size-4') ?> Kembali ke jadwal</a>
</div>

<form method="post" action="<?= site_url('teacher-schedules/piket') ?>">
    <?= csrf_field() ?>
    <div class="pk-grid">
        <?php foreach (DutyRoster::SHIFTS as $shift => $label): ?>
            <section class="card" aria-labelledby="pk-<?= $shift ?>">
                <h2 id="pk-<?= $shift ?>" class="mb-2 flex items-center gap-2 text-base font-bold text-ink"><span class="ov-tag ov-tag-<?= $shift ?>"><?= icon($shift === 'pagi' ? 'sun' : 'moon', 'size-4') ?></span>Piket <?= $label ?></h2>
                <?php foreach ($days as $d => $dn): $cur = array_column($piket[$shift][$d] ?? [], 'id'); ?>
                    <?php if ($d === 6 && ! $cur): continue; endif // Sabtu hanya bila sudah terisi ?>
                    <fieldset class="pk-day">
                        <legend class="sr-only">Piket <?= $label ?> hari <?= $dn ?></legend>
                        <h3><?= $dn ?></h3>
                        <div class="pk-sel">
                            <?php for ($i = 0; $i < max($slots, count($cur)); $i++): ?>
                                <label class="sr-only" for="pk-<?= $shift ?>-<?= $d ?>-<?= $i ?>">Petugas <?= $i + 1 ?> piket <?= $label ?> <?= $dn ?></label>
                                <select class="input h-10 min-h-0 text-sm" id="pk-<?= $shift ?>-<?= $d ?>-<?= $i ?>" name="piket[<?= $shift ?>][<?= $d ?>][]">
                                    <option value=""><?= $i + 1 ?>. — kosong —</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" <?= ($cur[$i] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= $i + 1 ?>. <?= $t['teacher_code'] ? esc($t['teacher_code']) . ' · ' : '' ?><?= esc($t['name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            <?php endfor ?>
                        </div>
                    </fieldset>
                <?php endforeach ?>
            </section>
        <?php endforeach ?>
    </div>
    <div class="form-actions mt-5 flex justify-end gap-2">
        <a class="btn btn-ghost" href="<?= site_url('teacher-schedules') ?>">Batal</a>
        <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan piket</button>
    </div>
</form>
<?= $this->endSection() ?>
