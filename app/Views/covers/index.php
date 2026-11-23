<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var bool       $isAdmin
 * @var string|null $ownUrl  blanko yang sedang diatur (admin: default sekolah; guru: milik sendiri)
 * @var array      $opts
 * @var array|null $active  blanko yang berlaku saat mencetak
 * @var string|null $school  blanko default sekolah
 */
$srcLabel = ['guru' => 'Blanko milik Anda', 'sekolah' => 'Blanko default sekolah'];
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-1.5 max-w-2xl text-sm text-muted">
            <?= $isAdmin
                ? 'Unggah blanko sampul <strong class="text-ink">default</strong> untuk semua guru. Guru tetap bisa mengganti dengan blanko miliknya sendiri di menu Laporan → Sampul Buku Agenda.'
                : 'Ganti sampul Buku Agenda Anda dengan blanko sendiri. Bila tidak diunggah, sampul memakai blanko default sekolah.' ?>
            Data guru (nama, NIP, mapel, kelas, semester, T.P.) diisi otomatis saat mencetak.</p>
    </div>
    <?php if (! $isAdmin): ?><a class="btn btn-ghost" href="<?= site_url('reports/journal') ?>"><?= icon('printer', 'size-4') ?> Buka Buku Jurnal</a><?php endif ?>
</div>

<div class="cvr-grid">
    <form method="post" action="<?= site_url('cover') ?>" enctype="multipart/form-data" class="card grid content-start gap-5" data-progress="Menyimpan sampul" data-cvr-form>
        <?= csrf_field() ?>
        <div>
            <p class="label mb-1">Sedang dipakai saat mencetak</p>
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <?= $active ? badge($srcLabel[$active['source']], $active['source'] === 'guru' ? 'success' : 'info') : badge('Desain bawaan aplikasi', 'neutral') ?>
                <?php if (! $isAdmin && ! $ownUrl && $school): ?><span class="text-xs text-muted">Unggah blanko di bawah untuk memakai milik Anda.</span><?php endif ?>
            </p>
        </div>

        <div class="field">
            <label class="label" for="blank"><?= $isAdmin ? 'Blanko default (gambar)' : 'Blanko milik saya (gambar)' ?></label>
            <input class="input py-2" type="file" id="blank" name="blank" accept="image/jpeg,image/png,image/webp" aria-describedby="blank-help" data-cvr-file>
            <p class="help" id="blank-help">JPG/PNG/WebP, potret (A4/F4), maks. 15 MB. Kosongkan area tengah-bawah untuk isian data guru. Tip: desain di Canva ukuran A4 lalu unduh PNG.</p>
        </div>

        <fieldset class="grid gap-3 rounded-xl border border-line p-3">
            <legend class="px-1 text-sm font-semibold">Isian data guru</legend>
            <label class="check"><input type="checkbox" name="fields" value="1" <?= $opts['fields'] ? 'checked' : '' ?> data-cvr="fields"> <span class="text-sm">Tampilkan isian data guru di atas blanko</span></label>
            <label class="check"><input type="checkbox" name="box" value="1" <?= $opts['box'] ? 'checked' : '' ?> data-cvr="box"> <span class="text-sm">Kotak putih di belakang isian (agar mudah dibaca)</span></label>
            <div class="field">
                <label class="label" for="cvr-y">Posisi isian dari atas: <b data-cvr-yv><?= (int) $opts['y'] ?>%</b></label>
                <input type="range" id="cvr-y" name="y" min="5" max="90" step="1" value="<?= (int) $opts['y'] ?>" data-cvr="y" class="w-full">
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
            <button class="btn btn-primary"><?= icon('check', 'size-4') ?> Simpan</button>
            <?php if ($ownUrl): ?>
                <button class="btn btn-ghost" name="remove" value="1" data-confirm="<?= esc($isAdmin ? 'Hapus blanko default? Sampul guru kembali ke desain bawaan (kecuali guru yang memakai blanko sendiri).' : 'Hapus blanko Anda dan kembali memakai default sekolah?', 'attr') ?>" data-confirm-label="Ya, hapus">
                    <?= icon('trash', 'size-4') ?> <?= $isAdmin ? 'Hapus blanko default' : 'Pakai default sekolah' ?></button>
            <?php endif ?>
        </div>
    </form>

    <section class="card" aria-labelledby="cvr-prev">
        <h2 id="cvr-prev" class="label mb-2">Pratinjau sampul</h2>
        <div class="cvr-preview" data-cvr-preview>
            <?php $prevBlank = $ownUrl ? ['url' => $ownUrl, 'opts' => $opts] : ($active ?? null); ?>
            <?php if (! $prevBlank): ?>
                <!-- Belum ada blanko: tampilkan desain bawaan; kerangka blanko muncul saat gambar dipilih -->
                <div data-cvr-builtin><?= view('partials/journal_cover', ['profile' => $profile, 'kop' => $kopSet, 'teacher' => $teacher], ['saveData' => false]) ?></div>
                <div data-cvr-blankwrap hidden><?= view('partials/journal_cover', ['profile' => $profile, 'kop' => $kopSet, 'teacher' => $teacher, 'blank' => ['url' => '', 'opts' => $opts]], ['saveData' => false]) ?></div>
            <?php else: ?>
                <?= view('partials/journal_cover', ['profile' => $profile, 'kop' => $kopSet, 'teacher' => $teacher, 'blank' => $prevBlank], ['saveData' => false]) ?>
            <?php endif ?>
        </div>
        <p class="mt-2 text-xs text-muted">Pratinjau berubah langsung saat memilih gambar atau menggeser posisi. Klik Simpan untuk menerapkan.</p>
    </section>
</div>
<?= $this->endSection() ?>
