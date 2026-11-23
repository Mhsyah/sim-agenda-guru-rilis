<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="page-title"><?= esc($title) ?></h1>
<p class="mt-2 max-w-3xl text-muted">Backup berformat JSON portabel sehingga dapat dipulihkan pada MariaDB/MySQL maupun PostgreSQL. File disimpan di folder <code>writable/backups</code> yang tidak dapat diakses publik. Unduh dan simpan salinan di tempat aman — file berisi data pribadi siswa. Logo sekolah tidak termasuk backup; unggah ulang di Profil setelah pindah hosting.</p>

<div class="mt-6 grid gap-5 lg:grid-cols-[1.4fr_1fr]">
    <section class="card p-0 sm:p-0" aria-labelledby="bk-title">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
            <h2 id="bk-title" class="font-display text-xl font-bold">File backup</h2>
            <form method="post" action="<?= site_url('settings/backup') ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('database', 'size-4') ?> Buat backup sekarang</button></form>
        </div>
        <p class="px-5 pt-1 text-xs text-muted">Database aktif: <?= esc($driver === 'Postgre' ? 'PostgreSQL' : 'MariaDB/MySQL') ?></p>
        <?php if (! $backups): ?>
            <p class="p-5 text-sm text-muted">Belum ada backup.</p>
        <?php else: ?>
            <div class="table-wrap mt-3">
                <table class="table">
                    <caption>Daftar file backup</caption>
                    <thead><tr><th scope="col">File</th><th scope="col" class="num">Ukuran</th><th scope="col">Dibuat</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= esc($b['name']) ?></td>
                            <td class="num"><?= fmt_num($b['size'] / 1024, 1) ?> KB</td>
                            <td class="whitespace-nowrap"><?= fmt_date(date('Y-m-d', $b['time'])) ?> <?= date('H:i', $b['time']) ?></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a class="btn btn-ghost btn-sm" href="<?= site_url('settings/backup/download/' . $b['name']) ?>"><?= icon('download', 'size-4') ?> Unduh</a>
                                    <form method="post" action="<?= site_url('settings/restore') ?>" data-progress="Memulihkan backup" data-confirm="Pulihkan data dari <?= esc($b['name'], 'attr') ?>? Semua data saat ini akan DIGANTI. Backup pengaman dibuat otomatis sebelum pemulihan." data-confirm-label="Ya, pulihkan">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="backup_name" value="<?= esc($b['name'], 'attr') ?>">
                                        <input type="hidden" name="confirm_text" value="PULIHKAN">
                                        <button class="btn btn-ghost btn-sm"><?= icon('upload', 'size-4') ?> Pulihkan</button>
                                    </form>
                                    <form method="post" action="<?= site_url('settings/backup/delete/' . $b['name']) ?>" data-confirm="Hapus file backup ini secara permanen?" data-confirm-label="Ya, hapus">
                                        <?= csrf_field() ?><button class="btn btn-ghost btn-sm" aria-label="Hapus <?= esc($b['name'], 'attr') ?>"><?= icon('trash', 'size-4') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="card card-organic" aria-labelledby="rs-title">
        <h2 id="rs-title" class="font-display text-xl font-bold">Pulihkan dari file</h2>
        <p class="mt-1 text-sm text-muted">Unggah file backup Agenda Guru (.json). Seluruh data saat ini akan diganti dalam satu transaksi; bila gagal, data lama tidak berubah.</p>
        <form method="post" action="<?= site_url('settings/restore') ?>" enctype="multipart/form-data" data-progress="Memulihkan backup" class="mt-4 grid gap-4">
            <?= csrf_field() ?>
            <div class="field">
                <label class="label" for="backup_file">File backup <span class="req" aria-hidden="true">*</span></label>
                <input class="input py-2" type="file" id="backup_file" name="backup_file" accept=".json,application/json" required>
            </div>
            <div class="field">
                <label class="label" for="confirm_text">Ketik <strong>PULIHKAN</strong> untuk konfirmasi <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="confirm_text" name="confirm_text" required autocomplete="off">
            </div>
            <div><button class="btn btn-danger"><?= icon('upload', 'size-4') ?> Pulihkan data</button></div>
        </form>
        <h3 class="mt-6 font-display text-base font-bold">Prosedur restore yang disarankan</h3>
        <ol class="mt-2 list-decimal pl-5 text-sm text-muted">
            <li>Uji pemulihan secara berkala di salinan/lokasi nonproduksi.</li>
            <li>Sistem membuat backup pengaman otomatis sebelum restore.</li>
            <li>Setelah restore, login ulang bila akun berubah.</li>
            <li>Periksa Log Audit untuk memastikan tindakan tercatat.</li>
        </ol>
    </section>
</div>
<?= $this->endSection() ?>
