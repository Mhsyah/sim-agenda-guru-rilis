<?php
/** @var \CodeIgniter\Pager\PagerRenderer $pager */
$pager->setSurroundCount(2);
if ($pager->getPageCount() <= 1) {
    return;
}
?>
<nav class="pager mt-5 no-print" aria-label="Navigasi halaman">
    <?php if ($pager->hasPrevious()): ?>
        <a href="<?= $pager->getFirst() ?>" aria-label="Halaman pertama">«</a>
        <a href="<?= $pager->getPrevious() ?>" aria-label="Halaman sebelumnya">‹</a>
    <?php endif ?>
    <?php foreach ($pager->links() as $link): ?>
        <a href="<?= $link['uri'] ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?> aria-label="Halaman <?= $link['title'] ?>"><?= $link['title'] ?></a>
    <?php endforeach ?>
    <?php if ($pager->hasNext()): ?>
        <a href="<?= $pager->getNext() ?>" aria-label="Halaman berikutnya">›</a>
        <a href="<?= $pager->getLast() ?>" aria-label="Halaman terakhir">»</a>
    <?php endif ?>
</nav>
