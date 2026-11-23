<?php
/** Tab penghubung Kalender ↔ Daftar Agenda (menu "Kalender & Agenda" guru). @var string $active calendar|list */
?>
<nav class="u-tabs ev-tabs mb-4 no-print" aria-label="Kalender & Agenda">
    <a href="<?= site_url('calendar') ?>" <?= $active === 'calendar' ? 'aria-current="page"' : '' ?>><?= icon('calendar', 'size-4') ?> Kalender</a>
    <a href="<?= site_url('agendas') ?>" <?= $active === 'list' ? 'aria-current="page"' : '' ?>><?= icon('list', 'size-4') ?> Daftar Agenda</a>
</nav>
