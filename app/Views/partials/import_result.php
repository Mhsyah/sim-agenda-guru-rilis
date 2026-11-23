<?php
/**
 * Ringkasan hasil impor setelah dikonfirmasi.
 *
 * @var array  $result
 * @var string $label   nama jenis data
 */
$dups = (int) ($result['duplicates'] ?? 0);
$bad  = count($result['errors']) - $dups;
?>
<section id="hasil" class="card mb-5" aria-labelledby="res-title" role="status">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="res-title" class="text-base font-bold">Hasil impor <?= esc($label) ?></h2>
        <span class="text-xs text-muted"><?= (int) $result['total'] ?> baris diproses</span>
    </div>
    <ul class="imp-sum mt-3">
        <li class="is-ok"><b><?= (int) $result['created'] ?></b><span>Baru</span></li>
        <li class="is-info"><b><?= (int) $result['updated'] + (int) ($result['restored'] ?? 0) ?></b><span>Diperbarui</span></li>
        <li class="<?= $dups ? 'is-warn' : '' ?>"><b><?= $dups ?></b><span>Duplikat (dilewati)</span></li>
        <li class="<?= $bad ? 'is-bad' : '' ?>"><b><?= $bad ?></b><span>Galat (dilewati)</span></li>
    </ul>
    <?php if (! empty($result['credentials'])): ?>
        <div class="alert alert-warning mt-4" role="alert"><?= icon('shield', 'size-5 shrink-0') ?>
            <div>
                <p class="font-semibold">Kata sandi sementara (hanya ditampilkan sekali). Bagikan secara pribadi dan minta guru menggantinya setelah login.</p>
                <div class="table-wrap mt-2"><table class="table"><caption>Kata sandi sementara</caption>
                    <thead><tr><th scope="col">Email</th><th scope="col">Kata sandi sementara</th></tr></thead>
                    <tbody><?php foreach ($result['credentials'] as [$e, $p]): ?><tr><td><?= esc($e) ?></td><td class="font-mono"><?= esc($p) ?></td></tr><?php endforeach ?></tbody>
                </table></div>
            </div>
        </div>
    <?php endif ?>
    <?php if ($result['errors']): ?>
        <details class="mt-3">
            <summary class="cursor-pointer text-sm font-semibold">Lihat <?= count($result['errors']) ?> baris yang dilewati</summary>
            <div class="table-wrap mt-2"><table class="table"><caption class="sr-only">Baris yang dilewati</caption>
                <thead><tr><th scope="col" class="num">Baris</th><th scope="col">Alasan</th></tr></thead>
                <tbody><?php foreach ($result['errors'] as $e): ?><tr><td class="num"><?= (int) $e['line'] ?></td><td><?= esc($e['reason']) ?></td></tr><?php endforeach ?></tbody>
            </table></div>
        </details>
    <?php endif ?>
</section>
