<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$tz = new DateTimeZone($profile['timezone'] ?? 'Asia/Jakarta');
$local = static function (?string $utc) use ($tz) {
    if (! $utc) {
        return '—';
    }
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone($tz);

    return fmt_date($d->format('Y-m-d')) . ' ' . $d->format('H:i:s');
};
$short = static fn (?string $json) => $json ? esc(mb_strimwidth($json, 0, 220, '…')) : '—';
?>
<h1 class="page-title"><?= esc($title) ?></h1>
<p class="mt-2 max-w-2xl text-muted">Perubahan nilai, bobot, impor, arsip, backup/restore, dan tindakan sensitif lain dicatat di sini. Waktu disimpan dalam UTC dan ditampilkan sesuai zona waktu profil.</p>

<form method="get" class="filters card my-5 flex flex-wrap items-end gap-3 p-4 sm:p-4">
    <div class="field min-w-56">
        <label class="label" for="et">Jenis entitas</label>
        <select class="input" id="et" name="entity_type">
            <option value="">Semua</option>
            <?php foreach ($types as $t): ?><option value="<?= esc($t, 'attr') ?>" <?= $t === service('request')->getGet('entity_type') ? 'selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
        </select>
    </div>
    <button class="btn btn-ghost">Terapkan</button>
</form>

<!-- Hapus log -->
<form method="post" action="<?= site_url('settings/audit/delete') ?>" id="audit-del" class="card mb-5 flex flex-wrap items-end gap-3 p-4 sm:p-4" data-audit-del>
    <?= csrf_field() ?>
    <input type="hidden" name="entity_type" value="<?= esc((string) service('request')->getGet('entity_type'), 'attr') ?>">
    <div class="field min-w-64">
        <label class="label" for="ad-mode">Hapus log</label>
        <select class="input" id="ad-mode" name="mode" data-audit-mode>
            <option value="selected">Log yang dicentang (0)</option>
            <option value="older_30">Lebih lama dari 30 hari</option>
            <option value="older_90">Lebih lama dari 90 hari</option>
            <option value="older_180">Lebih lama dari 6 bulan</option>
            <option value="older_365">Lebih lama dari 1 tahun</option>
            <option value="filtered"><?= service('request')->getGet('entity_type') ? 'Semua log jenis ini' : 'Semua log' ?></option>
        </select>
    </div>
    <button class="btn btn-ghost text-bad"><?= icon('trash', 'size-4') ?> Hapus log</button>
    <p class="basis-full text-xs text-muted">Total <?= number_format((int) $total, 0, ',', '.') ?> entri. Setiap penghapusan dicatat sebagai satu entri baru “audit_delete” (entri lama “audit_delete” juga bisa dicentang dan dihapus).</p>
</form>

<section class="card p-0 sm:p-0">
    <div class="table-wrap">
        <table class="table">
            <caption>Log audit</caption>
            <thead><tr><th scope="col" class="w-10"><input type="checkbox" class="bulk-check" data-audit-all aria-label="Pilih semua log di halaman ini"></th><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Tindakan</th><th scope="col">Entitas</th><th scope="col">Sebelum</th><th scope="col">Sesudah</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><input type="checkbox" class="bulk-check" name="ids[]" value="<?= (int) $r['id'] ?>" form="audit-del" data-audit-item aria-label="Pilih log ini"></td>
                    <td class="whitespace-nowrap text-xs"><?= $local($r['created_at']) ?></td>
                    <td><?= esc($r['user_name'] ?? '—') ?></td>
                    <td><?= badge($r['action'], str_contains($r['action'], 'fail') || str_contains($r['action'], 'block') || $r['action'] === 'audit_delete' ? 'danger' : 'info') ?></td>
                    <td class="text-xs"><?= esc($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td>
                    <td class="max-w-64 break-all font-mono text-[0.7rem] text-muted"><?= $short($r['old_values']) ?></td>
                    <td class="max-w-64 break-all font-mono text-[0.7rem] text-muted"><?= $short($r['new_values']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="px-5 pb-5"><?= $pager->links() ?></div>
</section>
<?= $this->endSection() ?>
