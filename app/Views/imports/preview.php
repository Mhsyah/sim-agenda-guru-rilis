<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$r       = $result;
$bad     = count($r['errors']);
$ok      = (int) $r['ok'];
$cols    = $meta['columns'];
unset($cols['password']);
$labels  = ['created' => ['Baru', 'success'], 'updated' => ['Sudah ada → diperbarui', 'info'], 'restored' => ['Dipulihkan', 'info'], 'duplicate' => ['Duplikat di file', 'warning'], 'error' => ['Galat', 'danger']];
$dups    = (int) ($r['duplicates'] ?? 0);
$errs    = $bad - $dups;
$base    = $actionBase ?? 'imports';
$tplUrl  = $base === 'curriculum' ? site_url('curriculum/template') : site_url('imports/' . $type . '/template');
$rows    = $r['rows'] ?? [];
$fileErr = ! $rows && $bad; // galat tingkat file (header/kosong)
?>
<nav class="mb-3 text-sm no-print" aria-label="Breadcrumb">
    <a href="<?= esc($back, 'attr') ?>" class="inline-flex items-center gap-1"><?= icon('chevron-left', 'size-4') ?> Kembali</a>
</nav>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-1 text-sm text-muted">File <strong class="text-ink"><?= esc($file) ?></strong> · <?= (int) $r['total'] ?> baris data. <strong class="text-ink">Belum ada data yang disimpan</strong> — periksa hasil di bawah, lalu klik Impor.</p>
    </div>
</div>

<!-- Ringkasan -->
<ul class="imp-sum mb-4" aria-label="Ringkasan pratinjau">
    <li class="is-ok"><b><?= (int) $r['created'] ?></b><span>Baru</span></li>
    <li class="is-info"><b><?= (int) $r['updated'] + (int) ($r['restored'] ?? 0) ?></b><span>Sudah ada → diperbarui</span></li>
    <li class="<?= $dups ? 'is-warn' : '' ?>"><b><?= $dups ?></b><span>Duplikat di file (dilewati)</span></li>
    <li class="<?= $errs ? 'is-bad' : '' ?>"><b><?= $errs ?></b><span>Galat data (dilewati)</span></li>
</ul>
<?php if ($dups || $errs): ?>
    <p class="alert alert-warning mb-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
        <span><?= $dups ? '<strong>' . $dups . ' baris duplikat</strong> (data sama muncul lebih dari sekali di file)' : '' ?><?= $dups && $errs ? ' dan ' : '' ?><?= $errs ? '<strong>' . $errs . ' baris galat</strong> (isian tidak valid)' : '' ?>
        tidak akan disimpan. Lihat kolom Keterangan, perbaiki di file lalu unggah ulang, atau lanjutkan untuk mengimpor baris yang valid saja.</span></p>
<?php endif ?>

<?php foreach (($r['capacity'] ?? []) as [$tone, $msg, $href, $lbl]): ?>
    <p class="alert alert-<?= $tone ?> mb-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
        <span class="flex-1"><?= esc($msg) ?></span><?php if ($href): ?><a class="btn btn-ghost btn-sm shrink-0" href="<?= esc($href, 'attr') ?>"><?= esc($lbl) ?></a><?php endif ?></p>
<?php endforeach ?>
<?php if (! empty($r['ignored'])): ?>
    <p class="alert alert-warning mb-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
        <span>Kolom tidak dikenali dan <strong>diabaikan</strong>: <?= esc(implode(', ', $r['ignored'])) ?>. Kolom yang dipakai: <?= esc(implode(', ', array_keys($meta['columns']))) ?>.</span></p>
<?php endif ?>
<?php if ($type === 'teachers' && $r['created']): ?>
    <p class="alert alert-info mb-3" role="status"><?= icon('info', 'size-5 shrink-0') ?>
        <span>Guru baru tanpa kolom kata sandi akan memakai kata sandi awal <strong><?= \App\Libraries\MasterImporter::DEFAULT_TEACHER_PASSWORD ?></strong>. Minta guru menggantinya setelah login pertama.</span></p>
<?php endif ?>

<?php if ($fileErr): ?>
    <div class="card border-bad/40">
        <p class="font-semibold text-bad">File tidak dapat diimpor</p>
        <ul class="mt-2 list-disc pl-5 text-sm"><?php foreach ($r['errors'] as $e): ?><li><?= esc($e['reason']) ?></li><?php endforeach ?></ul>
        <p class="mt-3 text-sm text-muted">Gunakan template agar urutan & nama kolom sesuai.</p>
        <a class="btn btn-ghost btn-sm mt-3" href="<?= esc($tplUrl, 'attr') ?>"><?= icon('download', 'size-4') ?> Unduh template</a>
    </div>
<?php else: ?>
    <section class="card p-0 sm:p-0" aria-label="Rincian per baris">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
            <div class="u-tabs" role="group" aria-label="Saring baris" data-imp-tabs>
                <button type="button" aria-pressed="<?= $bad ? 'false' : 'true' ?>" data-imp-filter="all">Semua <span><?= count($rows) ?></span></button>
                <button type="button" aria-pressed="<?= $bad ? 'true' : 'false' ?>" data-imp-filter="error,duplicate">Dilewati <span><?= $bad ?></span></button>
                <button type="button" aria-pressed="false" data-imp-filter="duplicate">Duplikat <span><?= $dups ?></span></button>
                <button type="button" aria-pressed="false" data-imp-filter="updated,restored">Sudah ada <span><?= (int) $r['updated'] + (int) ($r['restored'] ?? 0) ?></span></button>
            </div>
            <p class="text-xs text-muted">Baris bermasalah tidak ikut diimpor. Perbaiki di file lalu unggah ulang bila perlu.</p>
        </div>
        <div class="table-wrap imp-table">
            <table class="table">
                <caption>Pratinjau impor per baris</caption>
                <thead><tr><th scope="col" class="w-16">Baris</th><th scope="col" class="w-32">Hasil</th>
                    <?php foreach ($cols as $c => $d): ?><th scope="col"><?= esc($c) ?></th><?php endforeach ?>
                    <th scope="col">Keterangan</th></tr></thead>
                <tbody data-imp-rows>
                <?php foreach (array_slice($rows, 0, 1000) as $row): [$lbl, $tone] = $labels[$row['status']] ?? ['—', 'neutral']; ?>
                    <tr data-status="<?= esc($row['status'], 'attr') ?>" class="<?= $row['status'] === 'error' ? 'is-bad' : ($row['status'] === 'duplicate' ? 'is-dup' : '') ?>" <?= $bad && ! in_array($row['status'], ['error', 'duplicate'], true) ? 'hidden' : '' ?>>
                        <td class="tabular-nums text-muted"><?= (int) $row['line'] ?></td>
                        <td><?= badge($lbl, $tone) ?></td>
                        <?php foreach (array_keys($cols) as $c): ?><td><?= esc((string) ($row['data'][$c] ?? '')) ?></td><?php endforeach ?>
                        <td class="text-xs <?= $row['status'] === 'error' ? 'text-bad' : ($row['status'] === 'duplicate' ? 'text-warn' : 'text-muted') ?>"><?= esc((string) ($row['reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if (count($rows) > 1000): ?><p class="px-4 py-3 text-xs text-muted">Menampilkan 1.000 baris pertama dari <?= count($rows) ?>.</p><?php endif ?>
    </section>
<?php endif ?>

<div class="action-bar no-print mt-5" role="region" aria-label="Konfirmasi impor">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <p class="mr-auto text-sm"><?= $ok ? '<strong>' . $ok . ' baris</strong> siap diimpor' . ($bad ? ', ' . $bad . ' dilewati.' : '.') : 'Tidak ada baris yang bisa diimpor.' ?></p>
        <form method="post" action="<?= site_url($base . '/cancel') ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
            <button class="btn btn-ghost">Batal</button></form>
        <?php if ($ok): ?>
            <form method="post" action="<?= site_url($base . '/confirm') ?>" data-progress="Mengimpor <?= esc(mb_strtolower($meta['title']), 'attr') ?>"
                  <?= $bad ? 'data-confirm="' . esc($bad . ' baris (duplikat/galat) akan dilewati. Lanjutkan impor ' . $ok . ' baris yang valid?', 'attr') . '" data-confirm-label="Ya, impor"' : '' ?>>
                <?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
                <button class="btn btn-primary"><?= icon('upload', 'size-4') ?> Impor <?= $ok ?> baris</button>
            </form>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
