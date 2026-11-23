<?php
/**
 * Pemilih "Mata pelajaran & kelas yang diajarkan" (dipakai admin di Guru & Pengguna dan guru di Profil Saya).
 *
 * @var array<int, string>            $subjects  semua mapel aktif
 * @var array<int, string>            $classes   kelas tahun pelajaran aktif
 * @var list<string>                  $checked   id mapel yang dicentang
 * @var array<int, list<string>>      $teach     subject_id => [class_group_id]
 * @var bool                          $canCreate admin boleh menambah mapel baru dari sini
 * @var string                        $help
 */
$canCreate ??= false;
$help      ??= 'Centang mapel, lalu pilih kelas yang diajar.';
// Kelompokkan kelas per tingkat ("VII A" → tingkat VII, label "A") agar chip pendek & rapi.
$grades = [];
foreach ($classes as $cid => $cname) {
    $g = preg_match('/^(.+?)\s+(\S+)$/u', (string) $cname, $m) ? [$m[1], $m[2]] : [(string) $cname, (string) $cname];
    $grades[$g[0]][(int) $cid] = [$g[1], (string) $cname];
}
uksort($grades, 'strnatcmp');
$row = static function (int $sid, string $sname, bool $on, array $picked) use ($grades): string {
    $n = count($picked);
    ob_start(); ?>
    <div class="teach-row<?= $on ? ' is-on' : '' ?>" data-teach-row>
        <div class="teach-head">
            <label class="check teach-subject">
                <input type="checkbox" name="subject_ids[]" value="<?= $sid ?>" <?= $on ? 'checked' : '' ?> data-teach-subject>
                <span class="text-sm font-semibold"><?= esc($sname) ?></span>
            </label>
            <span class="teach-count" data-teach-count><?= $n ? $n . ' kelas' : '' ?></span>
        </div>
        <div class="teach-classes" data-teach-classes>
            <?php if (! $grades): ?>
                <span class="text-xs text-muted">Belum ada kelas di tahun pelajaran ini.</span>
            <?php else: ?>
                <?php foreach ($grades as $grade => $list): ?>
                    <div class="teach-grade">
                        <span class="teach-grade-name"><?= esc($grade) ?></span>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($list as $cid => [$short, $full]): ?>
                                <label class="teach-chip" title="<?= esc($full, 'attr') ?>"><input type="checkbox" name="teach[<?= $sid ?>][]" value="<?= $cid ?>" <?= in_array((string) $cid, $picked, true) ? 'checked' : '' ?> data-teach-class aria-label="<?= esc($full . ' — ' . $sname, 'attr') ?>"><span><?= esc($short) ?></span></label>
                            <?php endforeach ?>
                        </div>
                        <button type="button" class="teach-all" data-teach-all title="Pilih/lepas semua kelas <?= esc($grade, 'attr') ?>">Semua</button>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<fieldset class="teach" data-teach>
    <legend class="label mb-1">Mata pelajaran &amp; kelas yang diajarkan</legend>
    <p class="help mb-3"><?= esc($help) ?></p>
    <input type="hidden" name="subject_ids[]" value="">
    <div class="teach-list" data-teach-list>
        <?php if (! $subjects): ?>
            <p class="text-sm text-muted" data-teach-empty>Belum ada mata pelajaran.</p>
        <?php endif ?>
        <?php foreach ($subjects as $sid => $sname):
            $picked = array_map('strval', (array) ($teach[$sid] ?? []));
            echo $row((int) $sid, $sname, in_array((string) $sid, $checked, true) || $picked !== [], $picked);
        endforeach ?>
    </div>

    <?php if ($canCreate): ?>
        <!-- Tambah mapel baru tanpa meninggalkan formulir (admin) -->
        <div class="teach-new mt-3" data-teach-new data-url="<?= site_url('subjects/quick') ?>">
            <p class="text-sm font-semibold"><?= icon('plus', 'size-4') ?> Tambah mata pelajaran baru</p>
            <div class="mt-2 flex flex-wrap items-end gap-2">
                <div class="field min-w-48 flex-1">
                    <label class="label text-xs" for="tn-name">Nama mapel</label>
                    <input class="input h-10 min-h-0 text-sm" id="tn-name" maxlength="100" placeholder="Mis. Bahasa Inggris" data-teach-new-name>
                </div>
                <div class="field w-28">
                    <label class="label text-xs" for="tn-code">Kode</label>
                    <input class="input h-10 min-h-0 text-sm uppercase" id="tn-code" maxlength="20" placeholder="BIG" data-teach-new-code>
                </div>
                <button type="button" class="btn btn-ghost h-10" data-teach-new-add><?= icon('plus', 'size-4') ?> Tambahkan</button>
            </div>
            <p class="mt-1.5 text-xs" data-teach-new-msg aria-live="polite"></p>
        </div>
        <!-- Templat baris untuk mapel yang baru dibuat -->
        <template data-teach-template><?= $row(0, '__NAME__', true, []) ?></template>
    <?php endif ?>
</fieldset>
