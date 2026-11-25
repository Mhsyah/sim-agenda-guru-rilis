<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Warna kotak ikon per laporan (lembut, terbaca di terang & gelap). */
$tones = ['journal' => 'indigo', 'agenda' => 'blue', 'activities' => 'violet', 'attendance' => 'green', 'students' => 'sky',
    'scores' => 'amber', 'recap' => 'teal', 'incomplete' => 'rose', 'calendar' => 'orange'];
?>
<div class="mb-6">
    <h1 class="page-title"><?= esc($title) ?></h1>
    <p class="mt-2 max-w-2xl text-muted">Pilih laporan untuk difilter, dicetak dengan kop sekolah, atau diekspor ke CSV yang ramah Excel.</p>
</div>
<ul class="rp-grid" aria-label="Daftar laporan">
    <?php foreach ($types as $key => [$label, $desc, $ic]): ?>
        <li>
            <a class="rp-card" href="<?= site_url('reports/' . $key) ?>">
                <span class="rp-ico is-<?= $tones[$key] ?? 'slate' ?>" aria-hidden="true"><?= icon($ic, 'size-5') ?></span>
                <span class="min-w-0 flex-1">
                    <span class="rp-title"><?= esc($label) ?></span>
                    <span class="rp-desc"><?= esc($desc) ?></span>
                </span>
                <span class="rp-go" aria-hidden="true"><?= icon('chevron-right', 'size-4') ?></span>
            </a>
        </li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
