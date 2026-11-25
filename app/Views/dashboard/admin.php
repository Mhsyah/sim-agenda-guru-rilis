<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$age     = $sys['lastBackupAge'];
$pct     = (int) round($done / max(1, count($setup)) * 100);
$dirsBad = count(array_filter($sys['dirs'], static fn ($d) => ! $d[1]));
$bytes   = static function ($b) {
    if (! $b) {
        return '—';
    }
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
        if ($b < 1024) {
            return fmt_num($b, $u === 'B' ? 0 : 1) . ' ' . $u;
        }
        $b /= 1024;
    }

    return fmt_num($b, 1) . ' PB';
};
$cur  = $weeks[count($weeks) - 1] ?? ['agendas' => 0];
$prev = $weeks[count($weeks) - 2] ?? ['agendas' => 0];
$icons = ['users', 'users', 'grid', 'book'];
$notes = [
    $neverLoggedIn ? $neverLoggedIn . ' belum pernah login' : 'semua sudah pernah login',
    $noClass ? $noClass . ' belum punya kelas' : 'semua sudah ditempatkan',
    'T.P. ' . ($period['year']['name'] ?? '—'),
    'aktif digunakan',
];
$statCards = [];
foreach ($tiles as $i => [$label, $value, $ic, $url]) {
    $statCards[] = ['label' => $label, 'value' => number_format((int) $value, 0, ',', '.'), 'icon' => $icons[$i] ?? $ic, 'url' => $url, 'note' => $notes[$i] ?? '',
        'warn' => ($i === 1 && $noClass) || ($i === 0 && $neverLoggedIn)];
}
$statCards[] = ['label' => 'Agenda minggu ini', 'value' => (string) $cur['agendas'], 'icon' => 'pen', 'note' => 'seluruh guru · vs ' . $prev['agendas'] . ' minggu lalu',
    'trend' => \App\Controllers\Dashboard::trend($cur['agendas'], $prev['agendas'], false)];
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl"><?= esc(greeting()) ?>, <span class="marker">Admin</span></h1>
        <p class="text-sm text-muted"><?= fmt_date(date('Y-m-d'), true, true) ?> · <?= esc($profile['school_name'] ?? 'Sekolah') ?> · T.P. <?= esc($period['year']['name'] ?? '—') ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-ghost btn-sm" href="<?= site_url('users/new') ?>"><?= icon('plus', 'size-4') ?> Tambah guru</a>
        <a class="btn btn-primary btn-sm" href="<?= site_url('imports') ?>"><?= icon('upload', 'size-4') ?> Impor data</a>
    </div>
</div>

<!-- Ringkasan angka (satu baris) -->
<ul class="ad-kpis mb-5" aria-label="Ringkasan sekolah">
    <?php foreach ($statCards as $s): ?>
        <li>
            <<?= ! empty($s['url']) ? 'a href="' . esc($s['url'], 'attr') . '"' : 'div' ?> class="ad-kpi<?= ! empty($s['warn']) ? ' is-warn' : '' ?>">
                <span class="ad-kpi-ico"><?= icon($s['icon'], 'size-4') ?></span>
                <span class="min-w-0"><small><?= esc($s['label']) ?></small><b><?= esc($s['value']) ?></b><em><?= esc($s['note']) ?></em></span>
            </<?= ! empty($s['url']) ? 'a' : 'div' ?>>
        </li>
    <?php endforeach ?>
    <li>
        <a href="<?= site_url('settings/backup') ?>" class="ad-kpi<?= $age === null || $age > 7 ? ' is-warn' : '' ?>">
            <span class="ad-kpi-ico"><?= icon('database', 'size-4') ?></span>
            <span class="min-w-0"><small>Backup terakhir</small><b><?= $age === null ? 'Belum ada' : ($age === 0 ? 'Hari ini' : $age . ' hari lalu') ?></b><em><?= (int) $sys['backupCount'] ?> file tersimpan</em></span>
        </a>
    </li>
</ul>

<?php
$bars = array_map(static fn ($w) => ['label' => $w['label'], 'value' => $w['agendas'],
    'tip' => 'Minggu ' . $w['label'] . ': ' . $w['agendas'] . ' agenda' . ($w['total'] ? ' · hadir ' . fmt_num($w['present'] / $w['total'] * 100, 1) . '%' : '')], $weeks);
$sum = array_sum(array_column($weeks, 'agendas'));
$agendaChart = '<p class="mb-3 text-sm text-muted">' . $sum . ' agenda tercatat dari seluruh guru. Hanya jumlah yang ditampilkan; isi agenda tetap milik masing-masing guru.</p>'
    . view('partials/barchart', ['bars' => $bars, 'unit' => 'Jumlah agenda', 'caption' => 'Jumlah agenda seluruh guru per minggu',
        'aria' => 'Grafik batang jumlah agenda seluruh guru per minggu selama 8 minggu terakhir, total ' . $sum . ' agenda.']);
?>
<?php if (! empty($quality)): ?><?= view('dashboard/quality', ['q' => $quality, 'trend' => $qtrend, 'agendaChart' => $agendaChart]) ?><?php endif ?>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <section class="card overflow-hidden p-0 sm:p-0" aria-labelledby="act-title">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <div>
                <h2 id="act-title" class="text-[0.95rem] font-semibold tracking-tight">Aktivitas guru minggu ini</h2>
                <p class="text-xs text-muted">Diurutkan dari agenda terbanyak</p>
            </div>
            <span class="flex gap-3"><a href="<?= site_url('teacher-activities') ?>" class="text-xs font-semibold">Rekap kegiatan</a><a href="<?= site_url('users?role=teacher') ?>" class="text-xs font-semibold">Data guru</a></span>
        </div>
        <?php if (! $activity): ?>
            <p class="px-6 py-10 text-center text-sm text-muted">Belum ada akun guru aktif. <a href="<?= site_url('imports#teachers') ?>">Impor data guru</a>.</p>
        <?php else:
            $row = static function (array $a): string {
                $n = (int) $a['week_agendas'];

                return '<tr><td><div class="flex items-center gap-2.5"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-surface-2 text-[0.7rem] font-bold" aria-hidden="true">' . esc(mb_strtoupper(mb_substr($a['name'], 0, 1))) . '</span>'
                    . '<span class="truncate font-medium">' . esc($a['name']) . '</span></div></td>'
                    . '<td class="whitespace-nowrap text-xs text-muted">' . ($a['last_login_at'] ? fmt_date(substr($a['last_login_at'], 0, 10)) . ', ' . substr($a['last_login_at'], 11, 5) : 'Belum pernah') . '</td>'
                    . '<td class="text-right font-semibold tabular-nums">' . $n . '</td>'
                    . '<td class="text-right tabular-nums">' . ((int) ($a['week_acts'] ?? 0) ?: '<span class="text-muted">0</span>') . '</td>'
                    . '<td class="text-right">' . ($n > 0 ? '<span class="badge badge-success badge-dot">Aktif mengisi</span>' : ($a['last_login_at'] ? '<span class="badge badge-warning badge-dot">Belum mengisi</span>' : '<span class="badge badge-danger badge-dot">Belum login</span>')) . '</td></tr>';
            };
            $top = array_slice($activity, 0, 8);
            $rest = array_slice($activity, 8); ?>
            <div class="overflow-x-auto">
                <table class="table ad-table min-w-[34rem]">
                    <caption class="sr-only">Aktivitas guru minggu ini</caption>
                    <thead><tr><th scope="col">Guru</th><th scope="col" class="w-40">Login terakhir</th><th scope="col" class="w-20 text-right">Agenda</th><th scope="col" class="w-24 text-right" title="Kegiatan di luar KBM">Kegiatan</th><th scope="col" class="w-36 text-right">Status</th></tr></thead>
                    <tbody><?php foreach ($top as $a) { echo $row($a); } ?></tbody>
                    <?php if ($rest): ?><tbody class="ad-more" id="ad-more" hidden><?php foreach ($rest as $a) { echo $row($a); } ?></tbody><?php endif ?>
                </table>
            </div>
            <?php if ($rest): ?>
                <button type="button" class="ad-toggle" data-ad-more aria-controls="ad-more" aria-expanded="false"><?= icon('chevron-down', 'size-4') ?> <span>Tampilkan <?= count($rest) ?> guru lainnya</span></button>
            <?php endif ?>
        <?php endif ?>
    </section>

    <aside class="card" aria-label="Kelengkapan data dan sistem">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-[0.95rem] font-semibold tracking-tight">Kelengkapan data</h2>
            <span class="font-bold tabular-nums"><?= $pct ?>%</span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Kelengkapan data induk">
            <div class="h-full rounded-full bg-accent" style="width: <?= $pct ?>%"></div>
        </div>
        <?php $todo = array_values(array_filter($setup, static fn ($x) => ! $x[1])); ?>
        <?php if ($todo): ?>
            <ul class="mt-3 space-y-0.5 text-sm">
                <?php foreach ($todo as [$label, $ok, $url]): ?>
                    <li><a href="<?= $url ?>" class="flex items-center gap-2 rounded-lg px-2 py-1 text-ink no-underline hover:bg-surface-2"><span class="grid size-4 shrink-0 place-items-center rounded-full border border-line text-muted"><?= icon('plus', 'size-3') ?></span><?= esc($label) ?></a></li>
                <?php endforeach ?>
            </ul>
        <?php else: ?>
            <p class="mt-2 flex items-center gap-1.5 text-xs text-ok"><?= icon('check', 'size-4') ?> Semua data induk lengkap.</p>
        <?php endif ?>

        <div class="mt-4 flex items-center justify-between border-t border-line pt-3">
            <h2 class="text-[0.95rem] font-semibold tracking-tight">Kesehatan sistem</h2>
            <a class="text-xs font-semibold" href="<?= site_url('system-status') ?>">Detail</a>
        </div>
        <ul class="mt-2 space-y-1.5 text-sm">
            <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-2 text-muted"><?= icon('shield', 'size-4') ?> Login gagal 7 hari</span><span class="badge <?= $sys['blocked7'] ? 'badge-warning' : 'badge-success' ?>"><?= (int) $sys['failed7'] ?></span></li>
            <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-2 text-muted"><?= icon('monitor', 'size-4') ?> Ruang disk</span><span class="badge <?= $dirsBad ? 'badge-danger' : 'badge-success' ?>"><?= $bytes($sys['free']) ?></span></li>
            <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-2 text-muted"><?= icon('alert', 'size-4') ?> Galat di log</span><span class="badge <?= $sys['errors'] ? 'badge-warning' : 'badge-success' ?>"><?= count($sys['errors']) ?></span></li>
        </ul>
        <form method="post" action="<?= site_url('settings/backup') ?>" class="mt-3" data-progress="Membuat backup"><?= csrf_field() ?>
            <button class="btn btn-primary btn-sm w-full"><?= icon('database', 'size-4') ?> Backup sekarang</button></form>
    </aside>
</div>
<?= $this->endSection() ?>
