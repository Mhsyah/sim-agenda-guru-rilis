<?php

use App\Libraries\Attendance;

/** @var list<array> $roster */
$needClass ??= false;
?>
<?php if (! $roster): ?>
    <p class="rounded-xl border border-dashed border-line p-4 text-sm text-muted" data-att-empty>
        <?= $needClass ? 'Pilih kelas untuk menampilkan daftar siswa.' : 'Belum ada siswa aktif di kelas ini.' ?>
    </p>
<?php else: ?>
    <?php $synced = count(array_filter($roster, static fn ($r) => $r['source'] === 'synced' && $r['status'] !== 'H')); ?>
    <?php if ($synced): ?>
        <p class="alert alert-info mb-3" role="status"><?= icon('info', 'size-5 shrink-0') ?>
            <span><?= $synced ?> siswa sudah tercatat tidak hadir di agenda lain kelas ini pada tanggal yang sama; statusnya terisi otomatis.</span></p>
    <?php endif ?>
    <div class="att-box">
        <ol class="att-rows" data-att-rows>
            <?php foreach ($roster as $i => $s): $sid = $s['id']; ?>
                <li class="att-row<?= trim((string) ($s['notes'] ?? '')) !== '' ? ' has-note' : '' ?>" data-status="<?= esc($s['status'], 'attr') ?>" data-name="<?= esc(mb_strtolower($s['full_name']), 'attr') ?>">
                    <span class="att-no" aria-hidden="true"><?= $i + 1 ?></span>
                    <div class="min-w-0 flex-1">
                        <span class="att-name" id="att-name-<?= $sid ?>" title="<?= esc($s['full_name'], 'attr') ?>"><?= esc($s['full_name']) ?></span>
                        <?php if ($s['source'] === 'synced' && $s['status'] !== 'H'): ?>
                            <span class="att-synced">dari agenda lain hari ini</span>
                        <?php endif ?>
                    </div>
                    <div class="seg" role="radiogroup" aria-labelledby="att-name-<?= $sid ?>">
                        <?php foreach (Attendance::STATUSES as $code => $label): ?>
                            <label class="seg-<?= strtolower($code) ?>" title="<?= esc($label, 'attr') ?>">
                                <input type="radio" name="att[<?= $sid ?>]" value="<?= $code ?>" <?= $s['status'] === $code ? 'checked' : '' ?> data-att-radio>
                                <span aria-hidden="true"><?= $code ?></span><span class="sr-only"><?= esc($label) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                    <button type="button" class="att-note-btn" data-att-note-toggle aria-controls="att-note-<?= $sid ?>" title="Tambah keterangan"><?= icon('pen', 'size-3.5') ?><span class="sr-only">Keterangan untuk <?= esc($s['full_name']) ?></span></button>
                    <div class="att-note">
                        <label class="sr-only" for="att-note-<?= $sid ?>">Keterangan untuk <?= esc($s['full_name']) ?></label>
                        <input class="input h-9 min-h-0 py-1 text-sm" id="att-note-<?= $sid ?>" name="att_note[<?= $sid ?>]" maxlength="200"
                               value="<?= esc((string) ($s['notes'] ?? ''), 'attr') ?>" placeholder="Keterangan (opsional), mis. surat dokter">
                    </div>
                </li>
            <?php endforeach ?>
        </ol>
        <p class="att-none" data-att-none hidden></p>
    </div>
    <div class="att-legend mt-2" aria-label="Keterangan kode kehadiran">
        <span><b class="lg-h">H</b> Hadir</span><span><b class="lg-s">S</b> Sakit</span><span><b class="lg-i">I</b> Izin</span><span><b class="lg-a">A</b> Alpa (tanpa keterangan)</span>
        <span class="text-muted"><?= icon('pen', 'size-3.5') ?> = tambah keterangan per siswa</span>
    </div>
<?php endif ?>
