<?php
/** Tanda tangan cetak ATP/KKTP: kepala sekolah (kiri) & guru (kanan). @var array $kopSet @var array $teacher */
?>
<div class="pl-sign print-only">
    <div>
        <p>Mengetahui,</p>
        <p>Kepala <?= esc($profile['school_name'] ?? 'Sekolah') ?></p>
        <div class="pl-sign-space"></div>
        <p class="font-bold underline"><?= esc(($profile['principal_name'] ?? '') ?: '……………………………') ?></p>
        <p>NIP. <?= esc(($profile['principal_nip'] ?? '') ?: '……………………') ?></p>
    </div>
    <div>
        <p><?= esc(($profile['school_city'] ?? '') ?: '……………') ?>, <?= fmt_date(\App\Libraries\KopSettings::signDate($kopSet), true) ?></p>
        <p>Guru Mata Pelajaran</p>
        <div class="pl-sign-space"></div>
        <p class="font-bold underline"><?= esc($teacher['name'] ?? '') ?></p>
        <p>NIP. <?= esc(($teacher['nip'] ?? '') ?: '-') ?></p>
    </div>
</div>
