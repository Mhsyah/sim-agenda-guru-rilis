<?php
/**
 * Daftar siswa ringkas satu kelas (fragmen untuk pop-up Kelas/Rombel).
 *
 * @var array $class
 * @var list<array> $rows
 */
$l = count(array_filter($rows, static fn ($r) => $r['gender'] === 'L'));
?>
<div class="cd-head">
    <div class="min-w-0">
        <p id="cd-title" class="text-lg font-bold tracking-tight">Siswa <?= esc($class['name']) ?></p>
        <p class="text-xs text-muted"><?= count($rows) ?> siswa aktif · <?= $l ?> L / <?= count($rows) - $l ?> P</p>
    </div>
    <button type="button" class="btn btn-ghost btn-sm btn-icon" data-class-close aria-label="Tutup"><?= icon('x', 'size-4') ?></button>
</div>
<?php if (! $rows): ?>
    <p class="px-5 py-8 text-center text-sm text-muted">Belum ada siswa di kelas ini. Tempatkan siswa lewat Data Siswa atau Impor Data.</p>
<?php else: ?>
    <div class="px-5 pt-3">
        <label class="sr-only" for="cd-q">Cari siswa di kelas ini</label>
        <input class="input h-9 min-h-0 text-sm" id="cd-q" type="search" placeholder="Cari nama atau NIS…" autocomplete="off" data-class-filter>
    </div>
    <ol class="cd-list">
        <?php foreach ($rows as $i => $s): ?>
            <li data-name="<?= esc(mb_strtolower($s['full_name'] . ' ' . $s['nis']), 'attr') ?>">
                <span class="cd-no"><?= $i + 1 ?></span>
                <span class="min-w-0 flex-1 truncate" title="<?= esc($s['full_name'], 'attr') ?>"><?= esc($s['full_name']) ?></span>
                <span class="cd-g cd-g-<?= strtolower((string) $s['gender']) ?>"><?= esc($s['gender'] ?: '–') ?></span>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
<div class="cd-foot">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('students?class_group_id=' . (int) $class['id']) ?>"><?= icon('list', 'size-4') ?> Buka di Data Siswa</a>
</div>
