<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$bytes = static function ($b) {
    if ($b === null || $b === false) {
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
$tz    = new DateTimeZone($profile['timezone'] ?? 'Asia/Jakarta');
$local = static function (?string $utc) use ($tz) {
    if (! $utc) {
        return '—';
    }
    $d = new DateTime($utc, new DateTimeZone('UTC'));

    return fmt_date($d->setTimezone($tz)->format('Y-m-d')) . ' ' . $d->format('H:i');
};
$tableNames = [
    'users' => 'Akun', 'school_profiles' => 'Profil sekolah', 'academic_years' => 'Tahun ajaran', 'semesters' => 'Semester',
    'user_subjects' => 'Mapel guru', 'user_teaching' => 'Pembagian kelas', 'duty_rosters' => 'Jadwal piket', 'subject_loads' => 'Beban JP',
    'time_slots' => 'Struktur jam', 'notification_reads' => 'Notifikasi dibaca',
    'subjects' => 'Mapel', 'class_groups' => 'Kelas', 'students' => 'Siswa', 'student_enrollments' => 'Penempatan kelas',
    'learning_outcomes' => 'CP', 'learning_objectives' => 'TP', 'outcome_objectives' => 'Relasi CP–TP',
    'school_calendar_events' => 'Kegiatan kalender', 'teaching_schedules' => 'Jadwal', 'teaching_agendas' => 'Agenda',
    'attendances' => 'Daftar hadir', 'assessment_types' => 'Jenis asesmen', 'assessments' => 'Asesmen',
    'grading_configs' => 'Pengaturan bobot', 'grading_config_items' => 'Item bobot', 'assessment_scores' => 'Nilai', 'principal_reviews' => 'Kesan kepala sekolah', 'audit_logs' => 'Log audit',
];
$srv      = array_column(array_map(static fn ($r) => [$r[0], $r[1]], $server), 1, 0);
$badDirs  = array_values(array_filter($dirs, static fn ($d) => ! $d[1]));
// Hanya hal yang perlu ditindaklanjuti
$attention = [];
if ($lastBackupAge === null) {
    $attention[] = ['danger', 'Belum ada backup data.', site_url('settings/backup'), 'Buat backup'];
} elseif ($lastBackupAge > 7) {
    $attention[] = ['warning', 'Backup terakhir ' . $lastBackupAge . ' hari lalu.', site_url('settings/backup'), 'Backup'];
}
if ($badDirs) {
    $attention[] = ['danger', 'Folder tidak dapat ditulis: ' . implode(', ', array_column($badDirs, 0)) . '.', null, ''];
}
if ($errors) {
    $attention[] = ['warning', count($errors) . ' galat tercatat di log terbaru.', '#sys-err', 'Lihat'];
}
$dpSet  = \App\Libraries\Dapodik::settings();
$dpDiff = 0;
foreach ($dpSet['last'] as $dpL) {
    $dpDiff += (int) ($dpL['counts']['new'] ?? 0) + (int) ($dpL['counts']['changed'] ?? 0);
}
if ($dpDiff) {
    $attention[] = ['warning', $dpDiff . ' data berbeda dengan Dapodik pada pengecekan terakhir.', site_url('dapodik'), 'Sinkron'];
}
$migPending = \App\Controllers\SystemStatus::pendingMigrations();
$integ   = \App\Libraries\Integrity::check();
$intDiff = $integ['active'] ? count($integ['changed']) + count($integ['missing']) + count($integ['added']) : 0;
if ($intDiff) {
    $attention[] = ['danger', $intDiff . ' file aplikasi berbeda dari paket rilis resmi (diubah/ditambah/dihapus).', '#ss-int', 'Periksa'];
}
if ($blocked7) {
    $attention[] = ['warning', 'Login dikunci ' . $blocked7 . ' kali dalam 7 hari (percobaan sandi salah berulang).', site_url('settings/audit?entity_type=user'), 'Log'];
}
if (ENVIRONMENT !== 'production') {
    $attention[] = ['info', 'Mode ' . ENVIRONMENT . ' — di hosting setel CI_ENVIRONMENT = production.', null, ''];
}
foreach ($server as [$k, $v, $ok]) {
    if (! $ok && ! in_array($k, ['Mode'], true)) {
        $attention[] = ['warning', $k . ': ' . $v, null, ''];
    }
}
$tiles = [
    ['Versi aplikasi', 'v' . app_version(), 'zap', 'ok'],
    ['Database', preg_replace(['/MySQL\/MariaDB/', '/^(\S+ \d+(\.\d+)?).*$/'], ['MySQL', '$1'], (string) ($srv['Database'] ?? '—')), 'database', 'ok'],
    ['Backup terakhir', $lastBackupAge === null ? 'Belum ada' : ($lastBackupAge === 0 ? 'Hari ini' : $lastBackupAge . ' hari lalu'), 'archive', $lastBackupAge === null ? 'bad' : ($lastBackupAge > 7 ? 'warn' : 'ok')],
    ['Galat di log', (string) count($errors), 'alert', $errors ? 'warn' : 'ok'],
];
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-1.5 text-sm text-muted">Cek cepat kesehatan aplikasi. Hal yang perlu ditindaklanjuti tampil paling atas.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="post" action="<?= site_url('system-status/cache-clear') ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= icon('zap', 'size-4') ?> Bersihkan cache</button></form>
        <form method="post" action="<?= site_url('settings/backup') ?>" data-progress="Membuat backup"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('database', 'size-4') ?> Backup sekarang</button></form>
    </div>
</div>

<ul class="ss-tiles mb-5" aria-label="Status singkat">
    <?php foreach ($tiles as [$label, $val, $ic, $st]): ?>
        <li class="ss-tile is-<?= $st ?>">
            <span class="ss-ico"><?= icon($ic, 'size-4') ?></span>
            <span class="min-w-0"><small><?= esc($label) ?></small><b><?= esc($val) ?></b></span>
            <span class="ss-dot" aria-label="<?= ['ok' => 'baik', 'warn' => 'perlu perhatian', 'bad' => 'bermasalah'][$st] ?>"></span>
        </li>
    <?php endforeach ?>
</ul>

<div class="ss-layout">
<div class="min-w-0">
<?php if ($migPending): ?>
<section class="card ss-migrate mb-5" aria-labelledby="ss-mig">
    <div class="flex flex-wrap items-start gap-3">
        <span class="ss-health-ico"><?= icon('database', 'size-6') ?></span>
        <div class="min-w-0 flex-1">
            <h2 id="ss-mig" class="text-base font-bold">Struktur database perlu diperbarui</h2>
            <p class="text-xs text-muted"><?= count($migPending) ?> pembaruan struktur belum dijalankan (biasanya setelah memasang versi baru). Fitur terkait bisa galat sebelum diperbarui.</p>
            <ul class="mt-1 font-mono text-xs text-muted"><?php foreach (array_slice($migPending, 0, 5) as $mg): ?><li><?= esc($mg) ?></li><?php endforeach ?></ul>
        </div>
        <form method="post" action="<?= site_url('system-status/migrate') ?>" data-progress="Memperbarui struktur database"
              data-confirm="Jalankan pembaruan struktur database sekarang? Database dibackup otomatis terlebih dahulu." data-confirm-label="Ya, jalankan">
            <?= csrf_field() ?><button class="btn btn-primary"><?= icon('database', 'size-4') ?> Jalankan migrasi</button>
        </form>
    </div>
</section>
<?php endif ?>
<?php $warnN = count(array_filter($attention, static fn ($x) => $x[0] !== 'info')); ?>
<section class="card ss-health mb-5 <?= $warnN ? 'is-warn' : 'is-ok' ?>" aria-labelledby="ss-att">
    <div class="ss-health-head">
        <span class="ss-health-ico"><?= icon($warnN ? 'alert' : 'check', 'size-6') ?></span>
        <div>
            <h2 id="ss-att" class="text-base font-bold"><?= $warnN ? $warnN . ' hal perlu perhatian' : 'Semua normal' ?></h2>
            <p class="text-xs text-muted"><?= $warnN ? 'Selesaikan dari atas ke bawah; klik tombol di kanan tiap baris.' : 'Tidak ada yang perlu ditindaklanjuti saat ini.' ?></p>
        </div>
    </div>
    <?php if ($attention): ?>
        <ul class="ss-att mt-3">
            <?php foreach ($attention as [$tone, $text, $url, $act]): ?>
                <li class="is-<?= $tone ?>"><?= icon($tone === 'info' ? 'info' : 'alert', 'size-4 shrink-0') ?><span class="flex-1"><?= esc($text) ?></span>
                    <?php if ($url): ?><a class="btn btn-ghost btn-sm" href="<?= esc($url, 'attr') ?>"><?= esc($act) ?></a><?php endif ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>

<h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Rincian teknis</h2>

<!-- Rincian (dilipat) -->
<div class="ss-more">
    <details class="card">
        <summary><?= icon('monitor', 'size-4') ?> Server &amp; database</summary>
        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
            <?php foreach ($server as [$k, $v, $ok]): ?>
                <dt class="text-muted"><?= esc($k) ?></dt>
                <dd class="flex items-center gap-2 break-all"><?= $ok ? '' : icon('alert', 'size-4 shrink-0 text-warn') ?><span><?= esc((string) $v) ?></span></dd>
            <?php endforeach ?>
        </dl>
    </details>

    <details class="card">
        <summary><?= icon('archive', 'size-4') ?> Backup &amp; penyimpanan <span class="ss-count"><?= (int) $backupCount ?> file</span></summary>
        <?php if ($backups): ?>
            <ul class="mt-3 grid gap-1 text-sm">
                <?php foreach (array_slice($backups, 0, 5) as $b): ?>
                    <li class="flex flex-wrap items-center justify-between gap-2"><span class="truncate font-mono text-xs"><?= esc($b['name']) ?></span>
                        <span class="text-xs text-muted"><?= $bytes($b['size']) ?> · <?= fmt_date(date('Y-m-d', $b['time'])) ?> <?= date('H:i', $b['time']) ?></span></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
        <ul class="mt-3 grid gap-1 border-t border-line pt-3 text-sm">
            <?php foreach ($dirs as [$name, $ok, $size]): ?>
                <li class="flex items-center justify-between gap-2"><span class="font-mono text-xs">writable/<?= esc($name) ?></span>
                    <span class="flex items-center gap-2 text-xs"><span class="text-muted"><?= $bytes($size) ?></span><?= $ok ? '<span class="text-ok">✓</span>' : badge('Tidak dapat ditulis', 'danger') ?></span></li>
            <?php endforeach ?>
        </ul>
        <div class="mt-3 flex flex-wrap gap-2">
            <a class="btn btn-ghost btn-sm" href="<?= site_url('settings/backup') ?>"><?= icon('database', 'size-4') ?> Kelola backup</a>
            <form method="post" action="<?= site_url('system-status/logs-prune') ?>" data-confirm="Hapus file log yang lebih tua dari 30 hari?" data-confirm-label="Ya, hapus">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('trash', 'size-4') ?> Hapus log &gt; 30 hari</button>
            </form>
        </div>
    </details>

    <details class="card">
        <summary><?= icon('shield', 'size-4') ?> Percobaan login terbaru</summary>
        <ul class="mt-3 grid gap-1 text-sm">
            <?php foreach (array_slice($logins, 0, 6) as $l): ?>
                <li class="flex flex-wrap items-center gap-2">
                    <?= badge(['login' => 'Berhasil', 'login_failed' => 'Gagal', 'login_blocked' => 'Dikunci'][$l['action']], $l['action'] === 'login' ? 'success' : 'danger') ?>
                    <span><?= esc($l['name'] ?? '—') ?></span>
                    <span class="ml-auto text-xs text-muted"><?= $local($l['created_at']) ?> · IP <?= esc($l['ip_address'] ?? '—') ?></span>
                </li>
            <?php endforeach ?>
            <?php if (! $logins): ?><li class="text-muted">Belum ada catatan.</li><?php endif ?>
        </ul>
        <a class="mt-3 inline-block text-sm" href="<?= site_url('settings/audit?entity_type=user') ?>">Log audit lengkap →</a>
    </details>

    <details class="card" id="sys-err" <?= $errors ? 'open' : '' ?>>
        <summary><?= icon('alert', 'size-4') ?> Galat terbaru <span class="ss-count"><?= count($errors) ?></span></summary>
        <?php if (! $errors): ?>
            <p class="mt-3 flex items-center gap-2 text-sm text-ok"><?= icon('check', 'size-4') ?> Tidak ada galat dalam 7 hari terakhir.</p>
        <?php else: ?>
            <ul class="mt-3 grid gap-1.5">
                <?php foreach ($errors as $e): ?>
                    <li class="rounded-lg bg-surface-2 p-2"><p class="text-xs text-muted"><?= esc($e['level']) ?> · terakhir <?= esc($e['time']) ?><?= $e['count'] > 1 ? ' · ' . (int) $e['count'] . '×' : '' ?></p><p class="break-all font-mono text-xs"><?= esc($e['message']) ?></p></li>
                <?php endforeach ?>
            </ul>
            <form method="post" action="<?= site_url('system-status/errors-ack') ?>" class="mt-3">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= icon('check', 'size-4') ?> Tandai sudah ditangani</button>
                <span class="ml-2 text-xs text-muted">Galat ini disembunyikan; berkas log tetap utuh.</span>
            </form>
        <?php endif ?>
        <?php if (! empty($errorsRecovered)): ?>
            <p class="mt-2 text-xs text-muted"><?= (int) $errorsRecovered ?> galat koneksi database (saat MySQL/Laragon belum berjalan) sudah pulih dengan sendirinya.</p>
        <?php endif ?>
    </details>

    <details class="card" id="ss-dapodik">
        <summary><?= icon('refresh', 'size-4') ?> Sinkron Dapodik <span class="ss-count"><?= $dpSet['last'] ? ($dpDiff ? $dpDiff . ' selisih' : 'sesuai') : 'belum' ?></span></summary>
        <ul class="mt-3 grid gap-1 text-sm">
            <?php foreach (\App\Libraries\Dapodik::KINDS as $k => $m): $l = $dpSet['last'][$k] ?? null; ?>
                <li class="flex flex-wrap items-center justify-between gap-2"><span><?= esc($m['label']) ?></span>
                    <span class="text-xs text-muted"><?= $l ? fmt_date(date('Y-m-d', (int) $l['time'])) . ' · ' . ((int) $l['counts']['new'] + (int) $l['counts']['changed']) . ' selisih' : 'belum pernah dicek' ?></span></li>
            <?php endforeach ?>
        </ul>
        <a class="btn btn-ghost btn-sm mt-3" href="<?= site_url('dapodik') ?>"><?= icon('refresh', 'size-4') ?> Buka Sinkron Dapodik</a>
    </details>

    <details class="card" id="ss-int" <?= $intDiff ? 'open' : '' ?>>
        <summary><?= icon('shield', 'size-4') ?> Keutuhan file <span class="ss-count"><?= $integ['active'] ? ($intDiff ? $intDiff . ' berubah' : 'asli') : 'tidak aktif' ?></span></summary>
        <?php if (! $integ['active']): ?>
            <p class="mt-3 text-sm text-muted">Aktif otomatis pada paket rilis hosting (dibuat dengan <code>php scripts/build-release.php</code>). Di development file bebas diedit.</p>
        <?php else: ?>
            <p class="mt-3 text-sm">Paket v<?= esc($integ['version']) ?> · <?= (int) $integ['total'] ?> file dipantau · sidik jari <code><?= esc($integ['fingerprint']) ?></code></p>
            <?php if (! $intDiff): ?>
                <p class="mt-2 flex items-center gap-2 text-sm text-ok"><?= icon('check', 'size-4') ?> Semua file sama dengan paket rilis resmi.</p>
            <?php else: ?>
                <?php foreach (['changed' => 'Diubah', 'added' => 'File baru', 'missing' => 'Hilang'] as $k => $lbl): if (! $integ[$k]) { continue; } ?>
                    <p class="mt-2 text-xs font-semibold"><?= $lbl ?> (<?= count($integ[$k]) ?>)</p>
                    <ul class="mt-1 grid gap-0.5 font-mono text-xs"><?php foreach (array_slice($integ[$k], 0, 15) as $f): ?><li class="break-all"><?= esc($f) ?></li><?php endforeach ?></ul>
                <?php endforeach ?>
                <form method="post" action="<?= site_url('system-status/integrity-accept') ?>" class="mt-3" data-confirm="Catat kondisi file saat ini sebagai versi resmi? Lakukan hanya bila perubahan ini memang Anda pasang sendiri." data-confirm-label="Ya, terima">
                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('check', 'size-4') ?> Perubahan ini sah, terima</button>
                </form>
            <?php endif ?>
        <?php endif ?>
    </details>

    <details class="card ss-wide">
        <summary><?= icon('layers', 'size-4') ?> Ukuran data</summary>
        <ul class="ss-sizes mt-3">
            <?php foreach ($counts as $t => $n): ?>
                <li class="ss-size"><span><?= esc($tableNames[$t] ?? ucfirst(str_replace('_', ' ', $t))) ?></span>
                    <b><?= $n === null ? '—' : number_format($n, 0, ',', '.') ?></b></li>
            <?php endforeach ?>
        </ul>
    </details>
</div>
</div>
<?php
$upd  = \App\Libraries\AppUpdate::state();
$newV = \App\Libraries\AppUpdate::available($upd);
$rels = \App\Libraries\AppUpdate::releases();
$curR = $rels[0] ?? null;
$prev = $rels[1] ?? null;
$canCheck = $upd['source'] !== '';
$cmt      = $upd['commit'] ?? null;
$ahead    = \App\Libraries\AppUpdate::pendingCommits($upd);
[$stLbl, $stTone] = $newV ? ['v' . $newV['version'] . ' tersedia', 'warning'] : ($ahead ? [$ahead . ' pembaruan kode', 'warning'] : ($upd['latest'] ? ['Terbaru', 'success'] : ($upd['error'] ? ['Belum terhubung', 'neutral'] : ['Belum dicek', 'neutral'])));
$when = static fn ($ts) => fmt_date(date('Y-m-d', (int) $ts)) . ', ' . date('H:i', (int) $ts);
?>
<aside class="ss-side" aria-label="Info versi aplikasi">
    <section class="card ss-ver" id="ss-ver" aria-labelledby="ss-ver-h">
        <div class="ss-ver-top">
            <h2 id="ss-ver-h" class="text-xs font-semibold uppercase tracking-wide text-muted">Versi aplikasi</h2>
            <?= badge($stLbl, $stTone) ?>
        </div>
        <p class="ss-ver-num">v<?= esc(app_version()) ?></p>
        <?php if ($curR): ?><p class="text-xs text-muted">Dirilis <?= fmt_date($curR['date']) ?></p><?php endif ?>

        <?php if ($newV): ?>
            <div class="ss-ver-new">
                <p class="text-sm font-semibold">Versi <?= esc($newV['version']) ?> tersedia<?= $newV['date'] ? ' · ' . fmt_date($newV['date']) : '' ?></p>
                <?php if ($newV['notes'] !== ''): ?><p class="ss-ver-notes"><?= esc($newV['notes']) ?></p><?php endif ?>
                <?php if ($newV['url']): ?><a class="btn btn-primary btn-sm mt-2" href="<?= esc($newV['url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= icon('download', 'size-4') ?> Lihat di GitHub</a><?php endif ?>
            </div>
        <?php endif ?>

        <?php if (! $newV && $ahead): ?>
            <div class="ss-ver-new">
                <p class="text-sm font-semibold"><?= $ahead ?> pembaruan kode belum terpasang</p>
                <p class="ss-ver-notes">Versi masih v<?= esc(app_version()) ?>, tetapi kode di GitHub lebih baru.</p>
            </div>
        <?php endif ?>
        <?php if ($newV || $ahead): $blk = \App\Libraries\UpdateInstaller::blocker(); ?>
            <form method="post" action="<?= site_url('system-status/update-install') ?>" class="mt-3" data-progress="Memasang pembaruan — jangan tutup halaman"
                  data-confirm="Pasang pembaruan sekarang? Database dibackup otomatis, berkas aplikasi diperbarui (writable/ dan .env tidak disentuh), lalu migrasi dijalankan." data-confirm-label="Ya, pasang">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-sm w-full justify-center" <?= $blk ? 'disabled' : '' ?>><?= icon('download', 'size-4') ?> Pasang pembaruan</button>
            </form>
            <?php if ($blk): ?><p class="ss-ver-meta"><?= esc($blk) ?></p><?php endif ?>
        <?php endif ?>
        <?php if ($steps = session('update_steps')): ?>
            <details class="ss-ver-all mt-2" open><summary>Rincian pemasangan</summary>
                <ol class="ss-ver-steps"><?php foreach ($steps as $st): ?><li><?= esc($st) ?></li><?php endforeach ?></ol></details>
        <?php endif ?>
        <?php if (! empty($cmt['dev']) && empty($blk)): ?>
            <p class="ss-ver-meta">Mode pengembangan: perbarui dengan git pull, lalu terbitkan rilis (<code>php scripts/build-release.php --publish</code>).</p>
        <?php endif ?>
        <?php if ($cmt): ?>
            <p class="ss-ver-commit" title="Rilis terbaru di GitHub"><?= icon('refresh', 'size-3.5 shrink-0') ?>
                <span><b><?= esc(substr($cmt['sha'], 0, 7)) ?></b><?= $cmt['date'] ? ' · ' . fmt_date($cmt['date']) : '' ?> — <?= esc($cmt['message']) ?>
                <?php if (! empty($cmt['installed'])): ?><br><small>Terpasang: <?= esc(substr($cmt['installed'], 0, 7)) ?><?= $cmt['installed'] === $cmt['sha'] ? ' (sama)' : '' ?></small><?php endif ?></span></p>
        <?php endif ?>

        <form method="post" action="<?= site_url('system-status/update-check') ?>" class="mt-4" data-progress="Mengecek &amp; memasang pembaruan — jangan tutup halaman">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm w-full justify-center" <?= $canCheck ? '' : 'disabled' ?>><?= icon('refresh', 'size-4') ?> <?= config('AppUpdate')->autoInstall ? 'Cek &amp; pasang pembaruan' : 'Cek pembaruan' ?></button>
        </form>
        <p class="ss-ver-meta"><?= $upd['checked_at'] ? 'Terakhir dicek ' . $when($upd['checked_at']) : 'Belum pernah dicek' ?><?= $upd['auto'] ? ' · otomatis tiap hari' : '' ?></p>
        <?php if ($upd['error']): ?>
            <p class="ss-ver-warn"><?= icon('alert', 'size-4 shrink-0') ?><span><?= esc($upd['error']) ?></span></p>
        <?php elseif (! $upd['hasToken'] && $canCheck && ! $upd['latest']): ?>
            <p class="ss-ver-meta">Repo private: pengelola server perlu mengisi <code>appupdate.githubToken</code> di <code>.env</code>.</p>
        <?php endif ?>

        <?php if ($curR): ?>
            <div class="ss-ver-sec">
                <h3>Yang baru di v<?= esc($curR['version']) ?></h3>
                <ul class="ss-ver-list"><?php foreach (array_slice($curR['notes'], 0, 4) as $nt): ?><li><?= esc($nt) ?></li><?php endforeach ?></ul>
                <?php if (count($curR['notes']) > 4): ?>
                    <details class="ss-ver-all"><summary>Lihat <?= count($curR['notes']) - 4 ?> lainnya</summary>
                        <ul class="ss-ver-list"><?php foreach (array_slice($curR['notes'], 4) as $nt): ?><li><?= esc($nt) ?></li><?php endforeach ?></ul></details>
                <?php endif ?>
            </div>
        <?php endif ?>

        <div class="ss-ver-sec">
            <h3>Riwayat versi</h3>
            <ol class="ss-ver-tl">
                <?php if ($curR): ?>
                    <li class="is-cur"><b>v<?= esc($curR['version']) ?></b> <span>Terpasang · <?= fmt_date($curR['date']) ?></span></li>
                <?php endif ?>
                <?php if ($prev): ?>
                    <li><details><summary><b>v<?= esc($prev['version']) ?></b> <span>Sebelumnya · <?= fmt_date($prev['date']) ?></span></summary>
                        <ul class="ss-ver-list"><?php foreach ($prev['notes'] as $nt): ?><li><?= esc($nt) ?></li><?php endforeach ?></ul></details></li>
                <?php endif ?>
            </ol>
        </div>
    </section>
</aside>
</div>
<?= $this->endSection() ?>
