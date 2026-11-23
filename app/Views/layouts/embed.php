<?php
/**
 * Tata letak ringkas untuk formulir yang dibuka di dalam popup (iframe) — tanpa sidebar & header.
 * Dipakai formulir Agenda & Jurnal dari Kalender.
 *
 * @var array|null $authUser
 */
$pref  = $authUser['theme_pref'] ?? '';
$asset = static fn (string $p) => base_url('assets/' . $p) . '?v=' . (is_file(FCPATH . 'assets/' . $p) ? filemtime(FCPATH . 'assets/' . $p) : '1');
?>
<!doctype html>
<html lang="id" data-pref="<?= esc($pref, 'attr') ?>" data-theme="system" class="is-embed"
      data-csrf-name="<?= csrf_token() ?>" data-csrf-hash="<?= csrf_hash() ?>" data-theme-url="">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?= isset($title) ? esc($title) . ' · Agenda Guru' : 'Agenda Guru' ?></title>
    <script>
        (function () {
            try {
                var d = document.documentElement;
                var t = d.getAttribute('data-pref') || localStorage.getItem('ag-theme') || 'system';
                if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) d.classList.add('dark');
                d.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
</head>
<body class="embed-body">
<div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
<main id="main" class="embed-main">
    <?= view('partials/flash') ?>
    <?= $this->renderSection('content') ?>
</main>
<dialog id="confirm-dialog" class="m-auto w-[min(92vw,28rem)] rounded-2xl border border-line bg-surface p-6 text-ink shadow-2xl backdrop:bg-slate-900/40" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <h2 id="confirm-title" class="text-lg font-semibold">Konfirmasi</h2>
    <p id="confirm-message" class="mt-2 text-sm text-muted"></p>
    <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="btn btn-ghost" id="confirm-no">Batal</button>
        <button type="button" class="btn btn-danger" id="confirm-yes">Ya, lanjutkan</button>
    </div>
</dialog>
<script src="<?= $asset('js/app.js') ?>" defer></script>
</body>
</html>
