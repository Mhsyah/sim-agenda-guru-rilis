<?php
use App\Libraries\Auth;

$teacher = Auth::isTeacher();
$admin   = Auth::isAdmin();
/** Tautan menu: ikon + label (label tetap terbaca pembaca layar saat mode rail). */
$link = static function (string $path, string $label, string $icon = '', bool $exact = false): string {
    return '<a class="nav-link" href="' . site_url($path) . '" title="' . esc($label, 'attr') . '"' . nav_current($path === '/' ? '' : $path, $exact || $path === '/') . '>'
        . ($icon ? icon($icon, 'size-[1.1rem] shrink-0') : '') . '<span class="nav-label truncate">' . esc($label) . '</span></a>';
};
$group = static fn (string $t) => '<p class="nav-group"><span class="nav-group-text">' . esc($t) . '</span></p>';
?>
<div class="nav-brand flex h-16 shrink-0 items-center gap-2.5 px-4">
    <?= brand_mark($profile ?? null, 'size-9') ?>
    <div class="brand-text min-w-0 leading-tight">
        <a href="<?= site_url('/') ?>" class="block text-[0.9rem] font-bold leading-snug tracking-tight text-ink no-underline">Manajemen Agenda Guru</a>
        <p class="truncate text-[0.7rem] text-muted" title="<?= esc($profile['school_name'] ?? '', 'attr') ?>"><?= esc($profile['school_name'] ?? 'Manajemen Agenda Guru') ?></p>
    </div>
</div>
<nav class="flex-1 overflow-y-auto px-2.5 pb-4" aria-label="Menu">
    <?= $link('/', 'Dashboard', 'home') ?>

    <?php if ($admin): ?>
        <?= $group('Data sekolah') ?>
        <?= $link('settings/school', 'Profil & Kop Sekolah', 'home') ?>
        <?= $link('cover', 'Sampul Buku Agenda', 'image') ?>
        <?= $link('users', 'Guru & Pengguna', 'users') ?>
        <?php $schedOpen = url_is('teacher-schedules*') || url_is('schedule-builder*'); ?>
        <details class="nav-tree" <?= $schedOpen ? 'open' : '' ?>>
            <summary class="nav-link<?= $schedOpen ? ' is-parent' : '' ?>" title="Jadwal Mengajar Guru"><?= icon('clock', 'size-[1.1rem] shrink-0') ?><span class="nav-label truncate">Jadwal Mengajar Guru</span><span class="nav-caret"><?= icon('chevron-down', 'size-4') ?></span></summary>
            <div class="nav-sub">
                <?= $link('teacher-schedules', 'Jadwal Keseluruhan', '', true) ?>
                <?= $link('schedule-builder/generate', 'Proses Jadwal Otomatis') ?>
                <?= $link('schedule-builder/upload', 'Upload Jadwal (PDF)') ?>
                <?= $link('schedule-builder/setup', 'Beban JP & Struktur Jam') ?>
                <?= $link('teacher-schedules/piket', 'Atur Guru Piket') ?>
            </div>
        </details>
        <?= $link('academic-years', 'Tahun Pelajaran', 'calendar') ?>
        <?= $link('semesters', 'Semester', 'layers') ?>
        <?= $link('subjects', 'Mata Pelajaran', 'book') ?>
        <?= $link('classes', 'Kelas/Rombel', 'grid') ?>
        <?= $link('students', 'Data Siswa', 'users') ?>
        <?= $link('promotions', 'Kelulusan & Kenaikan', 'graduate') ?>
        <?= $link('calendar', 'Kalender', 'calendar') ?>
        <?= $link('imports', 'Impor Data', 'upload') ?>

        <?= $group('Sistem') ?>
        <?= $link('system-status', 'Status Sistem', 'monitor') ?>
        <?= $link('dapodik', 'Sinkron Dapodik', 'refresh') ?>
        <?= $link('settings/backup', 'Backup & Restore', 'database') ?>
        <?= $link('settings/audit', 'Log Audit', 'archive') ?>
    <?php endif ?>

    <?php if ($teacher): ?>
        <?= $group('Mengajar') ?>
        <?php $calOn = url_is('calendar*') || url_is('agendas*'); ?>
        <a class="nav-link" href="<?= site_url('calendar') ?>" title="Kalender &amp; Agenda" <?= $calOn ? 'aria-current="page"' : '' ?>><?= icon('calendar', 'size-[1.1rem] shrink-0') ?><span class="nav-label truncate">Kalender &amp; Agenda</span></a>
        <?= $link('schedules', 'Jadwal', 'clock') ?>
        <?= $link('principal-reviews', 'Kesan Kepsek', 'sparkle') ?>
        <?= $link('students', 'Data Siswa', 'users') ?>
        <?= $link('curriculum', 'Unggah CP/TP/ATP/KKTP', 'upload') ?>

        <?= $group('Kurikulum') ?>
        <?= $link('outcomes', 'Capaian Pembelajaran (CP)', 'target') ?>
        <?= $link('objectives', 'Tujuan Pembelajaran (TP)', 'check') ?>
        <?= $link('atp', 'Alur TP (ATP)', 'list') ?>
        <?= $link('kktp', 'KKTP', 'target') ?>

        <?= $group('Penilaian') ?>
        <?php $evalOpen = url_is('assessments*') || url_is('grading*') || url_is('recap*'); ?>
        <details class="nav-tree" <?= $evalOpen ? 'open' : '' ?>>
            <summary class="nav-link<?= $evalOpen ? ' is-parent' : '' ?>" title="Penilaian"><?= icon('chart', 'size-[1.1rem] shrink-0') ?><span class="nav-label truncate">Penilaian</span><span class="nav-caret"><?= icon('chevron-down', 'size-4') ?></span></summary>
            <div class="nav-sub">
                <?= $link('assessments', 'Asesmen & Nilai') ?>
                <?= $link('grading', 'Bobot Nilai') ?>
                <?= $link('recap', 'Rekap Nilai') ?>
            </div>
        </details>

        <?= $group('Laporan') ?>
        <?= $link('reports/journal', 'Buku Jurnal', 'printer', true) ?>
        <?= $link('cover', 'Sampul Buku Agenda', 'image', true) ?>
        <?= $link('reports', 'Laporan', 'file', true) ?>
    <?php endif ?>
</nav>
<div class="nav-foot border-t border-line p-2.5">
    <p class="role-chip mb-1.5 inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-muted">
        <?= icon($admin ? 'shield' : 'pen', 'size-3') ?> <?= $admin ? 'Admin Teknis' : 'Guru' ?>
    </p>
    <?= $link('settings/profile', 'Profil Saya', 'users') ?>
</div>
