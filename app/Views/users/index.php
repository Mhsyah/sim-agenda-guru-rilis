<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Users;
use App\Libraries\Auth;

$role   = (string) ($filters['role'] ?? '');
$active = (string) ($filters['is_active'] ?? '');
$q      = (string) ($filters['q'] ?? '');
/** Tautan filter dengan mempertahankan pencarian. */
$url = static function (array $set) use ($filters) {
    $p = array_filter(array_merge(['q' => $filters['q'] ?? '', 'role' => $filters['role'] ?? '', 'is_active' => $filters['is_active'] ?? ''], $set), static fn ($v) => $v !== '');

    return site_url('users') . ($p ? '?' . http_build_query($p) : '');
};
$ago = static function (?string $dt): string {
    if (! $dt) {
        return 'Belum pernah login';
    }
    $d = (int) floor((strtotime(date('Y-m-d')) - strtotime(substr($dt, 0, 10))) / 86400);

    return match (true) {
        $d <= 0 => 'Login hari ini, ' . substr($dt, 11, 5),
        $d === 1 => 'Login kemarin',
        $d < 30 => 'Login ' . $d . ' hari lalu',
        default => 'Login ' . fmt_date(substr($dt, 0, 10)),
    };
};
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 max-w-2xl text-muted"><?= esc($intro) ?></p>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        <?php foreach ($headerLinks as $l): ?>
            <a class="btn btn-ghost" href="<?= $l['url'] ?>"><?= icon($l['icon'] ?? 'file', 'size-4') ?> <?= esc($l['label']) ?></a>
        <?php endforeach ?>
        <a class="btn btn-primary" href="<?= site_url('users/new') ?>"><?= icon('plus', 'size-4') ?> Tambah pengguna</a>
    </div>
</div>

<!-- Ringkasan -->
<ul class="u-stats mb-4" aria-label="Ringkasan pengguna">
    <li><span class="u-stat-ico"><?= icon('users', 'size-4') ?></span><b><?= $stats['total'] ?></b><span>Total akun</span></li>
    <li><span class="u-stat-ico is-indigo"><?= icon('pen', 'size-4') ?></span><b><?= $stats['teacher'] ?></b><span>Guru</span></li>
    <li><span class="u-stat-ico is-slate"><?= icon('shield', 'size-4') ?></span><b><?= $stats['admin'] ?></b><span>Admin teknis</span></li>
    <li><span class="u-stat-ico is-green"><?= icon('check', 'size-4') ?></span><b><?= $stats['active'] ?></b><span>Aktif</span></li>
    <li><span class="u-stat-ico is-amber"><?= icon('clock', 'size-4') ?></span><b><?= $stats['never'] ?></b><span>Belum login</span></li>
    <li><span class="u-stat-ico is-red"><?= icon('x', 'size-4') ?></span><b><?= $stats['inactive'] ?></b><span>Nonaktif</span></li>
</ul>

<!-- Tab peran + cari + status -->
<div class="u-toolbar card mb-6 p-2.5 sm:p-2.5">
    <nav class="u-tabs" aria-label="Filter peran">
        <?php foreach (['' => ['Semua', $stats['total']], 'teacher' => ['Guru', $stats['teacher']], 'admin' => ['Admin teknis', $stats['admin']]] as $v => [$l, $n]): ?>
            <a href="<?= esc($url(['role' => $v]), 'attr') ?>" <?= $role === $v ? 'aria-current="page"' : '' ?>><?= $l ?> <span><?= $n ?></span></a>
        <?php endforeach ?>
    </nav>
    <form method="get" class="u-search" role="search" aria-label="Cari pengguna" data-live-search>
        <?php if ($role !== ''): ?><input type="hidden" name="role" value="<?= esc($role, 'attr') ?>"><?php endif ?>
        <div class="relative min-w-0 flex-1">
            <label for="q" class="sr-only">Cari nama, email, atau NIP</label>
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input class="input h-10 min-h-0 pl-9 text-sm" type="search" id="q" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama, email, atau NIP…">
        </div>
        <label for="flt-active" class="sr-only">Status</label>
        <select class="input h-10 min-h-0 w-auto text-sm" id="flt-active" name="is_active" data-autosubmit>
            <option value="">Semua status</option>
            <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Aktif</option>
            <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
        <button class="btn btn-ghost h-10"><?= icon('search', 'size-4') ?><span class="sr-only sm:not-sr-only"> Cari</span></button>
    </form>
</div>

<div data-live-region>
<?php if (! $table): ?>
    <div class="card flex flex-col items-center gap-3 px-6 py-14 text-center">
        <span class="grid size-14 place-items-center rounded-2xl bg-surface-2 text-muted"><?= icon('users', 'size-7') ?></span>
        <p class="text-lg font-bold">Tidak ada pengguna</p>
        <p class="max-w-md text-sm text-muted"><?= $q !== '' || $role !== '' || $active !== '' ? 'Tidak ada yang cocok dengan filter.' : 'Mulai dengan menambahkan atau mengimpor data guru.' ?></p>
    </div>
<?php else: ?>
    <ul class="u-grid" aria-label="Daftar pengguna">
        <?php foreach ($table as $t): $u = $t['row']; $id = (int) $u['id'];
            $isT  = $u['role'] === 'teacher';
            $on   = (int) $u['is_active'] === 1;
            $subs = $subjectNames[$id] ?? [];
            $st   = ! $on ? ['Nonaktif', 'warning'] : (empty($u['last_login_at']) ? ['Belum login', 'info'] : ['Aktif', 'success']); ?>
            <li class="u-card<?= $on ? '' : ' is-off' ?>">
                <div class="u-head">
                    <?= user_avatar($u, 'size-12', 'text-sm') ?>
                    <div class="min-w-0 flex-1">
                        <p class="u-name" title="<?= esc($u['name'], 'attr') ?>"><?= esc($u['name']) ?></p>
                        <p class="u-email" title="<?= esc($u['email'], 'attr') ?>"><?= esc($u['email']) ?></p>
                    </div>
                    <span class="badge badge-<?= $st[1] ?> shrink-0"><?= $st[0] ?></span>
                </div>

                <div class="u-meta">
                    <?= badge(Auth::ROLES[$u['role']] ?? $u['role'], $isT ? 'info' : 'neutral') ?>
                    <span><?= icon('file', 'size-3.5') ?> <?= $u['nip'] ? 'NIP ' . esc($u['nip']) : 'Tanpa NIP' ?></span>
                    <?php if (! empty($u['gender'])): ?><span><?= $u['gender'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></span><?php endif ?>
                </div>

                <?php if (! $isT): ?>
                    <div class="u-teach">
                        <p class="u-label">Akses</p>
                        <p class="text-xs leading-relaxed text-muted">Mengelola data induk sekolah (guru, siswa, kelas, mapel, tahun pelajaran), kop &amp; cetak, backup, dan status sistem.</p>
                    </div>
                <?php endif ?>
                <?php if ($isT): ?>
                    <div class="u-teach">
                        <p class="u-label">Mengajar</p>
                        <?php if (! $subs): ?>
                            <p class="text-xs text-muted">Mapel &amp; kelas belum diatur. <a href="<?= site_url('users/' . $id . '/edit') ?>">Atur sekarang</a></p>
                        <?php else: ?>
                            <ul class="grid gap-1.5">
                                <?php foreach ($subs as $sid => $sn): $groups = Users::groupClasses($classNames[$id][$sid] ?? []); $nCls = count($classNames[$id][$sid] ?? []); ?>
                                    <li class="u-subj">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-semibold"><?= subject_chip($subjectCodes[$sid] ?? null, preg_replace('/\s*\([^)]*\)$/', '', $sn)) ?></span>
                                            <span class="text-[0.7rem] text-muted"><?= $nCls ? $nCls . ' kelas' : 'kelas belum diatur' ?></span>
                                        </div>
                                        <?php if ($groups): ?>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <?php foreach ($groups as $grade => $letters): ?>
                                                    <span class="u-grade"><b><?= esc($grade) ?></b> <?= esc($letters) ?></span>
                                                <?php endforeach ?>
                                            </div>
                                        <?php endif ?>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </div>
                <?php endif ?>

                <div class="u-foot">
                    <span class="flex min-w-0 items-center gap-1.5 truncate text-xs text-muted"><?= icon('clock', 'size-3.5') ?> <?= esc($ago($u['last_login_at'])) ?></span>
                    <?php if ($isT): ?><span class="text-xs text-muted"><?= icon('pen', 'size-3.5') ?> <?= (int) $u['agenda_count'] ?> agenda</span><?php endif ?>
                    <div class="ml-auto flex gap-1.5 no-print">
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('users/' . $id . '/edit') ?>"><?= icon('edit', 'size-4') ?> Ubah<span class="sr-only"> <?= esc($u['name']) ?></span></a>
                        <form method="post" action="<?= site_url('users/' . $id . '/delete') ?>" data-confirm="Hapus akun <?= esc($u['name'], 'attr') ?>? Guru yang sudah punya agenda/asesmen tidak bisa dihapus — nonaktifkan saja." data-confirm-label="Ya, hapus">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm btn-icon size-9 hover:text-bad" title="Hapus" aria-label="Hapus <?= esc($u['name'], 'attr') ?>"><?= icon('trash', 'size-4') ?></button>
                        </form>
                    </div>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">Menampilkan <?= count($table) ?> dari <?= $pager->getTotal() ?> pengguna</p>
        <?= $pager->links() ?>
    </div>
<?php endif ?>
</div>
<?= $this->endSection() ?>
