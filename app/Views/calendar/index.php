<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
[$yy, $mm] = explode('-', $month);
$monthLabel = id_months()[(int) $mm] . ' ' . $yy;
$tone = static fn ($t) => $t === 'libur' ? 'danger' : ($t === 'ujian' ? 'warning' : 'info');
$today = date('Y-m-d');
/** Status kegiatan: Akan datang / Berlangsung / Selesai. */
$status = static function (array $e) use ($today): array {
    if ($today < $e['start_date']) {
        $n = (int) round((strtotime($e['start_date']) - strtotime($today)) / 86400);

        return ['Akan datang', 'info', $n === 1 ? 'besok' : $n . ' hari lagi'];
    }
    if ($today <= $e['end_date']) {
        $left = (int) round((strtotime($e['end_date']) - strtotime($today)) / 86400);

        return ['Berlangsung', 'success', $left === 0 ? 'berakhir hari ini' : 'sisa ' . ($left + 1) . ' hari'];
    }

    return ['Selesai', 'neutral', null];
};
$days = static fn (array $e) => (int) round((strtotime($e['end_date']) - strtotime($e['start_date'])) / 86400) + 1;
$teach = $teach ?? [];
/** Status agenda guru pada satu tanggal: [kelas css, teks singkat, keterangan]. */
$agState = static function (string $date, array $t) use ($today): array {
    if (! $t['total']) {
        return ['is-ok', count($t['extra']) . ' agenda', count($t['extra']) . ' agenda di luar jadwal'];
    }
    $txt = $t['done'] . '/' . $t['total'];
    if ($t['done'] >= $t['total']) {
        return ['is-ok', $txt, 'Semua agenda sudah ditulis'];
    }
    if ($date > $today && $t['done'] === 0) {
        return ['is-plan', $t['total'] . ' jadwal', 'Jadwal mengajar · klik untuk menulis agenda'];
    }
    if ($t['done'] > 0 || $date === $today) {
        return ['is-part', $txt, ($t['total'] - $t['done']) . ' agenda belum ditulis'];
    }

    return ['is-miss', $txt, 'Agenda belum ditulis'];
};
$history = $history ?? [];
$agSum   = ['is-ok' => 0, 'is-part' => 0, 'is-miss' => 0, 'is-plan' => 0];
$jpDone  = 0;
$jpDue   = 0;
foreach ($teach as $date => $t) {
    if (! $t['total'] && ! $t['extra']) {
        continue; // hanya kegiatan di luar KBM
    }
    $agSum[$agState($date, $t)[0]]++;
    if ($date <= $today) {
        $jpDone += $t['done'];
        $jpDue  += $t['total'];
    }
}
$agInfo = [
    'is-ok'   => ['Lengkap', 'semua agenda hari itu sudah ditulis', '2/2'],
    'is-part' => ['Sebagian', 'baru sebagian, atau hari ini masih ada yang belum ditulis', '1/3'],
    'is-miss' => ['Belum ditulis', 'tanggal sudah lewat tetapi agendanya belum ditulis', '0/2'],
    'is-plan' => ['Akan datang', 'tanggal yang akan datang', '2 jadwal'],
];
?>
<?php if (! $canEdit): ?><?= view('partials/agenda_tabs', ['active' => 'calendar']) ?><?php endif ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($canEdit ? $title : 'Kalender & Agenda') ?></h1>
        <p class="mt-2 text-muted">Tahun ajaran <?= esc($year['name'] ?? '') ?>. Verifikasi tanggal dengan kalender resmi sekolah.</p>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        <a class="btn btn-ghost" href="<?= site_url('calendar?view=list') ?>"><?= icon('list', 'size-4') ?> Tampilan daftar</a>
        <button type="button" class="btn btn-ghost" data-print><?= icon('printer', 'size-4') ?> Cetak</button>
        <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= site_url('calendar/new') ?>"><?= icon('plus', 'size-4') ?> Tambah kegiatan</a><?php endif ?>
    </div>
</div>

<?php if ($teach !== [] || ! $canEdit): ?>
    <p class="alert alert-info mb-4 no-print"><?= icon('pen', 'size-5 shrink-0') ?>
        <span><strong>Isi Agenda &amp; Jurnal dari kalender:</strong> klik kotak tanggal → pilih jadwal → formulir terbuka di popup. Kalender langsung diperbarui setelah disimpan.</span></p>
<?php endif ?>
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
<div class="card min-w-0 p-4 sm:p-6">
    <div class="mb-4 flex items-center justify-between gap-2">
        <a class="btn btn-ghost btn-icon" href="<?= site_url('calendar?month=' . $prev) ?>" aria-label="Bulan sebelumnya"><?= icon('chevron-left') ?></a>
        <h2 class="font-display text-2xl font-extrabold" aria-live="polite"><?= esc($monthLabel) ?></h2>
        <a class="btn btn-ghost btn-icon" href="<?= site_url('calendar?month=' . $next) ?>" aria-label="Bulan berikutnya"><?= icon('chevron-right') ?></a>
    </div>

    <!-- Legenda warna -->
    <ul class="cal-legend mb-3" aria-label="Keterangan warna">
        <?php foreach (event_types() as $k => $l): ?><li><span class="cal-dot" style="--ev:<?= event_type_colors()[$k] ?>"></span><?= esc($l) ?></li><?php endforeach ?>
    </ul>

    <!-- Grid bulanan: kegiatan tampil di dalam tanggal dengan warnanya (daftar di bawah = alternatif pembaca layar) -->
    <div class="cal" <?= $canEdit ? 'aria-hidden="true"' : '' ?>>
        <div class="cal-grid cal-head">
            <?php foreach (day_names() as $i => $dn): ?><div class="<?= $i === 7 ? 'is-sun' : '' ?>"><?= substr($dn, 0, 3) ?></div><?php endforeach ?>
        </div>
        <?php foreach ($weeks as $week): ?>
            <div class="cal-grid cal-week">
                <?php foreach ($week as $day):
                    $isToday = $day['date'] === date('Y-m-d');
                    $dow     = (int) date('N', strtotime($day['date']));
                    $holiday = array_filter($day['events'], static fn ($e) => $e['event_type'] === 'libur');
                    $cls     = ['cal-day', $day['in'] ? '' : 'is-out', $dow >= 6 ? 'is-weekend' : '', $dow === 7 || $holiday ? 'is-red' : '', $isToday ? 'is-today' : '']; ?>
                    <div class="<?= implode(' ', array_filter($cls)) ?><?= ! $canEdit && $day['in'] ? ' is-clickable' : '' ?>" <?= ! $canEdit && $day['in'] ? 'data-cal-day="' . $day['date'] . '"' : '' ?>>
                        <?php if ($canEdit && $day['in']): ?>
                            <a class="cal-num" href="<?= site_url('calendar/new?start_date=' . $day['date'] . '&end_date=' . $day['date']) ?>" tabindex="-1" title="Tambah kegiatan <?= esc(fmt_date($day['date']), 'attr') ?>"><?= (int) substr($day['date'], 8) ?></a>
                        <?php elseif (! $canEdit && $day['in']): ?>
                            <a class="cal-num" href="<?= site_url('agendas/new?agenda_date=' . $day['date']) ?>" data-cal-day="<?= $day['date'] ?>"
                               title="Tulis agenda <?= esc(fmt_date($day['date'], true), 'attr') ?>" aria-label="<?= esc(fmt_date($day['date'], true), 'attr') ?>: jadwal & agenda"><?= (int) substr($day['date'], 8) ?></a>
                        <?php else: ?>
                            <span class="cal-num"><?= (int) substr($day['date'], 8) ?></span>
                        <?php endif ?>
                        <?php if ($day['in'] && isset($teach[$day['date']]) && ($teach[$day['date']]['total'] || $teach[$day['date']]['extra'])): [$agCls, $agTxt, $agTip] = $agState($day['date'], $teach[$day['date']]); ?>
                            <button type="button" class="cal-ag <?= $agCls ?>" data-cal-day="<?= $day['date'] ?>" title="<?= esc($agTip, 'attr') ?>">
                                <?= icon('pen', 'size-3') ?> <?= esc($agTxt) ?><span class="sr-only"> — <?= esc($agTip) ?></span></button>
                        <?php endif ?>
                        <?php if ($day['in'] && ! empty($teach[$day['date']]['acts'])): $nAct = count($teach[$day['date']]['acts']); ?>
                            <button type="button" class="cal-act" data-cal-day="<?= $day['date'] ?>" title="<?= esc(implode(' · ', array_column($teach[$day['date']]['acts'], 'title')), 'attr') ?>">
                                <?= icon('sparkle', 'size-3') ?> <?= $nAct ?> kegiatan<span class="sr-only"> di luar KBM</span></button>
                        <?php endif ?>
                        <div class="cal-events">
                            <?php foreach (array_slice($day['events'], 0, 3) as $e):
                                $starts = $e['start_date'] === $day['date'] || $dow === 1 || substr($day['date'], 8) === '01';
                                $multi  = $e['start_date'] !== $e['end_date']; ?>
                                <?php $tag = $canEdit ? 'a' : 'span'; ?>
                                <<?= $tag ?> class="cal-ev<?= $multi ? ' is-multi' : '' ?>" style="--ev:<?= esc(event_color($e), 'attr') ?>"
                                   <?= $canEdit ? 'href="' . site_url('calendar/' . $e['id'] . '/edit') . '" tabindex="-1"' : '' ?>
                                   title="<?= esc($e['title'] . ' · ' . (event_types()[$e['event_type']] ?? '') . ($multi ? ' · ' . fmt_date($e['start_date']) . ' – ' . fmt_date($e['end_date']) : ''), 'attr') ?>">
                                    <span class="cal-ev-text"><?= ! $starts ? '<span class="cal-ev-cont" aria-hidden="true">↳</span> ' : '' ?><?= esc($e['title']) ?></span>
                                </<?= $tag ?>>
                            <?php endforeach ?>
                            <?php if (count($day['events']) > 3): ?><span class="cal-more">+<?= count($day['events']) - 3 ?> lagi</span><?php endif ?>
                        </div>
                        <?php if ($day['events']): ?>
                            <span class="cal-dots"><?php foreach (array_slice($day['events'], 0, 4) as $e): ?><i style="--ev:<?= esc(event_color($e), 'attr') ?>"></i><?php endforeach ?></span>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endforeach ?>
    </div>

</div>

<div class="grid gap-4">
<?php if (! $canEdit): ?>
    <section class="card p-4 sm:p-5" aria-labelledby="ag-st-title">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h2 id="ag-st-title" class="text-[0.95rem] font-semibold tracking-tight">Status agenda</h2>
            <span class="text-xs text-muted"><?= esc($monthLabel) ?></span>
        </div>
        <?php if ($jpDue): ?>
            <div class="mb-3">
                <div class="flex justify-between text-xs"><span class="text-muted">Agenda ditulis s.d. hari ini</span><b class="tabular-nums"><?= $jpDone ?>/<?= $jpDue ?></b></div>
                <div class="ag-prog" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $jpDue ?>" aria-valuenow="<?= $jpDone ?>"><span style="width: <?= round($jpDone / $jpDue * 100) ?>%"></span></div>
            </div>
        <?php endif ?>
        <ul class="ag-legend">
            <?php foreach ($agInfo as $cls => [$name, $desc, $sample]): ?>
                <li>
                    <span class="cal-ag <?= $cls ?>" aria-hidden="true"><?= icon('pen', 'size-3') ?> <?= $sample ?></span>
                    <span class="min-w-0 flex-1"><b><?= $name ?></b><small><?= $desc ?></small></span>
                    <span class="ag-n"><?= $agSum[$cls] ?><small>hari</small></span>
                </li>
            <?php endforeach ?>
        </ul>
    </section>

    <section class="card p-4 sm:p-5" aria-labelledby="ag-hist-title">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h2 id="ag-hist-title" class="text-[0.95rem] font-semibold tracking-tight">Riwayat agenda</h2>
            <a class="text-xs font-semibold" href="<?= site_url('agendas') ?>">Lihat semua</a>
        </div>
        <?php if (! $history): ?>
            <div class="rounded-xl border border-dashed border-line p-5 text-center text-sm text-muted">Belum ada agenda yang ditulis.</div>
        <?php else: ?>
            <ol class="act-feed">
                <?php foreach ($history as $h): [$hAt, $hAgo] = \App\Libraries\ActivityFeed::when(gmdate('Y-m-d H:i:s', strtotime((string) ($h['updated_at'] ?: $h['created_at'])))); ?>
                    <li class="act-item is-success">
                        <span class="act-dot" aria-hidden="true"><?= icon('pen', 'size-3.5') ?></span>
                        <div class="min-w-0">
                            <p class="act-text"><a href="<?= site_url('agendas/' . $h['id'] . '/edit') ?>"><?= esc($h['class_name']) ?> · <?= esc($h['subject_name']) ?></a></p>
                            <p class="act-detail"><?= esc($h['topic'] ?: '—') ?></p>
                            <span class="act-time"><?= fmt_date($h['agenda_date'], false, true) ?><?= $h['lesson_hours'] ? ' · Jam ' . esc($h['lesson_hours']) : '' ?> · diisi <?= esc($hAgo) ?></span>
                        </div>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </section>
<?php endif ?>
<aside class="card p-4 sm:p-5" aria-labelledby="ev-list-title">
    <div class="mb-3 flex items-baseline justify-between gap-2">
        <h2 id="ev-list-title" class="text-[0.95rem] font-semibold tracking-tight">Daftar kegiatan</h2>
        <span class="text-xs text-muted"><?= esc($monthLabel) ?> · <?= count($events) ?> kegiatan</span>
    </div>
    <?php if (! $events): ?>
        <div class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-muted">Belum ada kegiatan di bulan ini.</div>
    <?php else: ?>
        <ul class="ev-list">
            <?php foreach ($events as $e): [$stLabel, $stTone, $stNote] = $status($e); ?>
                <li class="ev-item<?= $stLabel === 'Selesai' ? ' is-done' : '' ?>" style="--ev:<?= esc(event_color($e), 'attr') ?>">
                    <div class="min-w-0 flex-1">
                        <p class="ev-title"><?= esc($e['title']) ?></p>
                        <p class="ev-date"><?= icon('calendar', 'size-3.5') ?> <?= fmt_date($e['start_date'], false, true) ?><?= $e['end_date'] !== $e['start_date'] ? ' – ' . fmt_date($e['end_date']) . ' · ' . $days($e) . ' hari' : '' ?></p>
                        <p class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span class="cal-chip" style="--ev:<?= esc(event_color($e), 'attr') ?>"><?= esc(event_types()[$e['event_type']] ?? $e['event_type']) ?></span>
                            <span class="badge badge-<?= $stTone ?>"><?= $stLabel ?></span>
                            <?php if ($stNote): ?><span class="text-xs text-muted"><?= esc($stNote) ?></span><?php endif ?>
                        </p>
                        <?php if (! empty($e['notes'])): ?><p class="mt-1 text-xs text-muted"><?= excerpt($e['notes'], 90) ?></p><?php endif ?>
                    </div>
                    <?php if ($canEdit): ?><a class="btn btn-ghost btn-sm btn-icon size-8 no-print" href="<?= site_url('calendar/' . $e['id'] . '/edit') ?>" title="Ubah"><?= icon('edit', 'size-4') ?><span class="sr-only">Ubah <?= esc($e['title']) ?></span></a><?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</aside>
</div>
</div>

<?php if (! $canEdit): ?>
    <!-- Panel per tanggal (guru): jadwal hari itu + tulis agenda -->
    <dialog class="cal-dlg" id="cal-day-dlg" aria-labelledby="cal-dlg-title">
        <div class="cal-dlg-head">
            <div class="flex min-w-0 items-center gap-2">
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-cal-back hidden aria-label="Kembali ke daftar agenda hari ini"><?= icon('chevron-left', 'size-4') ?></button>
                <div class="min-w-0">
                    <p class="text-xs text-muted" data-cal-kicker>Agenda &amp; Jurnal Mengajar</p>
                    <h2 id="cal-dlg-title" class="flex flex-wrap items-center gap-2 text-lg font-bold"></h2>
                </div>
                <span class="cal-dlg-state" data-cal-state></span>
            </div>
            <div class="flex items-center gap-2">
                <details class="cal-add-menu" data-cal-other data-dropdown>
                    <summary class="btn btn-primary btn-sm"><?= icon('plus', 'size-4') ?> Tambah <?= icon('chevron-down', 'size-3.5') ?></summary>
                    <div class="cal-add-pop">
                        <a href="#" data-cal-form data-cal-add="agenda"><?= icon('pen', 'size-4 shrink-0') ?><span><b>Agenda mengajar</b><small>Agenda &amp; jurnal KBM, termasuk di luar jadwal (mis. jam pengganti)</small></span></a>
                        <a href="#" data-cal-form data-cal-add="activity"><?= icon('sparkle', 'size-4 shrink-0') ?><span><b>Kegiatan lain</b><small>Di luar KBM: rapat, upacara, piket, pelatihan, kepanitiaan, …</small></span></a>
                    </div>
                </details>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-cal-close aria-label="Tutup"><?= icon('x', 'size-4') ?></button>
            </div>
        </div>
        <div class="cal-dlg-body" data-cal-body></div>
        <div class="cal-dlg-frame" data-cal-framewrap hidden>
            <iframe title="Formulir Agenda &amp; Jurnal Mengajar" data-cal-frame></iframe>
        </div>
        <div class="cal-dlg-foot" data-cal-foot>
            <ul class="cal-dlg-legend" aria-label="Keterangan">
                <li><i class="cal-st is-done"></i> Sudah diisi</li><li><i class="cal-st is-miss"></i> Belum diisi (sudah lewat)</li><li><i class="cal-st is-plan"></i> Akan datang</li><li><i class="cal-st is-act"></i> Kegiatan di luar KBM</li>
            </ul>
        </div>
    </dialog>
    <script type="application/json" id="cal-teach"><?= json_encode(['days' => $teach, 'events' => array_map(static fn ($d) => array_map(static fn ($e) => ['t' => $e['title'], 'k' => $e['event_type']], $d), array_map(static fn ($w) => $w, array_column(array_merge(...$weeks), 'events', 'date'))),
        'newUrl' => site_url('agendas/new'), 'actUrl' => site_url('activities/new')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif ?>
<?= $this->endSection() ?>
