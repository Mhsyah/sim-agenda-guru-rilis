<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Agendas;
use App\Libraries\Attendance;

$first = explode(' ', trim(preg_replace('/[,.].*$/', '', $authUser['name'])))[0];
$statusBadge = static fn (string $s) => match ($s) {
    'selesai'   => '<span class="badge badge-success badge-dot">Terlaksana</span>',
    'terlambat' => '<span class="badge badge-warning badge-dot">Guru terlambat</span>',
    'batal'     => '<span class="badge badge-danger badge-dot">Berhalangan</span>',
    default     => '<span class="badge badge-dot">Draf</span>',
};
?>
<!-- Judul -->
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-muted"><?= fmt_date($today, true, true) ?></p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-[1.75rem]"><?= esc(greeting()) ?>, <span class="marker"><?= esc($first) ?></span></h1>
        <p class="mt-1 text-sm text-muted">Ringkasan mengajar Anda · T.P. <?= esc($period['year']['name'] ?? '—') ?> semester <?= esc($period['semester']['name'] ?? '—') ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-ghost" href="<?= site_url('reports/journal') ?>"><?= icon('printer', 'size-4') ?> Cetak jurnal</a>
        <a class="btn btn-primary" href="<?= site_url('agendas/new') ?>"><?= icon('pen', 'size-4') ?> Tulis agenda</a>
    </div>
</div>

<?= view('partials/school_banner', ['profile' => $profile, 'period' => $period, 'editUrl' => null, 'eyebrow' => 'Sekolah Anda']) ?>

<?php if (! empty($holiday)): ?>
    <p class="alert alert-info mb-5" role="status"><?= icon('calendar', 'size-5 shrink-0') ?> Hari ini tercatat sebagai <strong><?= esc($holiday['title']) ?></strong> di kalender pendidikan.</p>
<?php endif ?>

<?php if (! empty($dutyToday)): ?>
    <p class="alert alert-warning mb-5" role="status"><?= icon('shield', 'size-5 shrink-0') ?> Hari ini Anda bertugas <strong>piket <?= esc(implode(' & ', array_map(static fn ($p) => \App\Libraries\DutyRoster::SHIFTS[$p['shift']] ?? $p['shift'], $dutyToday))) ?></strong>.</p>
<?php endif ?>

<?php if ($setup): ?>
    <section class="card mb-6" aria-labelledby="setup-title">
        <h2 id="setup-title" class="text-[0.95rem] font-semibold tracking-tight">Langkah awal</h2>
        <p class="mt-1 text-sm text-muted">Lengkapi hal berikut agar semua fitur siap dipakai.</p>
        <ol class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($setup as $i => [$label, $url]): ?>
                <li><a class="btn btn-ghost btn-sm" href="<?= $url ?>"><span class="grid size-5 place-items-center rounded-md bg-primary text-[0.65rem] font-bold text-primary-ink"><?= $i + 1 ?></span><?= esc($label) ?></a></li>
            <?php endforeach ?>
        </ol>
    </section>
<?php endif ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]">
    <!-- ======================= Kolom utama ======================= -->
    <div class="min-w-0 space-y-6">
        <?php if ($stats): ?>
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4" aria-label="Ringkasan">
                <?php foreach ($stats as $s): ?><li><?= view('partials/stat_card', ['s' => $s]) ?></li><?php endforeach ?>
            </ul>
        <?php endif ?>

        <?php if ($weeks): ?>
            <?php
            $bars = array_map(static fn ($w) => [
                'label' => $w['label'],
                'value' => $w['agendas'],
                'tip'   => 'Minggu ' . $w['label'] . ': ' . $w['agendas'] . ' agenda' . ($w['total'] ? ' · hadir ' . fmt_num($w['present'] / $w['total'] * 100, 1) . '%' : ''),
            ], $weeks);
            $sum = array_sum(array_column($weeks, 'agendas'));
            ?>
            <section class="card" aria-labelledby="chart-title">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="chart-title" class="text-[0.95rem] font-semibold tracking-tight">Agenda mengajar 8 minggu terakhir</h2>
                        <p class="mt-0.5 text-sm text-muted"><?= $sum ?> agenda tercatat · arahkan kursor ke batang untuk detail kehadiran</p>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('reports/agenda') ?>">Laporan agenda</a>
                </div>
                <?= view('partials/barchart', ['bars' => $bars, 'unit' => 'Jumlah agenda', 'caption' => 'Jumlah agenda per minggu',
                    'aria' => 'Grafik batang jumlah agenda per minggu selama 8 minggu terakhir, total ' . $sum . ' agenda. Data lengkap tersedia dalam tabel untuk pembaca layar.']) ?>
            </section>
        <?php endif ?>

        <section class="card overflow-hidden p-0 sm:p-0" aria-labelledby="recent-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                <div>
                    <h2 id="recent-title" class="text-[0.95rem] font-semibold tracking-tight">Agenda terbaru</h2>
                    <p class="mt-0.5 text-sm text-muted">Jurnal mengajar terakhir Anda</p>
                </div>
                <a href="<?= site_url('agendas') ?>" class="text-xs font-semibold">Lihat semua</a>
            </div>
            <?php if (empty($recent)): ?>
                <p class="px-6 py-10 text-center text-sm text-muted">Belum ada agenda. <a href="<?= site_url('agendas/new') ?>">Tulis agenda pertama</a>.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table min-w-[44rem]">
                        <caption>Agenda terbaru</caption>
                        <thead><tr>
                            <th scope="col" class="w-36">Tanggal</th><th scope="col" class="w-40">Kelas · Mapel</th><th scope="col">Pokok bahasan</th>
                            <th scope="col" class="w-28 text-right">Kehadiran</th><th scope="col" class="w-36 text-right">Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): $tot = (int) $r['att_total']; $pr = (int) $r['att_present']; ?>
                            <tr>
                                <td class="whitespace-nowrap text-muted"><?= fmt_date($r['agenda_date'], false, true) ?><?= $r['lesson_hours'] ? '<span class="block text-xs">Jam ' . esc($r['lesson_hours']) . '</span>' : '' ?></td>
                                <td class="whitespace-nowrap"><span class="font-medium"><?= esc($r['class_name']) ?></span><span class="block text-xs text-muted"><?= esc($r['subject_name']) ?></span></td>
                                <td><a class="font-medium text-ink" href="<?= site_url('agendas/' . $r['id'] . '/edit') ?>"><?= esc($r['topic']) ?></a></td>
                                <td class="whitespace-nowrap text-right tabular-nums"><?= $tot ? $pr . '/' . $tot : '<span class="text-muted">—</span>' ?></td>
                                <td class="whitespace-nowrap text-right"><?= $statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 sm:px-6">
                    <p class="text-sm text-muted">Menampilkan <strong class="text-ink"><?= count($recent) ?></strong> dari <strong class="text-ink"><?= (int) $recentTotal ?></strong> agenda</p>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('agendas') ?>">Semua agenda <?= icon('chevron-right', 'size-4') ?></a>
                </div>
            <?php endif ?>
        </section>
    </div>

    <!-- ======================= Kolom samping ======================= -->
    <aside class="space-y-6" aria-label="Hari ini">
        <section class="card" aria-labelledby="today-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="today-title" class="text-[0.95rem] font-semibold tracking-tight">Jadwal hari ini</h2>
                <a class="text-xs font-semibold" href="<?= site_url('schedules') ?>">Jadwal</a>
            </div>
            <?php if (! $todaySchedule): ?>
                <p class="text-sm text-muted">Tidak ada jadwal hari <?= day_names()[(int) date('N')] ?>.</p>
            <?php else: ?>
                <ol class="space-y-2">
                    <?php foreach ($todaySchedule as $s): ?>
                        <li class="flex items-center gap-3 rounded-xl border border-line p-2.5">
                            <span class="w-12 shrink-0 text-center font-mono text-xs tabular-nums text-muted"><?= fmt_time($s['start_time']) ?></span>
                            <span class="min-w-0 flex-1 text-sm"><span class="block truncate font-semibold"><?= esc($s['class_name']) ?></span><span class="block truncate text-xs text-muted"><?= esc($s['subject_name']) ?></span></span>
                            <?php if ($s['done']): ?>
                                <span class="badge badge-success badge-dot">Ditulis</span>
                            <?php else: ?>
                                <a class="btn btn-primary btn-sm" href="<?= site_url('agendas/new?' . http_build_query(['class_group_id' => $s['class_group_id'], 'subject_id' => $s['subject_id'], 'agenda_date' => $today])) ?>">Tulis<span class="sr-only"> agenda <?= esc($s['class_name']) ?></span></a>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ol>
            <?php endif ?>
        </section>

        <section class="card" aria-labelledby="act-title">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="act-title" class="text-[0.95rem] font-semibold tracking-tight">Riwayat aktivitas</h2>
                <a href="<?= site_url('activity') ?>" class="text-xs font-semibold">Lihat semua</a>
            </div>
            <?php if (empty($activity)): ?>
                <p class="text-sm text-muted">Belum ada aktivitas tercatat.</p>
            <?php else: ?>
                <ol class="act-feed">
                    <?php foreach ($activity as $a): ?>
                        <li class="act-item is-<?= esc($a['tone'], 'attr') ?>">
                            <span class="act-dot" aria-hidden="true"><?= icon($a['icon'], 'size-3.5') ?></span>
                            <div class="min-w-0">
                                <p class="act-text"><?= $a['url'] ? '<a href="' . esc($a['url'], 'attr') . '">' . esc($a['text']) . '</a>' : esc($a['text']) ?></p>
                                <?php if ($a['detail'] !== ''): ?><p class="act-detail"><?= esc($a['detail']) ?></p><?php endif ?>
                                <time class="act-time" title="<?= esc($a['at'], 'attr') ?>"><?= esc($a['ago']) ?></time>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ol>
            <?php endif ?>
        </section>

        <section class="card" aria-labelledby="abs-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="abs-title" class="text-[0.95rem] font-semibold tracking-tight">Tidak hadir hari ini</h2>
                <a class="text-xs font-semibold" href="<?= site_url('reports/attendance') ?>">Rekap</a>
            </div>
            <?php if (! $absentToday): ?>
                <p class="text-sm text-muted">Belum ada siswa tercatat tidak hadir di kelas Anda.</p>
            <?php else: ?>
                <ul class="space-y-2.5">
                    <?php foreach ($absentToday as $a): ?>
                        <li class="flex items-start gap-2.5 text-sm">
                            <span class="badge badge-<?= Attendance::TONES[$a['status']] ?> mt-0.5"><?= $a['status'] ?></span>
                            <span class="min-w-0"><span class="font-medium"><?= esc($a['full_name']) ?></span>
                                <span class="block text-xs text-muted"><?= esc($a['class_name']) ?> · <?= esc(Attendance::STATUSES[$a['status']]) ?><?= $a['notes'] ? ' — ' . esc($a['notes']) : '' ?></span></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="card" aria-labelledby="inc-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="inc-title" class="text-[0.95rem] font-semibold tracking-tight">Nilai belum lengkap</h2>
                <a class="text-xs font-semibold" href="<?= site_url('reports/incomplete') ?>">Laporan</a>
            </div>
            <?php if (! $incomplete && ! $invalidConfigs): ?>
                <p class="flex items-center gap-2 text-sm text-ok"><?= icon('check', 'size-4') ?> Semua asesmen sudah lengkap.</p>
            <?php endif ?>
            <ul class="space-y-2">
                <?php foreach ($incomplete as $a): $left = (int) $a['enrolled'] - (int) $a['graded']; ?>
                    <li><a class="flex items-center gap-2.5 rounded-xl border border-line p-2.5 text-sm text-ink no-underline transition-all duration-200 hover:bg-surface-2" href="<?= site_url('assessments/' . $a['id'] . '/scores') ?>">
                        <span class="badge badge-info"><?= esc($a['code']) ?></span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium"><?= esc($a['title']) ?></span><span class="block text-xs text-muted"><?= esc($a['class_name']) ?> · <?= $left ?> siswa belum dinilai</span></span>
                        <?= icon('chevron-right', 'size-4 text-muted') ?></a></li>
                <?php endforeach ?>
                <?php foreach ($invalidConfigs as $c): ?>
                    <li class="alert alert-warning p-2.5"><?= icon('alert', 'size-4 shrink-0') ?>
                        <span>Bobot “<?= esc($c['name']) ?>” = <?= fmt_num($c['sum']) ?>%. <a href="<?= site_url('grading/' . $c['id'] . '/edit') ?>">Perbaiki</a></span></li>
                <?php endforeach ?>
            </ul>
        </section>

        <section class="card" aria-labelledby="up-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="up-title" class="text-[0.95rem] font-semibold tracking-tight">Kalender terdekat</h2>
                <a class="text-xs font-semibold" href="<?= site_url('calendar') ?>">Kalender</a>
            </div>
            <?php if (! $upcoming): ?>
                <p class="text-sm text-muted">Tidak ada kegiatan dalam 3 minggu ke depan.</p>
            <?php else: ?>
                <ol class="space-y-3">
                    <?php foreach ($upcoming as $e): ?>
                        <li class="flex gap-3 text-sm">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full <?= $e['event_type'] === 'libur' ? 'bg-rose-500' : ($e['event_type'] === 'ujian' ? 'bg-amber-500' : 'bg-indigo-500') ?>" aria-hidden="true"></span>
                            <span class="min-w-0"><span class="font-medium"><?= esc($e['title']) ?></span><span class="block text-xs text-muted"><?= fmt_date($e['start_date']) ?> · <?= esc(event_types()[$e['event_type']] ?? $e['event_type']) ?></span></span>
                        </li>
                    <?php endforeach ?>
                </ol>
            <?php endif ?>
        </section>
    </aside>
</div>
<?= $this->endSection() ?>
