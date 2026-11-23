<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 max-w-2xl text-muted">Bobot berlaku per mapel (untuk satu kelas atau semua kelas) pada <?= esc(($period['year']['name'] ?? '') . ' ' . ($period['semester']['name'] ?? '')) ?>. Total bobot kategori aktif harus tepat 100% agar rekap final dapat dihitung.</p>
    </div>
    <a class="btn btn-primary no-print" href="<?= site_url('grading/new') ?>"><?= icon('plus', 'size-4') ?> Tambah pengaturan</a>
</div>

<?php if (! $rows): ?>
    <div class="card text-center"><p class="font-display text-xl font-bold">Belum ada pengaturan bobot</p><p class="mt-1 text-sm text-muted">Contoh umum: Tugas 20%, UH 30%, ATS 20%, AAS 30% — sesuaikan dengan kebijakan sekolah Anda.</p></div>
<?php else: ?>
    <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($rows as $i => $r): ?>
            <article class="card <?= $i % 2 ? '' : 'card-organic' ?>" aria-labelledby="gc-<?= $r['id'] ?>">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 id="gc-<?= $r['id'] ?>" class="font-display text-lg font-bold"><?= esc($r['name']) ?></h2>
                        <p class="text-sm text-muted"><?= esc($r['subject_name']) ?> · <?= $r['class_name'] ? esc($r['class_name']) : 'Semua kelas' ?></p>
                    </div>
                    <div class="flex gap-1.5">
                        <?= (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif') ?>
                        <?= $r['check']['valid'] ? badge('Total 100%', 'success') : badge('Total ' . fmt_num($r['check']['sum']) . '%', 'warning') ?>
                    </div>
                </div>
                <dl class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($r['weights'] as $code => $w): ?>
                        <div class="rounded-2xl bg-surface-2 px-3 py-2"><dt class="text-xs font-bold text-muted"><?= esc($code) ?></dt><dd class="font-display text-xl font-extrabold"><?= fmt_num($w) ?>%</dd></div>
                    <?php endforeach ?>
                </dl>
                <p class="mt-3 text-xs text-muted">Metode UH: <?= esc(\App\Libraries\GradeCalculator::METHODS[$r['aggregation_method']] ?? $r['aggregation_method']) ?> · Pembulatan tampilan: <?= esc(\App\Libraries\GradeCalculator::ROUNDING[$r['rounding_rule']] ?? $r['rounding_rule']) ?></p>
                <div class="mt-4 flex gap-2 no-print">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('grading/' . $r['id'] . '/edit') ?>"><?= icon('edit', 'size-4') ?> Ubah <span class="sr-only"><?= esc($r['name']) ?></span></a>
                    <form method="post" action="<?= site_url('grading/' . $r['id'] . '/delete') ?>" data-confirm="Hapus pengaturan bobot ini? Rekap untuk cakupan ini tidak dapat dihitung sampai pengaturan baru dibuat." data-confirm-label="Ya, hapus">
                        <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('trash', 'size-4') ?> Hapus <span class="sr-only"><?= esc($r['name']) ?></span></button>
                    </form>
                </div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
