<?php
/** @var list<array{jam: string, pukul: string, days: array<int, list<string>>}> $grid */
$days = array_slice(day_names(), 0, 6, true);
?>
<div class="table-wrap">
    <table class="table">
        <caption>Jadwal mengajar mingguan</caption>
        <thead>
        <tr>
            <th scope="col">Jam ke</th>
            <th scope="col">Pukul</th>
            <?php foreach ($days as $dn): ?><th scope="col"><?= $dn ?><br><span style="font-weight:400">Kelas</span></th><?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php if (! $grid): ?>
            <tr><td colspan="8" class="text-center">Belum ada jadwal.</td></tr>
        <?php endif ?>
        <?php foreach ($grid as $i => $row): ?>
            <tr>
                <td class="text-center"><?= esc($row['jam'] !== '' ? $row['jam'] : (string) ($i + 1)) ?>.</td>
                <td class="whitespace-nowrap text-center"><?= esc($row['pukul']) ?></td>
                <?php foreach (array_keys($days) as $d): ?>
                    <td class="text-center"><?= esc(implode(', ', $row['days'][$d] ?? [])) ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
