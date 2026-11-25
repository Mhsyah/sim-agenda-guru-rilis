<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Agendas;

$className   = $classes[$classId] ?? '—';
$subjectName = preg_replace('/\s*\([^)]*\)$/', '', $subjects[$subjectId] ?? '—');
$tp          = $period['year']['name'] ?? '';
$semName     = $semester['name'] ?? '';
$city        = $profile['school_city'] ?? '';
\App\Libraries\KopSettings::useRequest(); // pilihan cetak sekali pakai dari panel Pengaturan Cetak
$kopSet      = \App\Libraries\KopSettings::get($profile);
$signDate    = fmt_date(\App\Libraries\KopSettings::signDate($kopSet), true);
// Sampul hanya menampilkan jenjang (VII, VIII, …), bukan kelas paralel (VII A).
// Diambil dari kelas yang diajar untuk mapel ini; bila belum diatur, dari kelas yang dipilih.
$gradeOf     = static fn (string $n): string => preg_match('/^(\S+)\s+\S+$/u', trim($n), $m) || preg_match('/^(\d+|[IVXivx]+)[.\-]?[A-Za-z]\d*$/u', trim($n), $m) ? strtoupper($m[1]) : trim($n);
$taughtCls   = array_intersect_key($classes, array_flip(\App\Controllers\Users::teachingMap((int) ($authUser['id'] ?? 0))[$subjectId] ?? []));
$coverGrades = array_values(array_unique(array_map($gradeOf, $taughtCls ?: [$className])));
usort($coverGrades, static fn ($a, $b) => (roman_to_int($a) ?: 99) <=> (roman_to_int($b) ?: 99) ?: strnatcmp($a, $b));
$coverGrades = implode(', ', $coverGrades);
$sign = static function () use ($profile, $authUser, $city, $signDate) { ?>
    <div class="sign">
        <div>
            <p>Mengetahui,</p>
            <p>Kepala Sekolah</p>
            <div class="space"></div>
            <p class="font-bold underline"><?= esc($profile['principal_name'] ?? '……………………………') ?></p>
            <p>NIP. <?= esc($profile['principal_nip'] ?? '……………………') ?></p>
        </div>
        <div>
            <p><?= esc($city ?: '……………') ?>, <?= esc($signDate) ?></p>
            <p>Guru Mata Pelajaran</p>
            <div class="space"></div>
            <p class="font-bold underline"><?= esc($authUser['name'] ?? '……………………………') ?></p>
            <p>NIP. <?= esc($authUser['nip'] ?? '……………………') ?></p>
        </div>
    </div>
<?php };
$has = static fn ($p) => in_array($p, $parts, true);
?>
<nav class="mb-3 flex flex-wrap items-center gap-2 text-sm no-print" aria-label="Navigasi kembali">
    <?php $prev = previous_url(); $backUrl = str_starts_with($prev, site_url()) && ! str_contains($prev, 'reports/journal') ? $prev : site_url('reports'); ?>
    <a href="<?= esc($backUrl, 'attr') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 'size-4') ?> Kembali</a>
    <span class="text-muted">/</span>
    <a href="<?= site_url('reports') ?>">Laporan</a>
    <span class="text-muted">/</span>
    <span class="text-muted">Buku Jurnal</span>
</nav>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4 no-print">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 max-w-2xl text-muted">Format buku agenda sekolah: sampul &amp; kesan kepala sekolah, jadwal mengajar, dan tabel agenda guru. Dicetak landscape dengan kop sekolah. Lengkapi data kop &amp; kepala sekolah di <a href="<?= site_url('settings/profile') ?>">Profil</a>.</p>
    </div>
    <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?> Cetak / simpan PDF</button>
</div>

<form method="get" class="filters card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4 no-print" aria-label="Pilih isi buku jurnal">
    <div class="field min-w-40"><label class="label" for="j-class">Kelas</label>
        <select class="input" id="j-class" name="class_group_id"><?php foreach ($classes as $id => $n): ?><option value="<?= $id ?>" <?= $id === $classId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select></div>
    <div class="field min-w-52"><label class="label" for="j-subject">Mata pelajaran</label>
        <select class="input" id="j-subject" name="subject_id"><?php foreach ($subjects as $id => $n): ?><option value="<?= $id ?>" <?= $id === $subjectId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select></div>
    <div class="field min-w-36"><label class="label" for="j-sem">Semester</label>
        <select class="input" id="j-sem" name="semester_id"><?php foreach ($sems as $id => $n): ?><option value="<?= $id ?>" <?= $id === $semId ? 'selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?></select></div>
    <div class="field min-w-36"><label class="label" for="j-from">Dari tanggal</label><input class="input" type="date" id="j-from" name="from" value="<?= esc((string) $from, 'attr') ?>"></div>
    <div class="field min-w-36"><label class="label" for="j-to">Sampai tanggal</label><input class="input" type="date" id="j-to" name="to" value="<?= esc((string) $to, 'attr') ?>"></div>
    <div class="field min-w-48"><label class="label" for="j-parts">Bagian dicetak</label>
        <select class="input" id="j-parts" name="parts">
            <?php foreach (['cover,schedule,agenda,activity' => 'Lengkap (sampul, jadwal, agenda, kegiatan)', 'agenda' => 'Tabel agenda saja', 'activity' => 'Kegiatan di luar KBM saja', 'cover' => 'Sampul & kesan kepala sekolah', 'schedule' => 'Jadwal mengajar'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= implode(',', $parts) === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach ?>
        </select></div>
    <button class="btn btn-ghost"><?= icon('search', 'size-4') ?> Tampilkan</button>
    <?= view('partials/print_options', ['profile' => $profile, 'withDate' => true, 'withCover' => true], ['saveData' => false]) ?>
</form>

<?php if ($has('cover')): ?>
<!-- Halaman 1: sampul potret (blanko Cover Manajemen Agenda Guru), data guru terisi otomatis -->
<div class="sheet cover-wrap print-cover">
    <?= view('partials/journal_cover', ['profile' => $profile, 'kop' => $kopSet, 'teacher' => [
        'name' => (string) ($authUser['name'] ?? ''), 'nip' => (string) ($authUser['nip'] ?? ''), 'subject' => $subjectName,
        'class' => $coverGrades, 'semester' => $semName, 'year' => $tp,
    ], 'blank' => \App\Libraries\CoverBlank::resolve($authUser, $profile)], ['saveData' => false]) ?>
</div>
<!-- Halaman 2: petunjuk & kesan kepala sekolah (landscape) -->
<section class="paper sheet print-landscape" aria-label="Petunjuk dan kesan kepala sekolah">
    <?= view('partials/kop', ['profile' => $profile]) ?>
    <h2 class="sheet-title">JURNAL AGENDA GURU</h2>
    <div class="meta-grid" style="max-width:36rem;margin:0 auto 1rem">
        <span>Nama Guru</span><span>: <?= esc($authUser['name'] ?? '') ?></span>
        <span>NIP</span><span>: <?= esc($authUser['nip'] ?? '') ?></span>
        <span>Mata Pelajaran</span><span>: <?= esc($subjectName) ?></span>
        <span>Kelas</span><span>: <?= esc($coverGrades) ?></span>
        <span>Semester / T.P.</span><span>: <?= esc($semName) ?> / <?= esc($tp) ?></span>
    </div>
    <p style="font-size:.85rem;text-align:justify">Buku Agenda Guru ini diisi setiap hari mengajar. Bila berhalangan mengajar atau terlambat datang, tetap diisi dan dijelaskan sebab berhalangan atau terlambat datang. Sekali sebulan Agenda Guru ini diserahkan kepada Kepala Sekolah dan ditulis tanggapan oleh Kepala Sekolah.</p>
    <p style="font-size:.85rem;text-align:justify;margin-top:.4rem">Diharapkan guru yang memegang Buku Agenda Guru ini dapat kiranya sebagai pendidik yang ikut membentuk manusia profesional.</p>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="table">
            <caption>Kesan-kesan Kepala Sekolah</caption>
            <thead><tr><th scope="col" style="width:3rem">No.</th><th scope="col" style="width:9rem">Tanggal</th><th scope="col">Kesan-kesan Kepala Sekolah</th><th scope="col" style="width:7rem">Paraf</th></tr></thead>
            <tbody>
            <?php $n = max(8, count($reviews)); for ($i = 0; $i < $n; $i++): $rv = $reviews[$i] ?? null; ?>
                <tr style="height:2.1rem">
                    <td class="text-center"><?= $i + 1 ?>.</td>
                    <td><?= $rv ? fmt_date($rv['review_date']) : '' ?></td>
                    <td><?= $rv ? nl2br(esc($rv['notes'])) : '' ?></td>
                    <td class="text-center"><?= $rv && (int) $rv['is_signed'] ? '✓' : '' ?></td>
                </tr>
            <?php endfor ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif ?>

<?php if ($has('schedule')): ?>
<section class="paper sheet print-landscape" aria-label="Jadwal mengajar">
    <?= view('partials/kop', ['profile' => $profile]) ?>
    <h2 class="sheet-title">JADWAL MENGAJAR</h2>
    <p class="mb-3 text-center" style="font-size:.85rem"><?= esc($authUser['name'] ?? '') ?> · T.P. <?= esc($tp) ?></p>
    <?= view('schedules/grid', ['grid' => $grid]) ?>
</section>
<?php endif ?>

<?php if ($has('agenda')): ?>
<section class="paper sheet print-landscape" aria-label="Agenda guru">
    <?= view('partials/kop', ['profile' => $profile]) ?>
    <h2 class="sheet-title">AGENDA GURU</h2>
    <div class="meta-grid">
        <span>Mata Pelajaran</span><span>: <?= esc($subjectName) ?></span>
        <span>Satuan Pendidikan</span><span>: <?= esc($profile['school_name'] ?? '') ?></span>
        <span>Kelas</span><span>: <?= esc($className === '—' ? $className : strtoupper($gradeOf($className))) ?></span>
        <span>Semester</span><span>: <?= esc($semName) ?></span>
        <span>T.P</span><span>: <?= esc($tp) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <caption>Agenda guru <?= esc($subjectName) ?> kelas <?= esc($className) ?></caption>
            <thead>
            <tr>
                <th rowspan="2" scope="col">Hari/Tgl</th>
                <th rowspan="2" scope="col">Jam Ke</th>
                <th rowspan="2" scope="col">No. KI/KD /TP</th>
                <th scope="col">Pokok Bahasan/Materi</th>
                <th rowspan="2" scope="col">Banyak Pertemuan</th>
                <th rowspan="2" scope="col">Pertemuan Ke</th>
                <th scope="col">Selesai / Belum Selesai</th>
                <th colspan="3" scope="colgroup">Absensi Siswa</th>
                <th rowspan="2" scope="col">KET</th>
            </tr>
            <tr>
                <th scope="col">Tes Formatif / Sumatif / Sub Sumatif</th>
                <th scope="col">Alasan Belum Selesai</th>
                <th scope="col" title="Alpa">a</th><th scope="col" title="Sakit">s</th><th scope="col" title="Izin">i</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($agendas as $a): $ab = $abs[$a['id']] ?? []; $off = $a['status'] === 'batal'; ?>
                <tr>
                    <td class="whitespace-nowrap"><?= fmt_date($a['agenda_date'], false, true) ?></td>
                    <td class="text-center"><?= esc((string) ($a['lesson_hours'] ?? '')) ?></td>
                    <td class="text-center"><?= esc((string) ($a['tp_code'] ?? '')) ?></td>
                    <td class="cell-long"><?= esc($a['topic']) ?><?php if ($a['assessment_kind']): ?><br><em><?= esc(Agendas::ASSESSMENT_KINDS[$a['assessment_kind']] ?? $a['assessment_kind']) ?></em><?php endif ?></td>
                    <td class="text-center"><?= $a['meeting_total'] ? (int) $a['meeting_total'] : '' ?></td>
                    <td class="text-center"><?= $a['meeting_no'] ? (int) $a['meeting_no'] : '' ?></td>
                    <td><?= $off ? '—' : ($a['completion'] === 'belum' ? 'Belum selesai' . ($a['incomplete_reason'] ? '<br><em>' . esc($a['incomplete_reason']) . '</em>' : '') : 'Selesai') ?></td>
                    <td class="text-center"><?= $off ? '' : (int) ($ab['A'] ?? 0) ?></td>
                    <td class="text-center"><?= $off ? '' : (int) ($ab['S'] ?? 0) ?></td>
                    <td class="text-center"><?= $off ? '' : (int) ($ab['I'] ?? 0) ?></td>
                    <td><?= $a['status'] === 'batal' ? '<strong>Berhalangan:</strong> ' : ($a['status'] === 'terlambat' ? '<strong>Terlambat:</strong> ' : '') ?><?= esc((string) ($a['remarks'] ?? '')) ?></td>
                </tr>
            <?php endforeach ?>
            <?php for ($i = count($agendas); $i < 10; $i++): ?>
                <tr style="height:1.8rem"><?php for ($c = 0; $c < 11; $c++): ?><td></td><?php endfor ?></tr>
            <?php endfor ?>
            </tbody>
        </table>
    </div>
    <?php $sign() ?>
</section>
<?php endif ?>

<?php if ($has('activity')): ?>
<section class="paper sheet print-landscape" aria-label="Kegiatan di luar KBM">
    <?= view('partials/kop', ['profile' => $profile]) ?>
    <h2 class="sheet-title">KEGIATAN DI LUAR KEGIATAN BELAJAR MENGAJAR</h2>
    <div class="meta-grid">
        <span>Nama Guru</span><span>: <?= esc($authUser['name'] ?? '') ?></span>
        <span>NIP</span><span>: <?= esc($authUser['nip'] ?? '') ?></span>
        <span>Semester</span><span>: <?= esc($semName) ?></span>
        <span>T.P</span><span>: <?= esc($tp) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <caption>Kegiatan guru di luar KBM</caption>
            <thead>
            <tr>
                <th scope="col" style="width:2.5rem">No.</th>
                <th scope="col">Hari/Tgl</th>
                <th scope="col">Waktu</th>
                <th scope="col">Jenis Kegiatan</th>
                <th scope="col">Nama Kegiatan</th>
                <th scope="col">Tempat</th>
                <th scope="col">Uraian / Hasil</th>
                <th scope="col">Bukti</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $i => $k): ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?>.</td>
                    <td class="whitespace-nowrap"><?= fmt_date($k['activity_date'], false, true) ?></td>
                    <td class="whitespace-nowrap"><?= esc(\App\Controllers\Activities::timeRange($k)) ?></td>
                    <td><?= esc(\App\Controllers\Activities::types()[$k['activity_type']] ?? $k['activity_type']) ?></td>
                    <td><?= esc($k['title']) ?></td>
                    <td><?= esc((string) ($k['location'] ?? '')) ?></td>
                    <td class="cell-long"><?= nl2br(esc((string) ($k['description'] ?? ''))) ?></td>
                    <td class="text-center"><?= ! empty($k['material_file']) ? 'Ada' : '' ?></td>
                </tr>
            <?php endforeach ?>
            <?php for ($i = count($activities); $i < 8; $i++): ?>
                <tr style="height:1.8rem"><?php for ($c = 0; $c < 8; $c++): ?><td></td><?php endfor ?></tr>
            <?php endfor ?>
            </tbody>
        </table>
    </div>
    <?php $sign() ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
