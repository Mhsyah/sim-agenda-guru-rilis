<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title ?? 'Agenda Guru') ?></title>
    <script>try{var t=localStorage.getItem('ag-theme')||'system';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?: 1 ?>">
    <?= favicon_tags(is_file(WRITEPATH . 'installed.lock') ? safe_profile() : null) ?>
</head>
<body>
<a href="#main" class="skip-link">Lewati ke konten utama</a>
<div class="blob-field" aria-hidden="true"></div>
<main id="main" class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-14">
    <div class="mb-8 flex items-center gap-3">
        <span class="grid size-11 place-items-center rounded-[1rem_1.4rem_1rem_1.6rem] bg-accent font-display text-lg font-extrabold text-accent-ink" aria-hidden="true">AG</span>
        <div><p class="font-display text-lg font-extrabold">Manajemen Agenda Guru</p><p class="text-xs text-muted">Create by @yhsyah · v0.2</p></div>
    </div>
    <?= view('partials/flash') ?>
    <?= $this->renderSection('content') ?>
</main>
<footer class="border-t border-line px-6 py-5 text-center text-xs text-muted"><?= app_footer() ?></footer>
</body>
</html>
