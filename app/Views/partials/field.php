<?php
/**
 * @var string $name
 * @var array  $f
 * @var array  $errors
 */
$id      = 'f-' . $name;
$err     = $errors[$name] ?? null;
$type    = $f['type'] ?? 'text';
$value   = $f['value'] ?? '';
$desc    = trim(($f['help'] ?? '' ? $id . '-help ' : '') . ($err ? $id . '-err' : ''));
$attrs   = '';
foreach (($f['attrs'] ?? []) as $k => $v) {
    $attrs .= ' ' . esc($k, 'attr') . '="' . esc((string) $v, 'attr') . '"';
}
$common  = 'id="' . $id . '" name="' . esc($name, 'attr') . '"' . ($f['required'] ? ' required aria-required="true"' : '')
    . ($err ? ' aria-invalid="true"' : '') . ($desc ? ' aria-describedby="' . $desc . '"' : '') . $attrs;
$span    = ($f['span'] ?? '') === 'full' || in_array($type, ['textarea', 'checkboxes'], true) ? 'sm:col-span-2' : '';
?>
<?php if ($type === 'checkbox'): ?>
    <div class="<?= $span ?> sm:col-span-2">
        <input type="hidden" name="<?= esc($name, 'attr') ?>" value="0">
        <label class="check" for="<?= $id ?>">
            <input type="checkbox" value="1" <?= $common ?> <?= (string) $value === '1' || $value === 1 || $value === true ? 'checked' : '' ?>>
            <span class="text-sm font-medium"><?= esc($f['label']) ?></span>
        </label>
        <?php if (! empty($f['help'])): ?><p class="help" id="<?= $id ?>-help"><?= esc($f['help']) ?></p><?php endif ?>
    </div>
<?php elseif ($type === 'checkboxes'): ?>
    <?php $checked = array_map('strval', (array) ($value ?: [])); ?>
    <fieldset class="<?= $span ?> field" <?= isset($f['filterBy']) ? 'data-filter-by="' . esc($f['filterBy'], 'attr') . '"' : '' ?> <?= $desc ? 'aria-describedby="' . $desc . '"' : '' ?>>
        <legend class="label mb-1"><?= esc($f['label']) ?></legend>
        <input type="hidden" name="<?= esc($name, 'attr') ?>[]" value="">
        <?php if (empty($f['groups'])): ?>
            <p class="help">Belum ada data untuk dipilih.</p>
        <?php else: ?>
            <p class="help" data-group-empty hidden>Pilih mata pelajaran untuk menampilkan pilihan.</p>
            <?php foreach ($f['groups'] as $gid => $g): ?>
                <div class="rounded-2xl border border-line p-3" data-group="<?= esc((string) $gid, 'attr') ?>">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted"><?= esc($g['label']) ?></p>
                    <?php foreach ($g['items'] as $v => $l): ?>
                        <label class="check min-h-9">
                            <input type="checkbox" name="<?= esc($name, 'attr') ?>[]" value="<?= esc((string) $v, 'attr') ?>" <?= in_array((string) $v, $checked, true) ? 'checked' : '' ?>>
                            <span class="text-sm"><?= esc($l) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        <?php endif ?>
        <?php if (! empty($f['help'])): ?><p class="help" id="<?= $id ?>-help"><?= esc($f['help']) ?></p><?php endif ?>
    </fieldset>
<?php else: ?>
    <div class="field <?= $span ?>">
        <label class="label" for="<?= $id ?>"><?= esc($f['label']) ?><?php if ($f['required']): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></label>
        <?php if ($type === 'textarea'): ?>
            <textarea class="input" <?= $common ?> rows="4"><?= esc((string) $value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select class="input" <?= $common ?> <?= isset($f['filterBy']) ? 'data-filter-by="' . esc($f['filterBy'], 'attr') . '"' : '' ?>>
                <?php if (isset($f['empty'])): ?><option value=""><?= esc($f['empty']) ?></option><?php endif ?>
                <?php if (isset($f['groups'])): ?>
                    <?php foreach ($f['groups'] as $gid => $g): ?>
                        <optgroup label="<?= esc($g['label'], 'attr') ?>" data-group="<?= esc((string) $gid, 'attr') ?>">
                            <?php foreach ($g['items'] as $v => $l): ?>
                                <option value="<?= esc((string) $v, 'attr') ?>" <?= (string) $v === (string) $value ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                    <?php endforeach ?>
                <?php else: ?>
                    <?php foreach (($f['options'] ?? []) as $v => $l): ?>
                        <option value="<?= esc((string) $v, 'attr') ?>" <?= (string) $v === (string) $value ? 'selected' : '' ?>><?= esc($l) ?></option>
                    <?php endforeach ?>
                <?php endif ?>
            </select>
        <?php else: ?>
            <input class="input" type="<?= esc($type, 'attr') ?>" value="<?= esc((string) $value, 'attr') ?>" <?= $common ?>
                <?= isset($f['placeholder']) ? 'placeholder="' . esc($f['placeholder'], 'attr') . '"' : '' ?>
                <?= $type === 'number' && ! isset($f['attrs']['step']) ? 'step="any"' : '' ?>
                <?= in_array($type, ['text', 'email'], true) ? 'autocomplete="off"' : '' ?>>
        <?php endif ?>
        <?php if (! empty($f['help'])): ?><p class="help" id="<?= $id ?>-help"><?= esc($f['help']) ?></p><?php endif ?>
        <?php if ($err): ?><p class="error-text" id="<?= $id ?>-err"><?= esc($err) ?></p><?php endif ?>
    </div>
<?php endif ?>
