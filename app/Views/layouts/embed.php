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
