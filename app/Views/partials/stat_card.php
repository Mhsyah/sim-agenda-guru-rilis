<?php
/**
 * Kartu metrik dengan badge tren.
 *
 * @var array{label: string, value: string, icon?: string, url?: string, note?: string, trend?: ?array{dir: string, text: string}, warn?: bool} $s
 */
$t     = $s['trend'] ?? null;
$tag   = ! empty($s['url']) ? 'a' : 'div';
$arrow = ['up' => 'm6 15 6-6 6 6', 'down' => 'm6 9 6 6 6-6', 'flat' => 'M5 12h14'];
?>
<<?= $tag ?> <?= $tag === 'a' ? 'href="' . esc($s['url'], 'attr') . '"' : '' ?> class="stat flex h-full flex-col text-ink no-underline">
    <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2.5">
            <?php if (! empty($s['icon'])): ?>
                <span class="stat-ico grid size-8 shrink-0 place-items-center rounded-lg bg-surface-2 text-muted"><?= icon($s['icon'], 'size-4') ?></span>
            <?php endif ?>
            <p class="truncate text-[0.8125rem] font-medium leading-tight text-muted"><?= esc($s['label']) ?></p>
        </div>
        <?php if ($t): ?>
            <span class="trend trend-<?= esc($t['dir'], 'attr') ?> shrink-0">
                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="<?= $arrow[$t['dir']] ?>"/></svg>
                <?= esc($t['text']) ?><span class="sr-only"> <?= ['up' => 'naik', 'down' => 'turun', 'flat' => 'tetap'][$t['dir']] ?> dibanding minggu lalu</span>
            </span>
        <?php elseif (! empty($s['warn'])): ?>
            <span class="trend trend-down shrink-0">Perlu cek</span>
        <?php endif ?>
    </div>
    <p class="mt-4 text-[1.75rem] font-bold leading-none tracking-tight tabular-nums"><?= esc($s['value']) ?></p>
    <?php if (! empty($s['note'])): ?><p class="mt-2 truncate text-xs text-muted" title="<?= esc($s['note'], 'attr') ?>"><?= esc($s['note']) ?></p><?php endif ?>
</<?= $tag ?>>
