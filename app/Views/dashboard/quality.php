<?php
/**
 * Indikator kualitas data Manajemen Agenda Guru: 4 meter (Kelengkapan, Validitas, Mutakhir, Total),
 * grafik batang per entitas, dan tren skor per semester.
 *
 * @var array $q     DataQuality::forSemester()
 * @var list<array> $trend DataQuality::trend()
 */
use App\Libraries\DataQuality;

$color = ['k' => '#1e4f8f', 'v' => '#4a86c5', 'm' => '#0f2752', 't' => '#4a86c5'];
$fmt   = static fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '.') . '%';
$label = $q['semester']['label'] ?? '';
// Busur 270° (mulai kiri-bawah, searah jarum jam)
$arc = static function (float $pct, float $r = 52, float $cx = 70, float $cy = 66): string {
    $a0 = deg2rad(135);
    $a1 = deg2rad(135 + 270 * max(0, min(100, $pct)) / 100);
    $x0 = $cx + $r * cos($a0);
    $y0 = $cy + $r * sin($a0);
    $x1 = $cx + $r * cos($a1);
    $y1 = $cy + $r * sin($a1);
    $large = (270 * $pct / 100) > 180 ? 1 : 0;

    return sprintf('M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f', $x0, $y0, $r, $r, $large, $x1, $y1);
};
$gauges = [['k', 'Kelengkapan', $q['dims']['k']], ['v', 'Validitas', $q['dims']['v']], ['m', 'Mutakhir', $q['dims']['m']], ['t', 'Total', $q['total']]];

// Garis tren: titik → kurva halus
$W = 700;
$H = 240;
$padL = 44;
$padR = 24;
$padT = 16;
$padB = 34;
$minY = 60;
$pts  = [];
$tn   = count($trend);
foreach ($trend as $i => $t) {
    if ($t['total'] === null) {
        continue;
    }
    $x     = $tn > 1 ? $padL + ($W - $padL - $padR) * $i / ($tn - 1) : ($W + $padL - $padR) / 2;
    $y     = $padT + ($H - $padT - $padB) * (1 - (max($minY, $t['total']) - $minY) / (100 - $minY));
    $pts[] = [$x, $y, $t];
}
$path = '';
foreach ($pts as $i => [$x, $y]) {
    if ($i === 0) {
        $path = sprintf('M %.1f %.1f', $x, $y);
        continue;
    }
    [$px, $py] = $pts[$i - 1];
    $cx        = ($px + $x) / 2;
    $path     .= sprintf(' C %.1f %.1f, %.1f %.1f, %.1f %.1f', $cx, $py, $cx, $y, $x, $y);
}
?>
<section class="dq mb-5" aria-labelledby="dq-title">
    <div class="dq-head">
        <h2 id="dq-title" class="text-[0.95rem] font-bold">Indikator Kualitas Data Manajemen Agenda Guru</h2>
        <span class="text-xs text-muted">diperbarui tiap 10 menit</span>
    </div>

    <ul class="dq-gauges" aria-label="Skor kualitas data">
        <?php foreach ($gauges as [$key, $name, $val]): ?>
            <li class="dq-card">
                <div class="dq-card-head"><h3><?= esc($name) ?></h3><span class="dq-chip"><?= esc($label) ?></span></div>
                <svg viewBox="0 0 140 120" class="dq-gauge" role="img" aria-label="<?= esc($name . ' ' . $fmt($val), 'attr') ?>">
                    <path d="<?= $arc(100) ?>" class="dq-track"/>
                    <?php if ($val !== null && $val > 0): ?><path d="<?= $arc((float) $val) ?>" stroke="<?= $color[$key] ?>" class="dq-val"/><?php endif ?>
                    <text x="70" y="70" class="dq-num"><?= $fmt($val) ?></text>
                    <text x="70" y="88" class="dq-sub">Skor</text>
                </svg>
            </li>
        <?php endforeach ?>
    </ul>

    <div class="dq-card dq-tabs">
        <input type="radio" name="dq-tab" id="dq-t1" class="sr-only" checked>
        <input type="radio" name="dq-tab" id="dq-t2" class="sr-only">
        <?php if (! empty($agendaChart)): ?><input type="radio" name="dq-tab" id="dq-t3" class="sr-only"><?php endif ?>
        <div class="dq-tabbar">
            <div class="u-tabs" role="tablist" aria-label="Pilih grafik">
                <label for="dq-t1">Kualitas per entitas</label>
                <label for="dq-t2">Kualitas per semester</label>
                <?php if (! empty($agendaChart)): ?><label for="dq-t3">Agenda 8 minggu</label><?php endif ?>
            </div>
        </div>
        <!-- Batang per entitas -->
        <div class="dq-panel dq-p1">
            <p class="mb-3 text-sm text-muted">Perbandingan skor kelengkapan, validitas, dan kemutakhiran per entitas data</p>
            <div class="dq-bars" role="img" aria-label="Grafik skor per entitas">
                <div class="dq-grid" aria-hidden="true"><?php foreach ([100, 75, 50, 25, 0] as $g): ?><span data-v="<?= $g ?>%"></span><?php endforeach ?></div>
                <?php foreach (DataQuality::ENTITIES as $ek => $en): $row = $q['entities'][$ek] ?? []; ?>
                    <div class="dq-group" tabindex="0">
                        <div class="dq-cols">
                            <?php foreach (DataQuality::DIMS as $dk => $dn): $v = $row[$dk] ?? null; ?>
                                <span class="dq-bar <?= $v === null ? 'is-null' : '' ?>" style="height: <?= $v === null ? 0 : max(1, (float) $v) ?>%; background: <?= $color[$dk] ?>"></span>
                            <?php endforeach ?>
                        </div>
                        <span class="dq-x"><?= esc($en) ?></span>
                        <div class="dq-tip" role="tooltip">
                            <b><?= esc($en) ?></b>
                            <?php foreach (DataQuality::DIMS as $dk => $dn): ?><span style="color: <?= $color[$dk] ?>"><?= $dn ?> : <?= ($row[$dk] ?? null) === null ? 'belum ada data' : $fmt($row[$dk]) ?></span><?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <ul class="dq-legend"><?php foreach (DataQuality::DIMS as $dk => $dn): ?><li style="--c: <?= $color[$dk] ?>"><?= $dn ?></li><?php endforeach ?></ul>
            <table class="sr-only"><caption>Skor per entitas</caption><thead><tr><th>Entitas</th><?php foreach (DataQuality::DIMS as $dn): ?><th><?= $dn ?></th><?php endforeach ?></tr></thead>
                <tbody><?php foreach (DataQuality::ENTITIES as $ek => $en): ?><tr><td><?= esc($en) ?></td><?php foreach (array_keys(DataQuality::DIMS) as $dk): ?><td><?= $fmt($q['entities'][$ek][$dk] ?? null) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table>
        </div>

        <!-- Tren per semester -->
        <div class="dq-panel dq-p2">
            <p class="mb-3 text-sm text-muted">Tren skor total kualitas data <?= $tn ?> semester terakhir</p>
            <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="dq-line" role="img" aria-label="Grafik tren skor per semester">
                <?php foreach ([100, 90, 80, 70, 60] as $g): $gy = $padT + ($H - $padT - $padB) * (1 - ($g - $minY) / (100 - $minY)); ?>
                    <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $gy ?>" y2="<?= $gy ?>" class="dq-gl"/>
                    <text x="<?= $padL - 8 ?>" y="<?= $gy + 4 ?>" class="dq-yl"><?= $g ?>%</text>
                <?php endforeach ?>
                <?php if ($path): ?><path d="<?= $path ?>" class="dq-path"/><?php endif ?>
                <?php foreach ($pts as [$x, $y, $t]): $tw = 150; $tx = min($W - $padR - $tw, max($padL, $x - $tw / 2)); $ty = $y > 120 ? $y - 62 : $y + 14; ?>
                    <g class="dq-pt" tabindex="0">
                        <line x1="<?= $x ?>" x2="<?= $x ?>" y1="<?= $padT ?>" y2="<?= $H - $padB ?>" class="dq-vl"/>
                        <circle cx="<?= $x ?>" cy="<?= $y ?>" r="6"/>
                        <g class="dq-ptip"><rect x="<?= $tx ?>" y="<?= $ty ?>" width="<?= $tw ?>" height="48" rx="10"/>
                            <text x="<?= $tx + 12 ?>" y="<?= $ty + 19 ?>" class="dq-tt"><?= esc($t['label']) ?></text>
                            <text x="<?= $tx + 12 ?>" y="<?= $ty + 37 ?>" class="dq-tv">Skor : <?= $fmt($t['total']) ?></text></g>
                    </g>
                <?php endforeach ?>
                <?php foreach ($trend as $i => $t): $x = $tn > 1 ? $padL + ($W - $padL - $padR) * $i / ($tn - 1) : ($W + $padL - $padR) / 2; ?>
                    <text x="<?= $x ?>" y="<?= $H - 10 ?>" class="dq-xl"><?= esc($t['label']) ?></text>
                <?php endforeach ?>
            </svg>
            <?php if ($tn < 2): ?><p class="text-xs text-muted">Tren bertambah otomatis seiring berjalannya semester berikutnya.</p><?php endif ?>
        </div>
        <?php if (! empty($agendaChart)): ?>
            <!-- Agenda seluruh guru 8 minggu -->
            <div class="dq-panel dq-p3"><?= $agendaChart ?></div>
        <?php endif ?>
    </div>
</section>
