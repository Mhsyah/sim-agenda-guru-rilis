<?php
/**
 * Spanduk profil sekolah: foto sekolah sebagai latar, logo sekolah & instansi, nama, alamat, periode aktif.
 *
 * @var array|null  $profile
 * @var array|null  $period
 * @var string|null $editUrl  tautan "Ubah data" (admin); null = tanpa tombol
 * @var string|null $eyebrow  label kecil di atas nama (bawaan "Profil Sekolah")
 */
use App\Libraries\SchoolLogo;

$p      = $profile ?? [];
$photo  = SchoolLogo::assetUrl($p['school_photo'] ?? null, 'cover') ?? base_url('assets/img/cover-photo.webp');
// Foto potret/hampir persegi ditampilkan utuh di kanan (latar = versi buram), foto lanskap lebar memenuhi spanduk.
$file   = SchoolLogo::path($p['school_photo'] ?? null) ?? FCPATH . 'assets/img/cover-photo.webp';
$dim    = is_file($file) ? @getimagesize($file) : false;
$box    = is_file($file) ? SchoolLogo::contentBox($file) : [0, 0, 1, 1];
// rasio bagian foto yang berisi (setelah latar polos dipangkas)
$ratio  = $dim && $dim[1] > 0 ? ($dim[0] * $box[2]) / ($dim[1] * $box[3]) : 1.5;
$fit    = $dim && $ratio < 1.9;
$logo   = SchoolLogo::url($p ?: null);
$gov    = SchoolLogo::assetUrl($p['gov_logo'] ?? null, 'gov');
$name   = trim((string) ($p['school_name'] ?? '')) ?: 'Nama Sekolah';
$addr   = trim(implode(', ', array_filter([trim((string) ($p['school_address'] ?? '')), ucwords(mb_strtolower(trim((string) ($p['school_city'] ?? ''))))])));
$sem    = trim(($period['year']['name'] ?? '') . ' ' . ($period['semester']['name'] ?? ''));
// "SMP NEGERI 1 KOTA" → "SMP Negeri 1 Kota" (singkatan jenjang tetap kapital)
$nice   = preg_replace_callback('/\b(SMP|SMA|SMK|SD|MTS|MA|MI|SLB|SDN|SMPN|SMAN|SMKN|SDIT|SMPIT)\b/iu', static fn ($m) => mb_strtoupper($m[1]), mb_convert_case(mb_strtolower($name), MB_CASE_TITLE));
?>
<section class="sb-hero mb-5<?= $fit ? ' is-fit' : '' ?>" aria-label="Profil sekolah">
    <img class="sb-hero-img" src="<?= esc($photo, 'attr') ?>" alt="" loading="eager" decoding="async">
    <?php if ($fit): ?>
        <span class="sb-hero-frame" style="aspect-ratio: <?= round($ratio, 4) ?>">
            <img src="<?= esc($photo, 'attr') ?>" alt="Foto <?= esc($name, 'attr') ?>" decoding="async"
                 style="width: <?= round(100 / $box[2], 3) ?>%; left: <?= round(-$box[0] / $box[2] * 100, 3) ?>%; top: <?= round(-$box[1] / $box[3] * 100, 3) ?>%">
        </span>
    <?php endif ?>
    <div class="sb-hero-shade" aria-hidden="true"></div>
    <div class="sb-hero-top">
        <?php if ($sem !== ''): ?><span class="sb-hero-chip"><?= icon('calendar', 'size-3.5') ?> Periode aktif <?= esc($sem) ?></span><?php endif ?>
        <?php if (! empty($editUrl)): ?><a class="sb-hero-btn" href="<?= esc($editUrl, 'attr') ?>"><?= icon('edit', 'size-4') ?> Ubah data</a><?php endif ?>
    </div>
    <div class="sb-hero-body">
        <?php if ($logo): ?><span class="sb-hero-logo"><img src="<?= esc($logo, 'attr') ?>" alt="Logo <?= esc($name, 'attr') ?>"></span><?php endif ?>
        <?php if ($gov): ?><span class="sb-hero-logo is-small"><img src="<?= esc($gov, 'attr') ?>" alt="Logo instansi"></span><?php endif ?>
        <div class="min-w-0">
            <p class="sb-hero-eyebrow"><?= esc($eyebrow ?? 'Profil Sekolah') ?></p>
            <h2 class="sb-hero-name"><?= esc($nice) ?></h2>
            <?php if ($addr !== ''): ?><p class="sb-hero-addr"><?= icon('pin', 'size-3.5 shrink-0') ?> <span><?= esc($addr) ?></span></p><?php endif ?>
        </div>
    </div>
</section>
