<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php use App\Libraries\GradeCalculator; ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted"><?= esc(($period['year']['name'] ?? '') . ' · Semester ' . ($period['semester']['name'] ?? '')) ?></p>
    </div>
    <?php if ($recap && $recap['rows']): ?>
        <div class="flex gap-2 no-print">
            <a class="btn btn-ghost" href="<?= current_url() . '?' . http_build_query(['class_group_id' => $classId, 'subject_id' => $subjectId, 'format' => 'csv']) ?>"><?= icon('download', 'size-4') ?> Ekspor CSV</a>
            <button type="button" class="btn btn-ghost" data-print><?= icon('printer', 'size-4') ?> Cetak</button>
        </div>
    <?php endif ?>
</div>

<form method="get" class="filters card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4" aria-label="Pilih kelas dan mapel">
    <?php if (($view ?? '') === 'kktp'): ?><input type="hidden" name="view" value="kktp"><?php endif ?>
    <div class="field min-w-44">
        <label class="label" for="r-class">Kelas</label>
        <select class="input" id="r-class" name="class_group_id"><?php foreach ($classes as $id => $n): ?><option value="<?= $id ?>" <?= $id === $classId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select>
    </div>
    <div class="field min-w-56">
        <label class="label" for="r-subject">Mata pelajaran</label>
        <select class="input" id="r-subject" name="subject_id"><?php foreach ($subjects as $id => $n): ?><option value="<?= $id ?>" <?= $id === $subjectId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select>
    </div>
    <button class="btn btn-primary">Tampilkan</button>
</form>

<?php $tabQ = static fn (string $v) => site_url('recap') . '?' . http_build_query(['class_group_id' => $classId, 'subject_id' => $subjectId] + ($v === 'kktp' ? ['view' => 'kktp'] : [])); ?>
<nav class="u-tabs ev-tabs mb-4 no-print" aria-label="Jenis rekap">
    <a href="<?= esc($tabQ('nilai'), 'attr') ?>" <?= ($view ?? 'nilai') === 'nilai' ? 'aria-current="page"' : '' ?>>Nilai akhir</a>
    <a href="<?= esc($tabQ('kktp'), 'attr') ?>" <?= ($view ?? '') === 'kktp' ? 'aria-current="page"' : '' ?>>Ketercapaian KKTP · Semester <?= esc($period['semester']['name'] ?? '') ?></a>
</nav>
<?php if (($view ?? '') === 'kktp' && $kk !== null): ?>
    <?= view('recap/kktp', ['kk' => $kk]) ?>
<?php else: ?>
<?php if (! $recap): ?>
    <div class="card"><p class="text-muted">Pilih kelas dan mata pelajaran. Pastikan kelas, mapel, dan semester aktif sudah dibuat.</p></div>
<?php else: ?>
    <p class="print-only mb-2 font-bold">Rekap nilai <?= esc($classes[$classId] ?? '') ?> · <?= esc($subjects[$subjectId] ?? '') ?></p>
    <section class="card card-organic mb-5" aria-labelledby="formula-title">
        <h2 id="formula-title" class="font-display text-lg font-bold">Formula yang digunakan</h2>
        <?php if (! $recap['config']): ?>
            <div class="alert alert-warning mt-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
                <span>Belum ada pengaturan bobot aktif untuk kelas/mapel ini, sehingga nilai akhir belum dapat dihitung. <a class="no-print" href="<?= site_url('grading/new') ?>">Buat pengaturan bobot</a>.</span></div>
        <?php else: ?>
            <p class="mt-2 font-mono text-sm"><?= esc($recap['formula']) ?></p>
            <ul class="mt-3 grid gap-1 text-sm text-muted">
                <li>Pengaturan: <strong class="text-ink"><?= esc($recap['config']['name']) ?></strong> · <?= esc(GradeCalculator::METHODS[$recap['method']] ?? $recap['method']) ?> · <?= esc(GradeCalculator::ROUNDING[$recap['rule']] ?? '') ?></li>
                <li>Nilai tiap asesmen dinormalisasi ke skala 0–100 terhadap nilai maksimumnya. Nilai kosong tidak dihitung sebagai nol.</li>
                <li><strong class="text-ink">Final</strong>: total bobot = 100% dan semua komponen lengkap. <strong class="text-ink">Sementara</strong>: dihitung dari komponen yang tersedia dengan bobot dinormalisasi.</li>
            </ul>
            <?php if (! $recap['weightCheck']['valid']): ?>
                <div class="alert alert-warning mt-3" role="status"><?= icon('alert', 'size-5 shrink-0') ?>
                    <span>Total bobot aktif <?= fmt_num($recap['weightCheck']['sum']) ?>% (bukan 100%). Rekap final tidak dapat dihitung; semua hasil berlabel “Sementara”.</span></div>
            <?php endif ?>
        <?php endif ?>
        <p class="mt-3 text-sm">Asesmen tercakup:
            <?php foreach ($recap['codes'] as $c): ?>
                <?= badge($c . ': ' . count($recap['byType'][$c] ?? []) . ' asesmen', empty($recap['byType'][$c]) ? 'warning' : 'info') ?>
            <?php endforeach ?>
        </p>
    </section>

    <section class="card p-0 sm:p-0" aria-labelledby="rt">
        <?php if (! $recap['rows']): ?>
            <p class="p-6 text-muted" id="rt">Belum ada siswa aktif di kelas ini.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <caption id="rt">Rekap nilai <?= esc($classes[$classId] ?? '') ?> <?= esc($subjects[$subjectId] ?? '') ?></caption>
                    <thead><tr>
                        <th scope="col" class="num">No</th><th scope="col">Nama siswa</th>
                        <?php foreach ($recap['codes'] as $c): ?><th scope="col" class="num"><?= esc($c) ?><?= isset($recap['weights'][$c]) ? ' (' . fmt_num($recap['weights'][$c]) . '%)' : '' ?></th><?php endforeach ?>
                        <th scope="col" class="num">Nilai akhir</th><th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recap['rows'] as $i => $r): $calc = $r['calc']; ?>
                        <tr>
                            <td class="num"><?= $i + 1 ?></td>
                            <th scope="row" class="!bg-transparent !px-4 !py-3 text-left text-sm font-medium normal-case tracking-normal text-ink"><?= esc($r['student']['full_name']) ?><span class="block text-xs font-normal text-muted"><?= esc($r['student']['nis'] ?? '') ?></span></th>
                            <?php foreach ($recap['codes'] as $c): $comp = $calc['components'][$c] ?? null; ?>
                                <td class="num">
                                    <?php if (! $comp || $comp['value'] === null): ?><span class="text-muted">Belum diinput</span>
                                    <?php else: ?><?= fmt_score($comp['value'], $recap['rule']) ?><?php if (! $comp['complete']): ?><span class="block text-xs text-warn"><?= $comp['graded'] ?>/<?= $comp['total'] ?> terisi</span><?php endif ?>
                                    <?php endif ?>
                                </td>
                            <?php endforeach ?>
                            <td class="num font-display text-base font-bold"><?= $calc['value'] === null ? '<span class="text-muted font-sans text-sm font-normal">—</span>' : fmt_score($calc['value'], $recap['rule']) ?></td>
                            <td><?= match ($calc['status']) {
                                GradeCalculator::STATUS_FINAL     => badge('Final', 'success'),
                                GradeCalculator::STATUS_PROVISION => badge('Sementara', 'warning'),
                                default                           => badge('Belum ada nilai'),
                            } ?><?php if ($calc['missing'] && $calc['status'] !== GradeCalculator::STATUS_EMPTY): ?><span class="block text-xs text-muted">Belum lengkap: <?= esc(implode(', ', $calc['missing'])) ?></span><?php endif ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
