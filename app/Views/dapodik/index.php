<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\Dapodik;

/** @var array $set  @var bool $hasToken */
$res   = session('dapodik_result');
$ready = $set['url'] !== '' && $set['npsn'] !== '' && $hasToken;
?>
<div class="mb-5">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-1.5 max-w-3xl text-sm text-muted">Samakan data GTK, peserta didik, dan rombel Agenda Guru dengan Dapodik. Setiap sinkron selalu
        <strong class="text-ink">dipratinjau dulu</strong>; Anda memilih baris yang diterapkan, dan data tidak pernah dihapus otomatis.
        Tahun pelajaran aktif: <strong class="text-ink"><?= esc($period['year']['name'] ?? '—') ?></strong>.</p>
</div>

<?php if ($res && ! empty($res['credentials'])): ?>
    <div class="alert alert-warning mb-4" role="alert"><?= icon('shield', 'size-5 shrink-0') ?>
        <div><p class="font-semibold">Akun guru baru (kata sandi awal, tampil sekali). Minta guru menggantinya setelah login.</p>
            <ul class="mt-1 text-sm"><?php foreach ($res['credentials'] as [$e, $p]): ?><li><code><?= esc($e) ?></code> · <code><?= esc($p) ?></code></li><?php endforeach ?></ul></div>
    </div>
<?php endif ?>
<?php if ($res && ! empty($res['skipped'])): ?>
    <details class="card mb-4 text-sm"><summary class="cursor-pointer font-semibold"><?= count($res['skipped']) ?> baris dilewati</summary>
        <ul class="mt-2 list-disc pl-5 text-muted"><?php foreach ($res['skipped'] as $sk): ?><li><?= esc($sk) ?></li><?php endforeach ?></ul></details>
<?php endif ?>

<!-- Status per jenis data -->
<ul class="dp-kinds mb-5" aria-label="Status sinkron per data">
    <?php foreach (Dapodik::KINDS as $k => $meta): $last = $set['last'][$k] ?? null; $c = $last['counts'] ?? null; $diffN = $c ? $c['new'] + $c['changed'] : null; ?>
        <li class="dp-kind">
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-sm font-bold"><?= esc($meta['label']) ?></h2>
                <?= $c === null ? badge('Belum pernah', 'neutral') : ($diffN ? badge($diffN . ' selisih', 'warning') : badge('Sesuai', 'success')) ?>
            </div>
            <?php if ($c): ?>
                <p class="mt-1 text-xs text-muted">Dicek <?= fmt_date(date('Y-m-d', (int) $last['time'])) ?> <?= date('H:i', (int) $last['time']) ?> · <?= esc($last['source']) ?></p>
                <p class="mt-1 text-xs"><?= $c['new'] ?> baru · <?= $c['changed'] ?> berubah · <?= $c['missing'] ?> tidak ada di Dapodik · <?= $c['same'] ?> sama</p>
                <?php if (! empty($last['applied'])): ?><p class="mt-1 text-xs text-ok">Diterapkan <?= fmt_date(date('Y-m-d', (int) $last['applied']['time'])) ?>: <?= (int) $last['applied']['created'] ?> baru, <?= (int) $last['applied']['updated'] ?> diperbarui</p><?php endif ?>
            <?php endif ?>
            <div class="mt-3 grid gap-2">
                <form method="post" action="<?= site_url('dapodik/pull/' . $k) ?>" data-progress="Menarik data <?= esc(mb_strtolower($meta['label']), 'attr') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-sm w-full justify-center" <?= $ready ? '' : 'disabled title="Isi pengaturan Web Service dulu"' ?>><?= icon('zap', 'size-4') ?> Tarik dari Web Service</button>
                </form>
                <form method="post" action="<?= site_url('dapodik/upload/' . $k) ?>" enctype="multipart/form-data" class="dp-up" data-progress="Membaca berkas Dapodik">
                    <?= csrf_field() ?>
                    <label class="imp-drop"><?= icon('upload', 'size-4') ?><span><b data-imp-fname>Unggah ekspor Dapodik</b><small>.xlsx / .csv</small></span>
                        <input class="sr-only" type="file" name="file" accept=".xlsx,.csv" required data-imp-file></label>
                    <button class="btn btn-ghost btn-sm"><?= icon('search', 'size-4') ?> Periksa berkas</button>
                </form>
            </div>
        </li>
    <?php endforeach ?>
</ul>
<p class="mb-5 text-xs text-muted">Urutan yang disarankan: <strong>Rombel → GTK → Peserta didik</strong> (siswa ditempatkan ke rombel yang sudah ada).</p>

<div class="grid gap-5 lg:grid-cols-2">
    <form method="post" action="<?= site_url('dapodik/settings') ?>" class="card grid content-start gap-4" autocomplete="off">
        <?= csrf_field() ?>
        <h2 class="text-sm font-bold">Pengaturan Web Service Dapodik</h2>
        <div class="field">
            <label class="label" for="dp-url">Alamat Web Service</label>
            <input class="input" id="dp-url" name="url" value="<?= esc($set['url'], 'attr') ?>" placeholder="http://192.168.1.10:5774">
            <p class="help">Alamat komputer/server Dapodik di jaringan sekolah (port bawaan 5774).</p>
        </div>
        <div class="field">
            <label class="label" for="dp-npsn">NPSN</label>
            <input class="input" id="dp-npsn" name="npsn" inputmode="numeric" maxlength="8" value="<?= esc($set['npsn'] ?: (string) ($profile['npsn'] ?? ''), 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="dp-token">Token Web Service</label>
            <input class="input" id="dp-token" name="token" type="password" placeholder="<?= $hasToken ? '•••••••• (tersimpan — isi untuk mengganti)' : 'Tempel token dari Dapodik' ?>" autocomplete="new-password">
            <p class="help">Disimpan terenkripsi dan tidak ditampilkan lagi.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan</button>
            <?php if ($hasToken): ?><label class="check text-sm"><input type="checkbox" name="forget" value="1"> Hapus token</label><?php endif ?>
        </div>
    </form>

    <section class="card text-sm" aria-labelledby="dp-help">
        <h2 id="dp-help" class="mb-2 text-sm font-bold">Cara menghubungkan</h2>
        <ol class="ss-ver-steps !text-sm">
            <li>Di aplikasi <b>Dapodik</b> (login operator): menu <b>Pengaturan → Web Service</b>, tambahkan IP komputer yang menjalankan Agenda Guru, lalu salin <b>token</b>.</li>
            <li>Isi alamat, NPSN, dan token di formulir ini, lalu Simpan.</li>
            <li>Klik <b>Tarik dari Web Service</b> per jenis data, periksa pratinjau, centang baris yang benar, lalu <b>Terapkan</b>.</li>
        </ol>
        <p class="mt-3 rounded-lg bg-surface-2 p-3 text-xs text-muted"><b class="text-ink">Aplikasi di hosting internet?</b> Web Service Dapodik hanya dapat diakses dari jaringan sekolah.
            Gunakan <b>Unggah ekspor Dapodik</b>: di Dapodik unduh daftar GTK/peserta didik/rombel (Excel), lalu unggah di kartu di atas.</p>
        <p class="mt-2 text-xs text-muted">Yang diambil hanya: nama, NIP, NIS/NISN, L/P, tanggal lahir, rombel, tingkat. NIK, alamat, dan data orang tua tidak diambil.</p>
    </section>
</div>
<?= $this->endSection() ?>
