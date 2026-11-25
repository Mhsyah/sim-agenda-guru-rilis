<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman tidak ditemukan · Agenda Guru</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?: 1 ?>">
    <?= favicon_tags(safe_profile()) ?>
    <script>try{var t=localStorage.getItem('ag-theme')||'system';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
</head>
<body>
<div class="blob-field" aria-hidden="true"></div>
<main class="grid min-h-dvh place-items-center p-6">
    <div class="card card-organic glass max-w-md text-center">
        <p class="hero-title">404</p>
        <h1 class="mt-2 font-display text-2xl font-bold">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-muted">Tautan mungkin salah atau data sudah diarsipkan.</p>
        <a class="btn btn-primary mt-5" href="<?= site_url('/') ?>">Kembali ke dashboard</a>
    </div>
</main>
<footer class="px-6 pb-6 text-center text-xs text-muted">Copyright © <?= date('Y') ?> Manajemen Agenda Guru. Hak cipta dilindungi. · Create by @yhsyah</footer>
</body>
</html>
