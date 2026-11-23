<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** @var array $f @var list<array> $rows */
$semNames = $f['semesters'];
$groups   = [];
foreach ($rows as $r) {
    $k = ($r['grade_level'] ? 'Kelas ' . int_to_roman((int) $r['grade_level']) : 'Semua kelas') . ' · ' . ($r['semester_id'] ? ($semNames[$r['semester_id']] ?? 'Semester') : 'Ganjil & Genap');
    $groups[$k][] = $r;
}
?>
<?= view('curriculum/_plan_head', ['f' => $f, 'docTitle' => 'ALUR TUJUAN PEMBELAJARAN (ATP)', 'base' => 'atp', 'title' => $title, 'profile' => $profile, 'period' => $period]) ?>

<?php if (! $rows): ?>
    <div class="card py-12 text-center text-muted">Belum ada TP untuk saringan ini. Tambahkan di menu <a href="<?= site_url('objectives/new') ?>">Tujuan Pembelajaran</a> atau <a href="<?= site_url('curriculum') ?>">unggah CP/TP/ATP/KKTP</a>.</div>
<?php else: ?>
<form method="post" action="<?= site_url('atp/save') ?>" class="grid gap-4">
    <?= csrf_field() ?>
    <?php $no = 0; foreach ($groups as $label => $items): ?>
        <section class="card p-0 sm:p-0 pl-group" aria-label="<?= esc($label, 'attr') ?>">
            <h2 class="pl-group-h"><?= esc($label) ?> <span><?= count($items) ?> TP</span></h2>
            <div class="table-wrap">
                <table class="table pl-table">
                    <thead><tr><th scope="col" class="w-24">Urutan</th><th scope="col" class="w-24">Kode TP</th><th scope="col">Tujuan pembelajaran</th><th scope="col">CP</th><th scope="col" class="num">JP</th><th scope="col" class="no-print"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $r): $no++; ?>
                        <tr>
                            <td>
                                <label class="sr-only" for="atp-<?= $r['id'] ?>">Urutan ATP <?= esc($r['code'] ?? '') ?></label>
                                <input class="input h-9 min-h-0 w-20 text-center no-print" type="number" min="1" max="999" id="atp-<?= $r['id'] ?>" name="atp_order[<?= $r['id'] ?>]" value="<?= esc((string) ($r['atp_order'] ?? ''), 'attr') ?>" placeholder="—">
                                <span class="print-only"><?= esc((string) ($r['atp_order'] ?? $no)) ?></span>
                            </td>
                            <td class="whitespace-nowrap font-semibold"><?= esc($r['code'] ?? '—') ?></td>
                            <td><?= esc($r['description']) ?></td>
                            <td class="text-xs text-muted"><?= esc(implode(', ', $r['cp_codes']) ?: '—') ?></td>
                            <td class="num"><?= $r['estimated_minutes'] !== null ? fmt_num((int) $r['estimated_minutes'] / 40, 1) : '—' ?></td>
                            <td class="no-print"><a class="btn btn-ghost btn-sm" href="<?= site_url('objectives/' . $r['id'] . '/edit') ?>"><?= icon('edit', 'size-4') ?><span class="sr-only">Ubah TP</span></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach ?>
    <div class="action-bar no-print"><div class="flex flex-wrap items-center justify-end gap-2">
        <p class="mr-auto text-sm text-muted">Nomor urut menentukan urutan ATP (kosong = di akhir).</p>
        <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan urutan</button>
    </div></div>
</form>
<?= view('curriculum/_plan_sign', ['kopSet' => $kopSet, 'teacher' => $teacher, 'profile' => $profile]) ?>
<?php endif ?>
<?= $this->endSection() ?>
