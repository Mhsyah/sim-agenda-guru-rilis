<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="page-title"><?= esc($title) ?></h1>
<p class="mt-2 max-w-2xl text-muted">Semua laporan dapat difilter, dicetak dengan tampilan bersih (navigasi disembunyikan), dan diekspor ke CSV yang ramah Excel.</p>
<ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php $i = 0; foreach ($types as $key => [$label, $desc, $ic]): ?>
        <li>
            <a class="card card-hover flex h-full flex-col gap-3 text-ink no-underline <?= $i++ % 3 === 1 ? 'card-organic' : '' ?>" href="<?= site_url('reports/' . $key) ?>">
                <span class="grid size-11 place-items-center rounded-[1rem_1.4rem_1rem_1.6rem] bg-surface-2"><?= icon($ic) ?></span>
                <span class="font-display text-lg font-bold"><?= esc($label) ?></span>
                <span class="text-sm text-muted"><?= esc($desc) ?></span>
            </a>
        </li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
