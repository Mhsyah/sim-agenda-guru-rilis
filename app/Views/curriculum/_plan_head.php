<?php
/**
 * Kepala halaman ATP/KKTP: saringan (mapel, kelas, semester), tombol cetak, dan kop + judul cetak.
 *
 * @var array  $f
 * @var string $docTitle  judul dokumen cetak
 * @var string $base      rute halaman (atp|kktp)
 */
$semName = $f['semester'] ? ($f['semesters'][$f['semester']] ?? '') : 'Ganjil & Genap';
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3 no-print">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-1.5 max-w-3xl text-sm text-muted"><?= $base === 'atp'
            ? 'Urutan tujuan pembelajaran per mapel, kelas, dan semester. Ubah nomor urut lalu simpan; isi lengkap TP diatur di menu Tujuan Pembelajaran.'
            : 'Kriteria ketercapaian dan interval nilai tiap TP. Interval dipakai untuk tingkat ketercapaian di Input Nilai dan Rekap.' ?></p>
    </div>
    <button type="button" class="btn btn-ghost" data-print><?= icon('printer', 'size-4') ?> Cetak</button>
</div>

<form method="get" class="card pl-filters mb-4 no-print" aria-label="Saring">
    <div class="field">
        <label class="label" for="pl-subject">Mapel</label>
        <select class="input" id="pl-subject" name="subject_id" data-autosubmit>
            <?php foreach ($f['subjects'] as $id => $n): ?><option value="<?= $id ?>" <?= (int) $f['subject_id'] === (int) $id ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="pl-grade">Kelas</label>
        <select class="input" id="pl-grade" name="grade" data-autosubmit>
            <option value="">Semua kelas</option>
            <?php for ($g = 1; $g <= 12; $g++): ?><option value="<?= $g ?>" <?= $f['grade'] === $g ? 'selected' : '' ?>>Kelas <?= int_to_roman($g) ?></option><?php endfor ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="pl-sem">Semester</label>
        <select class="input" id="pl-sem" name="semester_id" data-autosubmit>
            <option value="">Ganjil &amp; Genap</option>
            <?php foreach ($f['semesters'] as $id => $n): ?><option value="<?= $id ?>" <?= $f['semester'] === (int) $id ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
        </select>
    </div>
</form>

<div class="print-only">
    <?= view('partials/kop', ['profile' => $profile]) ?>
    <h2 class="sheet-title"><?= esc($docTitle) ?></h2>
    <table class="pl-meta">
        <tr><td>Mata pelajaran</td><td>: <?= esc($f['subjects'][$f['subject_id']] ?? '—') ?></td><td>Tahun pelajaran</td><td>: <?= esc($period['year']['name'] ?? '') ?></td></tr>
        <tr><td>Kelas</td><td>: <?= $f['grade'] ? int_to_roman($f['grade']) : 'Semua' ?></td><td>Semester</td><td>: <?= esc($semName) ?></td></tr>
    </table>
</div>
