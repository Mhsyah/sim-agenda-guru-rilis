<?php
/**
 * Grafik batang murni HTML/CSS (tanpa library JS).
 *
 * @var list<array{label: string, value: float|int, tip: string}> $bars
 * @var string $aria    deskripsi grafik untuk pembaca layar
 * @var string $caption judul tabel data aksesibel
 * @var string $unit
 */
$max   = max(1, ...array_map(static fn ($b) => (float) $b['value'], $bars ?: [['value' => 1]]));
$top   = $max <= 4 ? 4 : (int) (ceil($max / 4) * 4);
$ticks = [$top, $top * 0.75, $top * 0.5, $top * 0.25, 0];
?>
<div class="flex gap-3" role="img" aria-label="<?= esc($aria, 'attr') ?>">
    <div class="flex h-52 flex-col justify-between pb-6 text-right text-[0.7rem] tabular-nums text-muted" aria-hidden="true">
        <?php foreach ($ticks as $t): ?><span><?= fmt_num($t, 0) ?></span><?php endforeach ?>
    </div>
    <div class="relative min-w-0 flex-1">
        <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between" aria-hidden="true">
            <?php foreach ($ticks as $i => $t): ?><span class="border-t <?= $i === 4 ? 'border-line' : 'border-dashed border-line' ?>"></span><?php endforeach ?>
        </div>
        <div class="bars relative h-52" aria-hidden="true">
            <?php foreach ($bars as $b): ?>
                <div class="bar-col">
                    <span class="bar-tip"><?= esc($b['tip']) ?></span>
                    <div class="flex h-[calc(100%-1.5rem)] w-full items-end justify-center">
                        <div class="bar" style="height: <?= round((float) $b['value'] / $top * 100, 1) ?>%"></div>
                    </div>
                    <span class="mt-2 h-4 whitespace-nowrap text-[0.68rem] font-medium text-muted"><?= esc($b['label']) ?></span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<div class="sr-only"><!-- dibungkus: tabel tidak bisa disusutkan ke 1px dan membuat halaman tergulir di HP -->
<table>
    <caption><?= esc($caption) ?></caption>
    <thead><tr><th scope="col">Periode</th><th scope="col"><?= esc($unit) ?></th><th scope="col">Keterangan</th></tr></thead>
    <tbody><?php foreach ($bars as $b): ?><tr><th scope="row"><?= esc($b['label']) ?></th><td><?= esc((string) $b['value']) ?></td><td><?= esc($b['tip']) ?></td></tr><?php endforeach ?></tbody>
</table>
</div>
