<?php
/** @var array|null $authUser */
use App\Libraries\Auth;

$pref      = $authUser['theme_pref'] ?? '';
$asset     = static fn (string $p) => base_url('assets/' . $p) . '?v=' . (is_file(FCPATH . 'assets/' . $p) ? filemtime(FCPATH . 'assets/' . $p) : '1');
$pageTitle = isset($title) ? esc($title) . ' · Agenda Guru' : 'Agenda Guru';
$isAdmin   = $authUser && Auth::isAdmin();
$initials  = $authUser ? mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim(preg_replace('/[,.].*$/', '', $authUser['name']))), 0, 2)))) : '';
?>
<!doctype html>
<html lang="id" data-pref="<?= esc($pref, 'attr') ?>" data-theme="system"
      data-csrf-name="<?= csrf_token() ?>" data-csrf-hash="<?= csrf_hash() ?>"
      data-theme-url="<?= $authUser ? site_url('preferences/theme') : '' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?></title>
    <script>
        (function () {
            try {
                var d = document.documentElement;
                var t = d.getAttribute('data-pref') || localStorage.getItem('ag-theme') || 'system';
                var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) d.classList.add('dark');
                d.setAttribute('data-theme', t);
                if (localStorage.getItem('ag-motion') === 'reduce') d.setAttribute('data-motion', 'reduce');
                if (localStorage.getItem('ag-side') === 'hidden') d.classList.add('side-hidden');
            } catch (e) {}
        })();
    </script>
    <link rel="preload" href="<?= base_url('assets/fonts/inter-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
    <?php if ($authUser ?? null): ?><style><?= \App\Libraries\KopSettings::pageCss(\App\Libraries\KopSettings::get($profile ?? null)) ?></style><?php endif ?>
    <?= favicon_tags($profile ?? null) ?>
    <meta name="theme-color" content="#020617" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f1f5f9" media="(prefers-color-scheme: light)">
</head>
<body>
<a href="#main" class="skip-link">Lewati ke konten utama</a>
<div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>

<?php if (! $authUser): ?>
    <!-- ================= Halaman tamu (login) ================= -->
    <?= view('partials/auth_bg') ?>
    <main id="main" tabindex="-1" class="relative z-10 flex min-h-dvh flex-col items-center justify-center px-4 py-12 sm:px-6">
        <div class="w-full max-w-[26rem]"><?= view('partials/flash') ?></div>
        <?= $this->renderSection('content') ?>
    </main>
<?php else: ?>
<?= view('partials/app_deco') ?>
<!-- ================= Kerangka aplikasi: sidebar | (header + konten ber-scroll sendiri) ================= -->
<div class="app-shell flex h-dvh overflow-hidden">

    <aside id="app-side" class="app-side no-print hidden shrink-0 flex-col border-r border-line bg-surface lg:flex" aria-label="Navigasi utama">
        <?= view('partials/nav') ?>
    </aside>

    <!-- Laci menu (hamburger) untuk tablet & ponsel — dialog: fokus terkunci & dikembalikan otomatis -->
    <dialog id="nav-drawer" class="nav-drawer" aria-label="Menu navigasi">
        <div class="flex h-full flex-col">
            <div class="absolute right-3 top-3.5 z-10">
                <button type="button" class="hamburger !inline-grid" data-drawer-close aria-label="Tutup menu"><?= icon('x', 'size-5') ?></button>
            </div>
            <?= view('partials/nav') ?>
        </div>
    </dialog>

    <div class="app-col flex min-w-0 flex-1 flex-col">
        <header class="app-top no-print flex shrink-0 items-center gap-2 border-b border-line bg-surface/90 px-3 backdrop-blur sm:gap-3 sm:px-5 lg:px-6">
            <button type="button" class="hamburger shrink-0" data-drawer-open aria-label="Buka menu navigasi" aria-haspopup="dialog" aria-controls="nav-drawer"><?= icon('menu', 'size-5') ?></button>
            <button type="button" class="side-toggle shrink-0" data-side-toggle aria-controls="app-side" aria-expanded="true" title="Sembunyikan sidebar (Ctrl+B)">
                <?= icon('sidebar', 'size-5') ?><span class="sr-only" data-side-label>Sembunyikan sidebar</span>
            </button>
            <a href="<?= site_url('/') ?>" class="flex shrink-0 items-center gap-2 text-ink no-underline lg:hidden" aria-label="Dashboard">
                <?= brand_mark($profile ?? null, 'size-8') ?>
                <span class="hidden text-sm font-bold tracking-tight sm:inline">Manajemen Agenda Guru</span>
            </a>

            <!-- Pencarian cepat -->
            <form role="search" method="get" action="<?= site_url($isAdmin ? 'students' : 'agendas') ?>" class="relative hidden min-w-0 max-w-md flex-1 lg:block">
                <label for="top-q" class="sr-only"><?= $isAdmin ? 'Cari siswa' : 'Cari agenda' ?></label>
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
                <input id="top-q" name="q" type="search" placeholder="<?= $isAdmin ? 'Cari siswa (nama/NIS)…' : 'Cari agenda, topik, refleksi…' ?>"
                       class="input h-10 min-h-0 border-line bg-surface-2 pl-9 pr-10 text-sm shadow-none" value="<?= esc((string) (service('request')->getGet('q') ?? ''), 'attr') ?>">
                <kbd class="kbd pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2">/</kbd>
            </form>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <!-- Periode kerja (pil ringkas) -->
                <?php $years = \App\Libraries\Lookup::years(); $sems = \App\Libraries\Lookup::semesters(); ?>
                <form id="period-form" method="post" action="<?= site_url('period') ?>" class="period-pill" aria-label="Periode kerja">
                    <?= csrf_field() ?>
                    <span class="hidden pl-2 pr-0.5 text-muted xl:inline" aria-hidden="true"><?= icon('calendar', 'size-4') ?></span>
                    <label class="sr-only" for="period-year">Tahun pelajaran</label>
                    <select id="period-year" name="academic_year_id">
                        <?php if (! $years): ?><option value="">Belum ada</option><?php endif ?>
                        <?php foreach ($years as $id => $n): ?>
                            <option value="<?= $id ?>" <?= (int) $id === (int) ($period['year']['id'] ?? 0) ? 'selected' : '' ?>><?= esc($n) ?></option>
                        <?php endforeach ?>
                    </select>
                    <span class="sep" aria-hidden="true"></span>
                    <label class="sr-only" for="period-sem">Semester</label>
                    <select id="period-sem" name="semester_id">
                        <?php foreach ($sems as $id => $n): ?>
                            <option value="<?= $id ?>" <?= (int) $id === (int) ($period['semester']['id'] ?? 0) ? 'selected' : '' ?>><?= esc($n) ?></option>
                        <?php endforeach ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" data-period-submit>Terapkan</button>
                </form>

                <!-- Notifikasi (pengingat otomatis) -->
                <?php
                $notifs  = \App\Libraries\Notifications::forUser($authUser);
                $fresh   = array_values(array_filter($notifs, static fn ($n) => ! $n['read']));
                $seen    = array_values(array_filter($notifs, static fn ($n) => $n['read']));
                $unread  = count($fresh);
                $backUrl = current_url(true)->__toString();
                $ntItem  = static function (array $n) use ($backUrl): string {
                    ob_start(); ?>
                    <li class="nt-item is-<?= esc($n['tone'], 'attr') ?><?= $n['read'] ? ' is-read' : '' ?>">
                        <form method="post" action="<?= site_url('notifications/open') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="key" value="<?= esc($n['key'], 'attr') ?>">
                            <input type="hidden" name="back" value="<?= esc($backUrl, 'attr') ?>">
                            <button class="nt-link" title="<?= $n['url'] ? 'Buka' : 'Tandai dibaca' ?>">
                                <span class="nt-ico"><?= icon($n['icon'], 'size-4') ?></span>
                                <span class="min-w-0 flex-1"><b><?= esc($n['title']) ?></b><?php if ($n['body'] !== ''): ?><small><?= esc($n['body']) ?></small><?php endif ?></span>
                                <?php if ($n['read']): ?>
                                    <span class="nt-state is-read"><?= icon('check', 'size-3') ?> Dibaca</span>
                                <?php else: ?>
                                    <span class="nt-state is-new"><span class="nt-dot" aria-hidden="true"></span> Baru</span>
                                <?php endif ?>
                            </button>
                        </form>
                    </li>
                    <?php return (string) ob_get_clean();
                };
                ?>
                <details class="relative" data-dropdown>
                    <summary class="nt-bell<?= $unread ? ' has-new' : ($notifs ? ' all-read' : '') ?>" aria-label="Notifikasi<?= $unread ? ', ' . $unread . ' belum dibaca' : ($notifs ? ', semua sudah dibaca' : '') ?>" title="Notifikasi">
                        <?= icon('bell', 'size-[1.15rem]') ?>
                        <?php if ($unread): ?>
                            <span class="nt-badge"><?= $unread > 9 ? '9+' : $unread ?></span>
                        <?php elseif ($notifs): ?>
                            <span class="nt-ok" aria-hidden="true"><?= icon('check', 'size-2.5') ?></span>
                        <?php endif ?>
                    </summary>
                    <div class="nt-panel">
                        <div class="nt-head">
                            <div>
                                <p class="text-sm font-semibold">Notifikasi</p>
                                <p class="text-[0.7rem] text-muted"><?= $notifs ? $unread . ' baru · ' . count($seen) . ' sudah dibaca' : 'Belum ada pengingat' ?></p>
                            </div>
                            <?php if ($unread): ?>
                                <form method="post" action="<?= site_url('notifications/read') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="<?= esc($backUrl, 'attr') ?>">
                                    <button class="nt-allread"><?= icon('check', 'size-3.5') ?> Tandai semua dibaca</button>
                                </form>
                            <?php endif ?>
                        </div>
                        <?php if (! $notifs): ?>
                            <p class="nt-empty"><?= icon('check', 'size-5') ?> Tidak ada notifikasi. Semua beres!</p>
                        <?php else: ?>
                            <div class="nt-list">
                                <?php if ($fresh): ?>
                                    <p class="nt-sec">Baru</p>
                                    <ul><?php foreach ($fresh as $n) { echo $ntItem($n); } ?></ul>
                                <?php endif ?>
                                <?php if ($seen): ?>
                                    <p class="nt-sec"><?= icon('check', 'size-3') ?> Sudah dibaca</p>
                                    <ul><?php foreach ($seen as $n) { echo $ntItem($n); } ?></ul>
                                <?php endif ?>
                            </div>
                        <?php endif ?>
                    </div>
                </details>

                <button type="button" class="hamburger !hidden sm:!inline-grid" data-theme-toggle aria-label="Ganti tema (terang, gelap, ikuti sistem)" title="Ganti tema">
                    <span data-theme-icon="light" hidden><?= icon('sun', 'size-[1.1rem]') ?></span>
                    <span data-theme-icon="dark" hidden><?= icon('moon', 'size-[1.1rem]') ?></span>
                    <span data-theme-icon="system"><?= icon('monitor', 'size-[1.1rem]') ?></span>
                </button>

                <span class="hidden h-6 w-px bg-line sm:block" aria-hidden="true"></span>

                <!-- Menu profil (drop-down tanpa JS) -->
                <details class="group relative" data-dropdown>
                    <summary class="flex cursor-pointer items-center gap-2.5 rounded-xl p-1 transition-all duration-200 hover:bg-surface-2 md:pr-2" aria-label="Menu akun">
                        <?= user_avatar($authUser, 'size-8', 'ring-2 ring-surface') ?>
                        <span class="hidden min-w-0 text-left leading-tight md:block">
                            <span class="block max-w-36 truncate text-sm font-semibold text-ink"><?= esc($authUser['name']) ?></span>
                            <span class="block text-[0.7rem] font-medium text-muted"><?= esc(Auth::ROLES[$authUser['role']] ?? '') ?></span>
                        </span>
                        <span class="hidden text-muted transition-all duration-200 group-open:rotate-180 md:block"><?= icon('chevron-down', 'size-4') ?></span>
                    </summary>
                    <div class="absolute right-0 z-40 mt-2 w-64 rounded-2xl border border-line bg-surface p-1.5 shadow-xl shadow-slate-900/10">
                        <div class="flex items-center gap-3 border-b border-line px-3 py-3">
                            <?= user_avatar($authUser, 'size-9') ?>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold"><?= esc($authUser['name']) ?></p>
                                <p class="truncate text-xs text-muted"><?= esc($authUser['email']) ?></p>
                            </div>
                        </div>
                        <a href="<?= site_url('settings/profile') ?>" class="mt-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink no-underline transition-all duration-200 hover:bg-surface-2"><?= icon('users', 'size-4 text-muted') ?> Profil saya</a>
                        <a href="<?= site_url('settings/security') ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink no-underline transition-all duration-200 hover:bg-surface-2"><?= icon('shield', 'size-4 text-muted') ?> Kata sandi</a>
                        <a href="<?= site_url('settings/preferences') ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink no-underline transition-all duration-200 hover:bg-surface-2"><?= icon('sun', 'size-4 text-muted') ?> Tema & preferensi</a>
                        <form method="post" action="<?= site_url('logout') ?>" class="mt-1 border-t border-line pt-1">
                            <?= csrf_field() ?>
                            <button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-bad transition-all duration-200 hover:bg-surface-2"><?= icon('logout', 'size-4') ?> Keluar</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <!-- Konten utama: scroll mandiri -->
        <main id="main" tabindex="-1" class="app-main flex flex-1 flex-col overflow-y-auto">
            <div class="mx-auto w-full max-w-7xl flex-1 px-4 pt-6 pb-20 sm:px-6 sm:pt-8">
                <?php if (empty($ownKop)): ?>
                    <div class="print-only mb-3">
                        <?= view('partials/kop', ['profile' => $profile]) ?>
                        <p style="font-size:9pt"><?= $isAdmin ? 'Admin' : 'Guru' ?>: <?= esc($authUser['name'] ?? '') ?> ·
                            T.P. <?= esc($period['year']['name'] ?? '') ?> Semester <?= esc($period['semester']['name'] ?? '') ?> ·
                            Dicetak <?= fmt_date(date('Y-m-d'), true) ?></p>
                    </div>
                <?php endif ?>
                <?php if (session('pw_default') && ! url_is('settings/security')): ?>
                    <div class="alert alert-warning mb-5 no-print" role="alert"><?= icon('shield', 'size-5 shrink-0') ?>
                        <span>Anda masih memakai <strong>kata sandi awal</strong>. Demi keamanan akun, segera <a href="<?= site_url('settings/security') ?>"><strong>ganti kata sandi</strong></a>.</span></div>
                <?php endif ?>
                <?= view('partials/flash') ?>
                <?= $this->renderSection('content') ?>
            </div>

            <footer class="app-foot no-print">
                <div class="mx-auto max-w-7xl px-4 py-5 text-center text-xs text-muted sm:px-6">
                    <p class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-1">
                        <?= brand_mark($profile ?? null, 'size-5') ?>
                        <span>Copyright © <?= date('Y') ?> <strong class="font-semibold text-ink">Manajemen Agenda Guru</strong><?= ! empty($profile['school_name']) ? ' — ' . esc($profile['school_name']) : '' ?>. Hak cipta dilindungi.</span>
                    </p>
                    <p class="mt-1.5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                        <span>Create by @yhsyah</span>
                        <span aria-hidden="true">·</span>
                        <span class="rounded-full border border-line bg-surface px-2 py-0.5 font-mono text-[0.65rem]">v<?= esc(app_version()) ?></span>
                    </p>
                </div>
            </footer>
        </main>
    </div>
</div>

<?php if (Auth::isTeacher()): ?>
    <!-- Menu radial: hanya pintasan opsional, semua tujuan juga ada di navigasi utama -->
    <div class="radial no-print">
        <nav class="radial-items" aria-label="Pintasan cepat" id="radial-items">
            <a class="btn btn-ghost btn-sm shadow-lg" href="<?= site_url('agendas/new') ?>"><?= icon('pen', 'size-4') ?> Tulis agenda</a>
            <a class="btn btn-ghost btn-sm shadow-lg" href="<?= site_url('assessments/new') ?>"><?= icon('chart', 'size-4') ?> Asesmen baru</a>
            <a class="btn btn-ghost btn-sm shadow-lg" href="<?= site_url('reports/journal') ?>"><?= icon('printer', 'size-4') ?> Cetak jurnal</a>
            <a class="btn btn-ghost btn-sm shadow-lg" href="<?= site_url('schedules') ?>"><?= icon('clock', 'size-4') ?> Jadwal</a>
        </nav>
        <button type="button" class="radial-toggle btn btn-primary btn-icon size-12 rounded-2xl shadow-xl" aria-expanded="false" aria-controls="radial-items" aria-label="Pintasan cepat">
            <?= icon('plus', 'size-5') ?>
        </button>
    </div>
<?php endif ?>
<?php endif ?>

<dialog id="confirm-dialog" class="dlg-confirm" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="dlg-confirm-body">
        <span class="dlg-confirm-ico" aria-hidden="true"><?= icon('alert', 'size-5') ?></span>
        <div class="min-w-0">
            <h2 id="confirm-title">Konfirmasi</h2>
            <p id="confirm-message"></p>
        </div>
    </div>
    <div class="dlg-confirm-actions">
        <button type="button" class="btn btn-ghost btn-sm" id="confirm-no">Batal</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirm-yes">Ya, lanjutkan</button>
    </div>
</dialog>

<script src="<?= $asset('js/app.js') ?>" defer></script>
</body>
</html>
