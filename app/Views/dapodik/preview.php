<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\Dapodik;

/** @var string $kind  @var string $source  @var array $diff  @var string $token */
$fields = Dapodik::FIELDS[$kind];
$c      = $diff['counts'];
$labels = ['new' => ['Baru di Dapodik', 'success'], 'changed' => ['Berbeda', 'warning'], 'missing' => ['Tidak ada di Dapodik', 'neutral'], 'same' => ['Sama', 'info']];
$act    = $c['new'] + $c['changed'];
?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb"><a href="<?= site_url('dapodik') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Sinkron Dapodik</a></nav>
<div class="mb-4">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-1 text-sm text-muted">Sumber: <strong class="text-ink"><?= esc($source) ?></strong>. <strong class="text-ink">Belum ada data yang diubah</strong> — centang baris yang ingin disamakan dengan Dapodik.</p>
</div>

<ul class="imp-sum mb-4" aria-label="Ringkasan perbedaan">
    <li class="is-ok"><b><?= $c['new'] ?></b><span>Baru di Dapodik</span></li>
    <li class="<?= $c['changed'] ? 'is-warn' : '' ?>"><b><?= $c['changed'] ?></b><span>Berbeda (akan disamakan)</span></li>
    <li><b><?= $c['missing'] ?></b><span>Ada di Agenda Guru, tidak ada di Dapodik</span></li>
    <li class="is-info"><b><?= $c['same'] ?></b><span>Sudah sama</span></li>
</ul>
<?php if ($c['missing']): ?>
    <p class="alert alert-info mb-3" role="status"><?= icon('info', 'size-5 shrink-0') ?>
        <span><?= $c['missing'] ?> data hanya ada di Agenda Guru (mis. siswa pindah/lulus, atau ejaan berbeda). Data ini <strong>tidak diubah</strong> — periksa dan perbarui manual bila perlu.</span></p>
<?php endif ?>

<form method="post" action="<?= site_url('dapodik/apply') ?>" class="card p-0 sm:p-0" data-progress="Menerapkan data Dapodik">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <div class="u-tabs" role="group" aria-label="Saring baris" data-imp-tabs>
            <button type="button" aria-pressed="<?= $act ? 'false' : 'true' ?>" data-imp-filter="all">Semua <span><?= count($diff['items']) ?></span></button>
            <button type="button" aria-pressed="<?= $act ? 'true' : 'false' ?>" data-imp-filter="new,changed">Perlu disamakan <span><?= $act ?></span></button>
            <button type="button" aria-pressed="false" data-imp-filter="missing">Tidak ada di Dapodik <span><?= $c['missing'] ?></span></button>
        </div>
        <label class="check text-sm"><input type="checkbox" checked data-dp-all> Centang semua yang perlu disamakan</label>
    </div>
    <div class="table-wrap imp-table">
        <table class="table">
            <caption class="sr-only">Perbedaan data per baris</caption>
            <thead><tr><th scope="col" class="w-10"><span class="sr-only">Pilih</span></th><th scope="col" class="w-36">Hasil</th>
                <?php foreach ($fields as $f => $lbl): ?><th scope="col"><?= esc($lbl) ?></th><?php endforeach ?></tr></thead>
            <tbody data-imp-rows>
            <?php foreach (array_slice($diff['items'], 0, 2000) as $it): [$lbl, $tone] = $labels[$it['status']]; $row = $it['data'] ?? $it['local']; ?>
                <tr data-status="<?= $it['status'] ?>" class="<?= $it['status'] === 'changed' ? 'is-dup' : '' ?>" <?= $act && ! in_array($it['status'], ['new', 'changed'], true) ? 'hidden' : '' ?>>
                    <td><?php if (in_array($it['status'], ['new', 'changed'], true)): ?>
                        <input type="checkbox" class="bulk-check" name="keys[]" value="<?= esc($it['key'], 'attr') ?>" checked aria-label="Terapkan <?= esc($row['name'] ?? '', 'attr') ?>" data-dp-row>
                    <?php endif ?></td>
                    <td><?= badge($lbl, $tone) ?></td>
                    <?php foreach (array_keys($fields) as $f): $ch = $it['changes'][$f] ?? null; ?>
                        <td class="<?= $ch ? 'dp-changed' : '' ?>">
                            <?php if ($ch): ?><s class="text-xs text-muted"><?= esc($ch[0] !== '' ? $ch[0] : '(kosong)') ?></s><br><b><?= esc($ch[1]) ?></b>
                            <?php else: ?><?= esc((string) ($row[$f] ?? '')) ?><?php endif ?>
                        </td>
                    <?php endforeach ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="action-bar sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3">
        <p class="mr-auto text-sm"><?= $act ? '<strong>' . $act . '</strong> baris dapat disamakan.' : 'Semua data sudah sama dengan Dapodik.' ?><?= $kind === 'gtk' && $c['new'] ? ' Guru baru dibuatkan akun (kata sandi awal simguru123).' : '' ?></p>
        <a class="btn btn-ghost" href="<?= site_url('dapodik') ?>">Batal</a>
        <?php if ($act): ?><button class="btn btn-primary" data-confirm="Terapkan baris yang dicentang ke data Agenda Guru?" data-confirm-label="Ya, terapkan"><?= icon('check', 'size-4') ?> Terapkan</button><?php endif ?>
    </div>
</form>
<?= $this->endSection() ?>
