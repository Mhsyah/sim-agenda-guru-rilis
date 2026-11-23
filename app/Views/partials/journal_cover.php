<?php
/**
 * Sampul (halaman pertama) Buku Agenda Guru — potret, mengikuti blanko "Cover Manajemen Agenda Guru".
 * Data guru terisi otomatis dari akun guru yang mencetak.
 *
 * @var array|null $profile
 * @var array      $kop      pengaturan kop & cetak (KopSettings::get)
 * @var array      $teacher  ['name','nip','subject','class','semester','year'] — kosong = garis isian (pratinjau)
 * @var array|null $blank    blanko unggahan ['url', 'opts' => fields,y,box] — null = desain bawaan
 */
use App\Libraries\KopSettings;
use App\Libraries\SchoolLogo;

$p      = $profile ?? [];
$k      = $kop;
$t      = $teacher ?? [];
[$pw, $ph] = explode(' ', KopSettings::PAPERS[$k['paper']][1] ?? '210mm 297mm');
$school = SchoolLogo::url($p ?: null);
$gov    = SchoolLogo::assetUrl($p['gov_logo'] ?? null, 'gov');
$photo  = ($k['show_cover_photo'] ?? 1) ? (SchoolLogo::assetUrl($p['school_photo'] ?? null, 'cover') ?? base_url('assets/img/cover-photo.webp')) : null;
$logos  = array_values(array_filter($k['show_logo'] ? [$gov, $school] : [$gov ?: $school]));
$val    = static fn (string $key) => ($t[$key] ?? '') !== '' ? esc($t[$key]) : '<span class="cv-blank"></span>';
$rows   = [
    'Nama Guru'        => $val('name'),
    'NIP'              => ($t['nip'] ?? null) === '' && array_key_exists('nip', $t) ? '-' : $val('nip'),
    'Mata Pelajaran'   => $val('subject'),
    'Kelas'            => $val('class'),
    'Semester'         => $val('semester'),
    'Tahun Pelajaran'  => $val('year'),
];
?>
<?php if (! empty($blank)): $bo = $blank['opts']; ?>
<section class="cover-sheet is-blank<?= $blank['url'] === '' ? ' is-empty' : '' ?>" style="--cv-ar: <?= (float) $pw ?> / <?= (float) $ph ?>; --cv-ph: <?= esc($ph, 'attr') ?>" aria-label="Sampul Buku Agenda Guru">
    <img class="cv-blank-img" src="<?= esc($blank['url'], 'attr') ?>" alt="" <?= $blank['url'] === '' ? 'hidden' : '' ?>>
    <?php if ($blank['url'] === ''): ?><p class="cv-blank-empty">Belum ada blanko.<br>Pilih gambar untuk melihat pratinjau.</p><?php endif ?>
    <?php if (true): ?>
        <dl <?= $bo['fields'] ? '' : 'hidden' ?> class="cv-data cv-data-float<?= $bo['box'] ? ' has-box' : '' ?>" style="top: <?= (int) $bo['y'] ?>%" data-cv-fields>
            <?php foreach ($rows as $label => $v): ?>
                <dt><?= $label ?></dt><dd><span class="cv-colon">:</span> <?= $v ?></dd>
            <?php endforeach ?>
        </dl>
    <?php endif ?>
</section>
<?php return; endif ?>
<section class="cover-sheet" style="--cv-ar: <?= (float) $pw ?> / <?= (float) $ph ?>; --cv-ph: <?= esc($ph, 'attr') ?>" aria-label="Sampul Buku Agenda Guru">
    <?php if ($photo): ?><img class="cv-photo" src="<?= esc($photo, 'attr') ?>" alt=""><?php endif ?>
    <img class="cv-shape cv-shape-top" src="<?= base_url('assets/img/cover-shapes.webp') ?>" alt="">
    <img class="cv-shape cv-shape-bottom" src="<?= base_url('assets/img/cover-shapes.webp') ?>" alt="">

    <div class="cv-body">
        <div class="cv-head">
            <?php if ($logos): ?>
                <div class="cv-logos"><?php foreach ($logos as $l): ?><img src="<?= esc($l, 'attr') ?>" alt=""><?php endforeach ?></div>
            <?php endif ?>
            <?php foreach (['line1', 'line2'] as $ln): ?>
                <?php if (($k[$ln] ?? '') !== ''): ?><p class="cv-inst"><?= esc($k[$ln]) ?></p><?php endif ?>
            <?php endforeach ?>
            <p class="cv-school"><?= esc(($k['line3'] ?? '') !== '' ? $k['line3'] : ($p['school_name'] ?? 'NAMA SEKOLAH')) ?></p>
        </div>

        <div class="cv-title">
            <h2>BUKU AGENDA GURU</h2>
            <p>JURNAL MENGAJAR</p>
        </div>

        <dl class="cv-data">
            <?php foreach ($rows as $label => $v): ?>
                <dt><?= $label ?></dt><dd><span class="cv-colon">:</span> <?= $v ?></dd>
            <?php endforeach ?>
        </dl>
    </div>

</section>
