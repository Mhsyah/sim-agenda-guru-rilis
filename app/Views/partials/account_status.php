<?php
/**
 * Kartu status akun + riwayat status (timeline).
 *
 * @var array $acct pengguna yang ditampilkan
 */
use App\Libraries\AccountHistory;

$st   = AccountHistory::status($acct);
$hist = AccountHistory::timeline((int) $acct['id']);
?>
<section class="card" aria-labelledby="acct-status-title">
    <h2 id="acct-status-title" class="text-[0.95rem] font-semibold tracking-tight">Status</h2>
    <p class="mt-3"><span class="badge badge-<?= esc($st['tone'], 'attr') ?> acct-badge"><?= icon($st['icon'], 'size-3.5') ?> <?= esc($st['label']) ?></span></p>
    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-muted [&_dd]:whitespace-nowrap">
        <dt>Login terakhir</dt><dd class="text-ink"><?= ! empty($acct['last_login_at']) ? esc(fmt_date(substr($acct['last_login_at'], 0, 10), true) . ' ' . substr($acct['last_login_at'], 11, 5)) : 'Belum pernah' ?></dd>
        <dt>Terdaftar</dt><dd class="text-ink"><?= ! empty($acct['created_at']) ? esc(fmt_date(substr($acct['created_at'], 0, 10), true)) : '—' ?></dd>
    </dl>
</section>

<section class="card mt-4" aria-labelledby="acct-hist-title">
    <h2 id="acct-hist-title" class="text-[0.95rem] font-semibold tracking-tight">Riwayat Status</h2>
    <?php if (! $hist): ?>
        <p class="mt-3 text-sm text-muted">Belum ada riwayat tercatat untuk akun ini.</p>
    <?php else: ?>
        <ol class="timeline mt-4">
            <?php foreach ($hist as $h): ?>
                <li class="timeline-item tl-<?= esc($h['tone'], 'attr') ?>">
                    <p class="text-sm font-semibold"><?= esc($h['title']) ?></p>
                    <p class="text-xs text-muted"><?= esc($h['actor']) ?> · <?= esc($h['at']) ?></p>
                    <?php if ($h['note']): ?><p class="timeline-note"><?= esc($h['note']) ?></p><?php endif ?>
                </li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>
</section>
