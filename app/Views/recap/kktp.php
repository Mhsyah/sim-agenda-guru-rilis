<?php
/**
 * Rekap ketercapaian KKTP (siswa × TP) — semester aktif.
 *
 * @var array $kk
 */
use App\Libraries\Kktp;

$short = ['Perlu bimbingan' => 'PB', 'Cukup' => 'C', 'Baik' => 'B', 'Sangat baik' => 'SB'];
?>
<?php if (! $kk['tps']): ?>
    <div class="card py-12 text-center text-muted">Belum ada asesmen yang dihubungkan ke TP pada kelas, mapel, dan semester ini.
        Pilih <b>TP yang diukur</b> saat membuat asesmen agar ketercapaian KKTP terhitung.</div>
<?php else: ?>
    <p class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-muted">Keterangan:
        <?php foreach (Kktp::LEVELS as $i => $lv): ?><?= badge($short[$lv] . ' = ' . $lv, Kktp::TONES[$i]) ?><?php endforeach ?>
        · nilai = rata-rata asesmen yang mengukur TP tersebut</p>
    <section class="card p-0 sm:p-0">
        <div class="table-wrap">
            <table class="table kk-table">
                <caption class="sr-only">Ketercapaian KKTP per siswa</caption>
                <thead>
                    <tr>
                        <th scope="col" class="num">No</th><th scope="col">Nama siswa</th>
                        <?php foreach ($kk['tps'] as $tp): ?>
                            <th scope="col" class="kk-tp" title="<?= esc($tp['description'], 'attr') ?>"><?= esc($tp['code'] ?: 'TP') ?><small><?= count($tp['assessments']) ?> asesmen</small></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($kk['rows'] as $i => $r): ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <th scope="row" class="!bg-transparent text-left text-sm font-medium normal-case tracking-normal text-ink"><?= esc($r['student']['full_name']) ?></th>
                        <?php foreach ($kk['tps'] as $tp): $c = $r['cells'][$tp['id']] ?? null; ?>
                            <td class="kk-cell"><?php if ($c && $c['level']): ?>
                                <span class="badge badge-<?= esc($c['level']['tone'], 'attr') ?>" title="<?= esc($c['level']['label'], 'attr') ?>"><?= esc($short[$c['level']['label']] ?? '') ?></span>
                                <small><?= fmt_num($c['value'], 0) ?></small>
                            <?php else: ?><span class="text-muted">—</span><?php endif ?></td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="2" class="text-left text-xs">Rekap tingkat</th>
                        <?php foreach ($kk['tps'] as $tp): $sm = $kk['summary'][$tp['id']] ?? []; ?>
                            <td class="kk-sum"><?php foreach (Kktp::LEVELS as $lv): ?><?php if (! empty($sm[$lv])): ?><span><?= $short[$lv] ?> <?= (int) $sm[$lv] ?></span><?php endif ?><?php endforeach ?></td>
                        <?php endforeach ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
<?php endif ?>
