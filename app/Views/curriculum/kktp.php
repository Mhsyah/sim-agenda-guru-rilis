<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** @var array $f @var list<array> $rows */
$semNames = $f['semesters'];
?>
<?= view('curriculum/_plan_head', ['f' => $f, 'docTitle' => 'KRITERIA KETERCAPAIAN TUJUAN PEMBELAJARAN (KKTP)', 'base' => 'kktp', 'title' => $title, 'profile' => $profile, 'period' => $period]) ?>

<?php if (! $rows): ?>
    <div class="card py-12 text-center text-muted">Belum ada TP untuk saringan ini.</div>
<?php else: ?>
<section class="card p-0 sm:p-0 print-landscape">
    <div class="table-wrap">
        <table class="table pl-table">
            <thead><tr>
                <th scope="col" class="w-16">ATP</th><th scope="col" class="w-24">Kode TP</th><th scope="col">Tujuan pembelajaran</th>
                <th scope="col">Kriteria ketercapaian</th><th scope="col">Interval nilai</th><th scope="col" class="w-28">Kelas · Semester</th>
                <th scope="col" class="no-print"><span class="sr-only">Aksi</span></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="num"><?= esc((string) ($r['atp_order'] ?? '—')) ?></td>
                    <td class="whitespace-nowrap font-semibold"><?= esc($r['code'] ?? '—') ?></td>
                    <td><?= esc($r['description']) ?></td>
                    <td><?= ($r['kktp_criteria'] ?? '') !== '' ? esc($r['kktp_criteria']) : '<span class="text-muted no-print">Belum diisi</span>' ?></td>
                    <td><ul class="pl-bands"><?php foreach ($r['bands'] as $b): ?><li><?= badge($b['min'] . '–' . $b['max'] . ' ' . $b['label'], $b['tone']) ?></li><?php endforeach ?></ul>
                        <?php if (($r['kktp_intervals'] ?? '') === '' || $r['kktp_intervals'] === null): ?><small class="text-muted no-print">bawaan</small><?php endif ?></td>
                    <td class="text-xs"><?= $r['grade_level'] ? 'Kelas ' . int_to_roman((int) $r['grade_level']) : 'Semua' ?><br><?= $r['semester_id'] ? esc($semNames[$r['semester_id']] ?? '') : 'Ganjil & Genap' ?></td>
                    <td class="no-print"><a class="btn btn-ghost btn-sm" href="<?= site_url('objectives/' . $r['id'] . '/edit') ?>"><?= icon('edit', 'size-4') ?> Ubah</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?= view('curriculum/_plan_sign', ['kopSet' => $kopSet, 'teacher' => $teacher, 'profile' => $profile]) ?>
<?php endif ?>
<?= $this->endSection() ?>
