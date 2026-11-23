<?php
$map = [
    'success' => ['alert-success', 'check', 'status'],
    'warning' => ['alert-warning', 'alert', 'status'],
    'error'   => ['alert-error', 'alert', 'alert'],
    'info'    => ['alert-info', 'info', 'status'],
];
foreach ($map as $key => [$cls, $ic, $role]):
    if ($msg = session()->getFlashdata($key)): ?>
        <div class="alert <?= $cls ?> mb-5 no-print" role="<?= $role ?>">
            <?= icon($ic, 'size-5 shrink-0 mt-0.5') ?>
            <div><?= esc($msg) ?></div>
        </div>
    <?php endif;
endforeach;
$act = session()->getFlashdata('action');
if (is_array($act) && ! empty($act['url'])): ?>
    <div class="alert alert-info mb-5 no-print flex-wrap items-center" role="status">
        <?= icon('pen', 'size-5 shrink-0') ?>
        <div class="flex-1"><?= esc($act['text'] ?? '') ?></div>
        <a class="btn btn-primary btn-sm" href="<?= esc($act['url'], 'attr') ?>"><?= esc($act['label'] ?? 'Buka') ?></a>
    </div>
<?php endif;
$errs = session('errors') ?? [];
if ($errs && count($errs) > 1): ?>
    <div class="alert alert-error mb-5" role="alert">
        <?= icon('alert', 'size-5 shrink-0 mt-0.5') ?>
        <ul class="list-disc pl-4">
            <?php foreach ($errs as $f => $e): ?><li><?= esc($e) ?></li><?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
