<?php
/**
 * Panel "Pengaturan Cetak" untuk satu cetakan (diletakkan di dalam form GET halaman cetak).
 * Nilai awal = Pengaturan Cetak Dokumen sekolah; perubahan di sini hanya berlaku untuk cetakan ini.
 *
 * @var array|null $profile
 * @var bool       $withDate  tampilkan pilihan tanggal surat (halaman bertanda tangan)
 * @var bool       $withCover tampilkan pilihan foto sekolah di sampul
 */
use App\Libraries\KopSettings;

$k         = KopSettings::get($profile ?? null);
$withDate  ??= false;
$withCover ??= false;
$open      = KopSettings::hasOverride($profile ?? null);
?>
<details class="print-opts basis-full" data-print-opts <?= $open ? 'open' : '' ?>>
    <summary>
        <?= icon('chevron-down', 'print-opts-chev size-4') ?>
        <?= icon('printer', 'size-4') ?>
        <span>Pengaturan Cetak: kertas<?= $withDate ? ', tanggal surat' : '' ?> &amp; logo</span>
        <?php if ($open): ?><span class="badge badge-info ml-1">Khusus cetakan ini</span><?php endif ?>
    </summary>
    <div class="print-opts-body">
        <div class="grid gap-4 <?= $withDate ? 'sm:grid-cols-2' : '' ?>">
            <div class="field">
                <label class="label" for="po-paper">Ukuran kertas</label>
                <select class="input" id="po-paper" name="print[paper]" data-print-auto>
                    <?php foreach (KopSettings::PAPERS as $v => $p): ?>
                        <option value="<?= esc($v, 'attr') ?>" <?= $k['paper'] === $v ? 'selected' : '' ?>><?= esc($p[0]) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php if ($withDate): ?>
                <fieldset class="field min-w-0">
                    <legend class="label mb-1.5">Tanggal surat</legend>
                    <div class="seg-toggle" role="radiogroup" aria-label="Tanggal surat">
                        <label><input type="radio" name="print[date_mode]" value="auto" <?= $k['date_mode'] !== 'manual' ? 'checked' : '' ?> data-print-auto><span>Otomatis</span></label>
                        <label><input type="radio" name="print[date_mode]" value="manual" <?= $k['date_mode'] === 'manual' ? 'checked' : '' ?> data-print-date-manual><span>Manual</span></label>
                    </div>
                    <div class="mt-2" data-print-date <?= $k['date_mode'] === 'manual' ? '' : 'hidden' ?>>
                        <label class="sr-only" for="po-date">Tanggal surat</label>
                        <input class="input" type="date" id="po-date" name="print[sign_date]" value="<?= esc($k['sign_date'] ?: date('Y-m-d'), 'attr') ?>" data-print-auto>
                    </div>
                </fieldset>
            <?php endif ?>
        </div>
        <div class="mt-4 grid gap-2.5">
            <input type="hidden" name="print[logo]" value="0">
            <label class="check items-start"><input type="checkbox" name="print[logo]" value="1" <?= $k['show_logo'] ? 'checked' : '' ?> data-print-auto>
                <span><span class="text-sm">Tampilkan <strong>logo instansi &amp; logo sekolah</strong> pada kop surat</span>
                <span class="block text-xs text-muted">Tanpa centang = hanya logo instansi.</span></span></label>
            <?php if ($withCover): ?>
                <input type="hidden" name="print[cover]" value="0">
                <label class="check items-start"><input type="checkbox" name="print[cover]" value="1" <?= $k['show_cover_photo'] ? 'checked' : '' ?> data-print-auto>
                    <span><span class="text-sm">Tampilkan <strong>foto sekolah</strong> pada sampul</span>
                    <span class="block text-xs text-muted">Foto diunggah admin di Profil &amp; Kop Sekolah.</span></span></label>
            <?php endif ?>
        </div>
        <p class="mt-3 text-xs text-muted">Bawaan mengikuti Profil &amp; Kop Sekolah › Pengaturan Cetak Dokumen. Pilihan di sini hanya berlaku untuk cetakan ini.
            <?php if ($open): ?><a href="<?= esc(current_url() . '?' . http_build_query(array_diff_key((array) service('request')->getGet(), ['print' => 1])), 'attr') ?>">Kembali ke bawaan</a><?php endif ?></p>
    </div>
</details>
