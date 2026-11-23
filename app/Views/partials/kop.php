<?php
/**
 * Kop surat sekolah untuk semua halaman cetak (juga dipakai pratinjau langsung di Profil & Kop Sekolah).
 *
 * @var array|null $profile
 * @var array|null $kop     pengaturan kop (opsional; bawaan dari profil)
 */
use App\Libraries\KopSettings;
use App\Libraries\SchoolLogo;

$p      = $profile ?? [];
$k      = $kop ?? KopSettings::get($p ?: null);
$school = SchoolLogo::url($p ?: null);
$gov    = SchoolLogo::assetUrl($p['gov_logo'] ?? null, 'gov');
// Bawaan: kop memakai logo instansi saja (kiri). Dicentang "tampilkan juga logo sekolah": logo sekolah ikut tampil di kanan.
// Bila logo instansi belum diunggah, logo sekolah dipakai di kiri.
[$left, $right] = $gov ? [$gov, $school] : [$school, null];
$both   = (bool) $k['show_logo'];
$style  = sprintf('--kop-font:%s;--kop-font-addr:%s;--kop-s1:%dpx;--kop-s2:%dpx;--kop-s3:%dpx;--kop-sa:%dpx',
    KopSettings::fontStack($k), KopSettings::fontStack($k, 'font_addr'), $k['size1'], $k['size2'], $k['size3'], $k['size_addr']);
$line   = static fn (string $key, string $cls) => '<p class="' . $cls . '" data-kop="' . $key . '"' . ($k[$key] === '' ? ' hidden' : '') . '>' . esc($k[$key]) . '</p>';
?>
<header class="kop kop-rule-<?= esc($k['rule'], 'attr') ?> kop-case-<?= esc($k['case'], 'attr') ?> kop-w-<?= esc($k['weight'], 'attr') ?><?= $both ? '' : ' kop-one' ?>" style="<?= esc($style, 'attr') ?>" data-kop-root>
    <span class="kop-logo-box" data-kop-logo><?php if ($left): ?><img src="<?= esc($left, 'attr') ?>" alt="" class="kop-logo"><?php endif ?></span>
    <div class="kop-text">
        <?= $line('line1', 'kop-l1') ?>
        <?= $line('line2', 'kop-l2') ?>
        <?= $line('line3', 'kop-l3') ?>
        <?= $line('addr1', 'kop-addr') ?>
        <?= $line('addr2', 'kop-addr') ?>
        <?= $line('city', 'kop-city') ?>
    </div>
    <span class="kop-logo-box kop-logo-right" data-kop-logo><?php if ($right): ?><img src="<?= esc($right, 'attr') ?>" alt="" class="kop-logo"><?php endif ?></span>
    <p class="kop-postal" data-kop="postal"<?= $k['postal'] === '' ? ' hidden' : '' ?>><?= esc($k['postal']) ?></p>
</header>
