<?php
/**
 * @var string     $mode     guru|kelas
 * @var list<array> $list    ringkasan per guru
 * @var list<array>|null $grid kisi guru/kelas terpilih
 * @var array|null $selected
 */
$url  = static fn (array $p) => site_url('teacher-schedules') . (($p = array_filter($p, static fn ($v) => $v !== '' && $v !== null && $v !== 0)) ? '?' . http_build_query($p) : '');
$days = array_slice(day_names(), 0, 6, true);
// Senin–Jumat selalu tampil; Sabtu hanya bila ada jadwal
$used = array_filter($days, static fn ($d) => $d <= 5 || ! empty($grid[6]), ARRAY_FILTER_USE_KEY);
// Ukuran kertas landscape dari Pengaturan Cetak (mm) → lembar diperkecil otomatis agar muat 1 halaman
[$pw, $ph] = array_map('floatval', explode(' ', \App\Libraries\KopSettings::PAPERS[$kopCfg['paper']][1] ?? '210mm 297mm'));
$fit = 'data-fit-w="' . ($ph - $kopCfg['margin_left'] - $kopCfg['margin_right']) . '" data-fit-h="' . ($pw - $kopCfg['margin_top'] - $kopCfg['margin_bottom']) . '"';
$semName = $period['semester']['name'] ?? '';
$ttd     = \App\Libraries\SchoolLogo::assetUrl($profile['principal_signature'] ?? null, 'ttd');
$sign    = static function () use ($profile, $kopCfg, $ttd): string {
    ob_start(); ?>
    <div class="ts-sign">
        <p><?= esc(($profile['school_city'] ?? '') ?: '……………') ?>, <?= fmt_date(\App\Libraries\KopSettings::signDate($kopCfg), true) ?></p>
        <p>Kepala <?= esc($profile['school_name'] ?? 'Sekolah') ?></p>
        <div class="ts-sign-space"><?php if ($ttd): ?><img src="<?= esc($ttd, 'attr') ?>" alt="Tanda tangan kepala sekolah"><?php endif ?></div>
        <p class="font-bold underline"><?= esc(($profile['principal_name'] ?? '') ?: '……………………………') ?></p>
        <p>NIP. <?= esc(($profile['principal_nip'] ?? '') ?: '……………………') ?></p>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4 no-print">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted">Jadwal mingguan seluruh guru pada T.P. <?= esc($period['year']['name'] ?? '') ?>. Jadwal diisi &amp; diubah oleh masing-masing guru di menu Jadwal.</p>
    </div>
    <?php if ($overall): ?>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-ghost" href="<?= site_url('teacher-schedules/piket') ?>"><?= icon('shield', 'size-4') ?> Atur piket</a>
            <form method="get" class="flex items-center gap-1.5" aria-label="Ukuran kertas cetak">
                <label for="ov-paper" class="text-xs text-muted">Kertas</label>
                <select id="ov-paper" name="print[paper]" class="input h-10 min-h-0 w-auto text-sm" data-autosubmit>
                    <?php foreach (\App\Libraries\KopSettings::PAPERS as $pk => $pp): ?>
                        <option value="<?= $pk ?>" <?= $kopCfg['paper'] === $pk ? 'selected' : '' ?>><?= esc(explode(' (', $pp[0])[0]) ?> landscape</option>
                    <?php endforeach ?>
                </select>
            </form>
            <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?> Cetak jadwal keseluruhan</button>
        </div>
    <?php endif ?>
    <?php if ($grid !== null): ?>
        <div class="flex gap-2">
            <a class="btn btn-ghost" href="<?= esc($url(['mode' => $mode]), 'attr') ?>"><?= icon('chevron-left', 'size-4') ?> Semua <?= $mode === 'guru' ? 'guru' : 'kelas' ?></a>
            <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?> Cetak jadwal</button>
        </div>
    <?php endif ?>
</div>

<ul class="u-stats mb-4 no-print" aria-label="Ringkasan jadwal">
    <li><span class="u-stat-ico is-indigo"><?= icon('users', 'size-4') ?></span><b><?= $stats['teachers'] ?>/<?= $stats['total'] ?></b><span>Guru terjadwal</span></li>
    <li><span class="u-stat-ico is-green"><?= icon('clock', 'size-4') ?></span><b><?= $stats['jp'] ?></b><span>JP per minggu</span></li>
    <li><span class="u-stat-ico is-amber"><?= icon('grid', 'size-4') ?></span><b><?= $stats['classes'] ?></b><span>Kelas terjadwal</span></li>
    <li><span class="u-stat-ico"><?= icon('calendar', 'size-4') ?></span><b><?= $stats['blocks'] ?></b><span>Blok jadwal</span></li>
</ul>

<div class="u-toolbar card mb-6 p-2.5 sm:p-2.5 no-print">
    <nav class="u-tabs" aria-label="Tampilan jadwal">
        <a href="<?= esc($url([]), 'attr') ?>" <?= $mode === 'semua' ? 'aria-current="page"' : '' ?>><?= icon('list', 'size-4') ?> Keseluruhan</a>
        <a href="<?= esc($url(['mode' => 'guru']), 'attr') ?>" <?= $mode === 'guru' ? 'aria-current="page"' : '' ?>><?= icon('users', 'size-4') ?> Per guru</a>
        <a href="<?= esc($url(['mode' => 'kelas']), 'attr') ?>" <?= $mode === 'kelas' ? 'aria-current="page"' : '' ?>><?= icon('grid', 'size-4') ?> Per kelas</a>
    </nav>
    <?php if ($mode !== 'semua'): ?>
    <form method="get" class="u-search" aria-label="Pilih jadwal">
        <input type="hidden" name="mode" value="<?= esc($mode, 'attr') ?>">
        <?php if ($mode === 'guru'): ?>
            <label for="ts-guru" class="sr-only">Pilih guru</label>
            <select class="input h-10 min-h-0 min-w-0 flex-1 text-sm" id="ts-guru" name="guru" data-autosubmit>
                <option value="">— Pilih guru untuk melihat kisi jadwal —</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= ($selected['id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                <?php endforeach ?>
            </select>
        <?php else: ?>
            <label for="ts-kelas" class="sr-only">Pilih kelas</label>
            <select class="input h-10 min-h-0 min-w-0 flex-1 text-sm" id="ts-kelas" name="kelas" data-autosubmit>
                <option value="">— Pilih kelas —</option>
                <?php foreach ($classes as $id => $n): ?>
                    <option value="<?= (int) $id ?>" <?= ($selected['id'] ?? 0) == $id ? 'selected' : '' ?>><?= esc($n) ?></option>
                <?php endforeach ?>
            </select>
        <?php endif ?>
        <button class="btn btn-ghost h-10"><?= icon('eye', 'size-4') ?><span class="sr-only sm:not-sr-only"> Lihat</span></button>
    </form>
    <?php else: ?>
        <p class="px-2 text-xs text-muted">Klik guru di panel samping (atau sel di tabel) untuk menyorot jadwalnya.</p>
    <?php endif ?>
</div>

<?php if ($overall): ?>
    <!-- Jadwal keseluruhan: Pagi & Siang + panel kode guru -->
    <div class="ov-layout" data-ov>
        <div class="min-w-0">
            <?php if (! $overall['shifts']): ?>
                <div class="card py-14 text-center text-muted">Belum ada jadwal pada tahun pelajaran ini.</div>
            <?php endif ?>
            <?php foreach ($overall['shifts'] as $key => $sh): $n = count($sh['classes']); ?>
                <section class="ov-sheet is-flow print-landscape" aria-labelledby="ov-<?= $key ?>">
                    <div class="print-only ov-kop"><?= view('partials/kop', ['profile' => $profile]) ?></div>
                    <header class="ov-head">
                        <p class="print-only ov-doc">JADWAL PELAJARAN SEMESTER <?= esc(strtoupper($semName)) ?> TAHUN PELAJARAN <?= esc($period['year']['name'] ?? '') ?></p>
                        <h2 id="ov-<?= $key ?>" class="ov-title"><span class="ov-tag ov-tag-<?= $key ?>"><?= icon($key === 'pagi' ? 'sun' : 'moon', 'size-4') ?></span><?= esc(strtoupper($sh['title'])) ?></h2>
                        <p class="ov-sub">Kelas <?= esc(implode(', ', $sh['classes'])) ?> · pukul <?= esc(str_replace(':', '.', $sh['range'])) ?></p>
                    </header>
                    <div class="table-wrap ov-wrap">
                        <table class="ov-table">
                            <?php // Lebar kolom tetap (persen) agar tabel pas selebar kertas tanpa zoom
                            $wFix = 2.2 + 2.4 + 6.6 + 9.2; $wCls = (100 - $wFix) / max(1, $n); ?>
                            <colgroup class="ov-cols">
                                <col style="width:2.2%"><col style="width:2.4%"><col style="width:6.6%">
                                <?php for ($i = 0; $i < $n; $i++): ?><col style="width:<?= round($wCls * .4, 3) ?>%"><col style="width:<?= round($wCls * .6, 3) ?>%"><?php endfor ?>
                                <col style="width:9.2%">
                            </colgroup>
                            <caption class="sr-only"><?= esc($sh['title']) ?></caption>
                            <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="ov-day">Hari</th>
                                <th scope="col" rowspan="2" class="ov-jam">Jam</th>
                                <th scope="col" rowspan="2" class="ov-time">Pukul</th>
                                <?php foreach ($sh['classes'] as $cn): ?><th scope="colgroup" colspan="2" class="ov-cls"><?= esc($cn) ?></th><?php endforeach ?>
                                <th scope="col" rowspan="2" class="ov-piket-h">Piket</th>
                            </tr>
                            <tr><?php for ($i = 0; $i < $n; $i++): ?><th scope="col" class="ov-sh">KD</th><th scope="col" class="ov-sh">MP</th><?php endfor ?></tr>
                            </thead>
                            <?php foreach ($sh['days'] as $dnum => $day): ?>
                                <tbody class="ov-dayblock">
                                <?php foreach ($day['rows'] as $ri => $r): ?>
                                    <tr class="<?= $r['cells'] ? '' : 'ov-break' ?>">
                                        <?php if ($ri === 0): ?><th scope="rowgroup" rowspan="<?= count($day['rows']) ?>" class="ov-dayname"><span><?= esc($day['name']) ?></span></th><?php endif ?>
                                        <td class="ov-jam"><?= esc($r['jam']) ?></td>
                                        <td class="ov-time"><?= esc(str_replace(':', '.', $r['pukul'])) ?></td>
                                        <?php if (! $r['cells']): ?>
                                            <td colspan="<?= 2 * $n ?>" class="ov-rest">Istirahat</td>
                                        <?php else: ?>
                                            <?php foreach (array_keys($sh['classes']) as $cid): $c = $r['cells'][$cid] ?? null; ?>
                                                <?php if ($c): ?>
                                                    <td class="ov-kd" data-u="<?= $c['uid'] ?>" style="--mp: <?= esc($c['clr'], 'attr') ?>" title="<?= esc($c['full'], 'attr') ?>"><?= esc($c['kd'] !== '' ? $c['kd'] : '–') ?></td>
                                                    <td class="ov-mp" data-u="<?= $c['uid'] ?>" title="<?= esc($c['full'], 'attr') ?>"><b><?= esc($c['mp']) ?></b><small><?= esc($c['name']) ?></small></td>
                                                <?php else: ?>
                                                    <td class="ov-kd"></td><td class="ov-mp"></td>
                                                <?php endif ?>
                                            <?php endforeach ?>
                                        <?php endif ?>
                                        <?php if ($ri === 0): $pk = $piket[$key][$dnum] ?? []; ?>
                                            <td rowspan="<?= count($day['rows']) ?>" class="ov-piket">
                                                <?php if ($pk): ?>
                                                    <ol><?php foreach ($pk as $p): ?><li data-u="<?= $p['id'] ?>"><?= esc($p['name']) ?></li><?php endforeach ?></ol>
                                                <?php else: ?><span class="text-muted">—</span><?php endif ?>
                                            </td>
                                        <?php endif ?>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            <?php endforeach ?>
                        </table>
                    </div>
                    <div class="ov-foot">
                        <ul class="ov-legend" aria-label="Keterangan mata pelajaran">
                            <?php foreach ($overall['legend'] as $code => $lg): ?>
                                <li style="--mp: <?= esc($lg['clr'], 'attr') ?>"><b><?= esc($code) ?></b> <?= esc($lg['name']) ?></li>
                            <?php endforeach ?>
                        </ul>
                        <?= $sign() ?>
                    </div>
                </section>
            <?php endforeach ?>

            <!-- Daftar & kode guru (ikut tercetak) -->
            <section class="ov-sheet ov-codes print-only" aria-label="Daftar dan kode guru" <?= $fit ?>>
                <div class="ov-kop"><?= view('partials/kop', ['profile' => $profile]) ?></div>
                <h2 class="ov-title">DAFTAR DAN KODE GURU</h2>
                <table class="ov-table ov-codelist">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Mapel</th><th>JP</th></tr></thead>
                    <tbody>
                    <?php foreach ($overall['panel'] as $g): ?>
                        <tr><td><?= esc($g['kd']) ?></td><td><?= esc($g['name']) ?></td><td><?= esc($g['mapel']) ?></td><td><?= $g['jp'] ?></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="ov-panel no-print" aria-label="Daftar kode guru">
            <div class="ov-panel-in card p-0 sm:p-0">
                <div class="ov-panel-head">
                    <h2>Kode Guru</h2>
                    <label for="ov-find" class="sr-only">Cari guru</label>
                    <input class="input h-9 min-h-0 text-sm" type="search" id="ov-find" placeholder="Cari kode / nama…" data-ov-find>
                </div>
                <div class="ov-mini" data-ov-mini hidden></div>
                <ul class="ov-list">
                    <?php foreach ($overall['panel'] as $g): ?>
                        <li>
                            <button type="button" data-ov-guru="<?= $g['id'] ?>" data-ov-text="<?= esc(mb_strtolower($g['kd'] . ' ' . $g['name']), 'attr') ?>">
                                <span class="ov-code"><?= esc($g['kd'] !== '' ? $g['kd'] : '–') ?></span>
                                <span class="min-w-0 flex-1"><b><?= esc($g['short']) ?></b><small><?= esc($g['mapel'] ?: 'Belum ada jadwal') ?></small></span>
                                <span class="ov-jp"><?= $g['jp'] ?><small>JP</small></span>
                            </button>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </aside>
    </div>
    <script type="application/json" id="ov-data"><?= json_encode(array_column($overall['panel'], null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php elseif ($grid !== null): ?>
    <!-- Kisi jadwal guru / kelas terpilih -->
    <section class="paper print-landscape ts-sheet" aria-labelledby="ts-title" <?= $fit ?>>
        <?= view('partials/kop', ['profile' => $profile]) ?>
        <h2 id="ts-title" class="sheet-title">JADWAL MENGAJAR</h2>
        <p class="mb-1 text-center text-sm font-semibold"><?= $mode === 'guru' ? esc($selected['name']) . (! empty($selected['nip']) ? ' · NIP ' . esc($selected['nip']) : '') : 'Kelas ' . esc($selected['name']) ?></p>
        <p class="mb-4 text-center text-xs text-muted">T.P. <?= esc($period['year']['name'] ?? '') ?> · <?= $selJp ?> JP/minggu · <?= $selDays ?> hari</p>
        <?php if (! $grid): ?>
            <p class="py-10 text-center text-muted">Belum ada jadwal untuk <?= $mode === 'guru' ? 'guru' : 'kelas' ?> ini.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table ts-grid">
                    <caption class="sr-only">Jadwal mingguan <?= esc($selected['name']) ?></caption>
                    <thead><tr><?php foreach ($used as $d => $dn): ?>
                        <th scope="col" class="text-center"><?= $dn ?>
                            <?php foreach ($dayPiket[$d] ?? [] as $pk): ?><span class="ts-piket"><?= icon('shield', 'size-3') ?> <?= esc($mode === 'guru' ? $pk : 'Piket: ' . $pk) ?></span><?php endforeach ?>
                        </th>
                    <?php endforeach ?></tr></thead>
                    <tbody>
                        <tr>
                            <?php foreach (array_keys($used) as $d): ?>
                                <td>
                                    <?php if (empty($grid[$d])): ?><p class="ts-empty">Tidak ada jadwal</p><?php endif ?>
                                    <?php foreach ($grid[$d] ?? [] as $c): ?>
                                        <?php if (! empty($c['break'])): ?>
                                            <div class="ts-break"><span>Istirahat</span> <small><?= esc($c['pukul']) ?></small></div>
                                            <?php continue; ?>
                                        <?php endif ?>
                                        <div class="ts-cell" style="--mp: <?= esc($c['clr'], 'attr') ?>">
                                            <small><?= esc($c['pukul']) ?><?= $c['jam'] !== '' ? ' · Jam ' . esc($c['jam']) : '' ?></small>
                                            <b><?= esc($c['main']) ?></b>
                                            <span><?= esc($c['sub']) ?></span>
                                        </div>
                                    <?php endforeach ?>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?= $sign() ?>
        <?php endif ?>
    </section>
<?php elseif ($mode === 'kelas'): ?>
    <!-- Daftar kelas -->
    <div class="ts-classes">
        <?php foreach ($classes as $id => $n): ?>
            <a class="card ts-class" href="<?= esc($url(['mode' => 'kelas', 'kelas' => $id]), 'attr') ?>">
                <span class="u-stat-ico is-indigo"><?= icon('grid', 'size-4') ?></span><b><?= esc($n) ?></b><span class="text-xs text-muted">Lihat jadwal</span>
            </a>
        <?php endforeach ?>
    </div>
<?php else: ?>
    <!-- Ringkasan semua guru -->
    <form method="get" class="mb-3 no-print" role="search" aria-label="Cari guru" data-live-search>
        <input type="hidden" name="mode" value="guru">
        <div class="relative max-w-md">
            <label for="ts-q" class="sr-only">Cari nama atau NIP guru</label>
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input class="input h-10 min-h-0 pl-9 text-sm" type="search" id="ts-q" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama atau NIP guru…">
        </div>
    </form>
    <div data-live-region>
        <section class="card p-0 sm:p-0">
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Ringkasan jadwal per guru</caption>
                    <thead><tr><th scope="col">Guru</th><th scope="col">Mata pelajaran</th><th scope="col">Kelas</th><th scope="col" class="text-center">JP/minggu</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                    <?php if (! $list): ?>
                        <tr><td colspan="5" class="py-8 text-center text-muted">Tidak ada guru yang cocok.</td></tr>
                    <?php endif ?>
                    <?php foreach ($list as $t): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <?= user_avatar($t, 'size-9') ?>
                                    <div class="min-w-0"><strong class="block"><?= esc($t['name']) ?></strong><?php if ($t['nip']): ?><span class="text-xs text-muted tabular-nums">NIP <?= esc($t['nip']) ?></span><?php endif ?></div>
                                </div>
                            </td>
                            <td class="text-sm"><?= $t['subjects'] ? esc(implode(', ', $t['subjects'])) : '<span class="text-muted">—</span>' ?></td>
                            <td class="max-w-80 text-xs text-muted"><?= $t['classes'] ? esc(implode(', ', $t['classes'])) : '—' ?></td>
                            <td class="text-center"><?= $t['jp'] ? '<span class="badge badge-info tabular-nums">' . $t['jp'] . '</span>' : '<span class="badge badge-warning">Belum ada</span>' ?></td>
                            <td class="text-right"><a class="btn btn-ghost btn-sm whitespace-nowrap" href="<?= esc($url(['mode' => 'guru', 'guru' => $t['id']]), 'attr') ?>"><?= icon('eye', 'size-4') ?> Jadwal</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
