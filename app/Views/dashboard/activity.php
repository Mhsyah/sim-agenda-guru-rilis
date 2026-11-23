<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title"><?= esc($title) ?></h1>
        <p class="mt-2 text-muted">Semua aktivitas akun Anda yang tercatat (<?= number_format($total, 0, ',', '.') ?> entri).</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('/') ?>"><?= icon('chevron-left', 'size-4') ?> Dashboard</a>
</div>
<section class="card">
    <?php if (! $items): ?>
        <p class="py-10 text-center text-muted">Belum ada aktivitas tercatat.</p>
    <?php else: ?>
        <ol class="act-feed act-feed-lg">
            <?php foreach ($items as $a): ?>
                <li class="act-item is-<?= esc($a['tone'], 'attr') ?>">
                    <span class="act-dot" aria-hidden="true"><?= icon($a['icon'], 'size-4') ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="act-text"><?= $a['url'] ? '<a href="' . esc($a['url'], 'attr') . '">' . esc($a['text']) . '</a>' : esc($a['text']) ?></p>
                        <?php if ($a['detail'] !== ''): ?><p class="act-detail"><?= esc($a['detail']) ?></p><?php endif ?>
                    </div>
                    <time class="act-time whitespace-nowrap" title="<?= esc($a['ago'], 'attr') ?>"><?= esc($a['at']) ?></time>
                </li>
            <?php endforeach ?>
        </ol>
        <?php if ($pages > 1): ?>
            <nav class="mt-5 flex items-center justify-between gap-2 border-t border-line pt-4" aria-label="Halaman riwayat">
                <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('activity?page=' . ($page - 1)) ?>"><?= icon('chevron-left', 'size-4') ?> Lebih baru</a><?php else: ?><span></span><?php endif ?>
                <span class="text-sm text-muted">Halaman <?= $page ?> dari <?= $pages ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('activity?page=' . ($page + 1)) ?>">Lebih lama <?= icon('chevron-right', 'size-4') ?></a><?php else: ?><span></span><?php endif ?>
            </nav>
        <?php endif ?>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
