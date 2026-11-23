<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$glow = 'h-11 w-full rounded-xl border bg-surface pl-11 pr-4 text-[0.95rem] text-ink outline-none transition-all duration-200';
$err  = static fn (string $k) => isset($errors[$k]);
?>
<div class="w-full max-w-[26rem]">
    <!-- Brand sekolah -->
    <div class="mx-auto mb-8 flex w-fit items-center gap-3">
        <?= brand_mark($profile ?? null, 'size-11') ?>
        <div class="leading-tight">
            <p class="text-lg font-bold tracking-tight">Manajemen Agenda Guru</p>
            <p class="text-sm text-muted"><?= esc($profile['school_name'] ?? 'Sekolah Anda') ?></p>
        </div>
    </div>

    <section aria-labelledby="login-title" class="rounded-2xl border border-line bg-surface/90 p-6 shadow-xl shadow-slate-900/[0.04] backdrop-blur-sm sm:p-8">
        <header class="mb-6 text-center">
            <h1 id="login-title" class="text-2xl font-bold tracking-tight">Selamat datang kembali</h1>
            <p class="mt-1.5 text-sm text-muted">Masuk untuk mengelola agenda, kehadiran, dan penilaian.</p>
        </header>

        <form method="post" action="<?= site_url('login') ?>" class="space-y-5" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="theme" value="" data-theme-field>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold">Email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true">
                        <svg class="size-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    </span>
                    <input class="input <?= $glow ?>" type="email" id="email" name="email" value="<?= esc(old('email', '', false), 'attr') ?>"
                           required autocomplete="username" autofocus placeholder="nama@sekolah.sch.id"
                        <?= $err('email') ? 'aria-invalid="true" aria-describedby="email-err"' : '' ?>>
                </div>
                <?php if ($err('email')): ?><p class="error-text mt-1.5" id="email-err"><?= esc($errors['email']) ?></p><?php endif ?>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-semibold">Kata sandi</label>
                    <details class="group relative" data-dropdown>
                        <summary class="cursor-pointer rounded-md text-sm font-semibold text-link transition-all duration-200 hover:underline hover:underline-offset-4">Lupa kata sandi?</summary>
                        <div class="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-line bg-surface p-3 text-xs text-muted shadow-xl" role="note">
                            Hubungi <strong class="text-ink">admin teknis</strong> sekolah. Admin dapat mengatur ulang kata sandi Anda di menu <em>Data Guru &amp; Pengguna</em>.
                        </div>
                    </details>
                </div>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true">
                        <svg class="size-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <input class="input <?= $glow ?>" type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••"
                        <?= $err('password') ? 'aria-invalid="true" aria-describedby="password-err"' : '' ?>>
                </div>
                <?php if ($err('password')): ?><p class="error-text mt-1.5" id="password-err"><?= esc($errors['password']) ?></p><?php endif ?>
            </div>

            <label for="remember" class="group flex w-fit cursor-pointer select-none items-center gap-2.5 text-sm text-muted">
                <input id="remember" type="checkbox" data-remember class="size-[1.125rem] cursor-pointer rounded-md accent-indigo-600 transition-all duration-200">
                <span class="transition-all duration-200 group-hover:text-ink">Ingat email di perangkat ini</span>
            </label>

            <button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold tracking-wide text-primary-ink shadow-lg shadow-slate-900/20 transition-all duration-200 hover:-translate-y-px hover:shadow-xl active:translate-y-0 active:scale-[0.99]">
                Masuk
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <p class="mt-5 text-center text-xs text-muted">Demi keamanan, login dikunci 5 menit setelah 5 kali gagal.</p>
    </section>

    <div class="auth-chip mx-auto mt-6 flex w-fit items-center justify-center gap-1.5 text-xs text-muted" role="group" aria-label="Tema tampilan">
        <span class="mr-1">Tema:</span>
        <button type="button" class="btn btn-sm border-transparent bg-transparent shadow-none hover:bg-surface aria-pressed:bg-surface aria-pressed:shadow-sm" data-theme-set="light" aria-pressed="false"><?= icon('sun', 'size-3.5') ?> Terang</button>
        <button type="button" class="btn btn-sm border-transparent bg-transparent shadow-none hover:bg-surface aria-pressed:bg-surface aria-pressed:shadow-sm" data-theme-set="dark" aria-pressed="false"><?= icon('moon', 'size-3.5') ?> Gelap</button>
        <button type="button" class="btn btn-sm border-transparent bg-transparent shadow-none hover:bg-surface aria-pressed:bg-surface aria-pressed:shadow-sm" data-theme-set="system" aria-pressed="false"><?= icon('monitor', 'size-3.5') ?> Sistem</button>
    </div>

    <footer class="auth-chip auth-foot mx-auto mt-6 w-fit text-center text-xs text-muted"><?= app_footer() ?></footer>
</div>
<?= $this->endSection() ?>
