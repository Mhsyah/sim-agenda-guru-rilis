<?php

namespace App\Libraries;

use App\Models\AuditLogModel;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Status akun & riwayat perubahan status (dibangun dari log audit):
 * dibuat, diaktifkan/dinonaktifkan, peran berubah, kata sandi diatur ulang/diganti, login pertama.
 */
final class AccountHistory
{
    /** @return array{key: string, label: string, tone: string, icon: string} */
    public static function status(array $user): array
    {
        if (! (int) $user['is_active']) {
            return ['key' => 'inactive', 'label' => 'Nonaktif', 'tone' => 'warning', 'icon' => 'x'];
        }
        if (empty($user['last_login_at'])) {
            return ['key' => 'new', 'label' => 'Aktif · belum pernah login', 'tone' => 'info', 'icon' => 'clock'];
        }

        return ['key' => 'active', 'label' => 'Aktif', 'tone' => 'success', 'icon' => 'check'];
    }

    /**
     * @return list<array{title: string, actor: string, at: string, note: ?string, tone: string}>
     */
    public static function timeline(int $userId, int $limit = 30): array
    {
        $rows = (new AuditLogModel())
            ->select('audit_logs.action, audit_logs.old_values, audit_logs.new_values, audit_logs.created_at, audit_logs.user_id, u.name AS actor')
            ->join('users u', 'u.id = audit_logs.user_id', 'left')
            ->where('audit_logs.entity_type', 'user')->where('audit_logs.entity_id', $userId)
            ->whereIn('audit_logs.action', ['create', 'update', 'password_reset', 'password_change', 'login'])
            ->orderBy('audit_logs.created_at', 'ASC')->orderBy('audit_logs.id', 'ASC')
            ->findAll(500);

        $roles  = Auth::ROLES;
        $out    = [];
        $logged = false;
        foreach ($rows as $r) {
            $old   = json_decode((string) $r['old_values'], true) ?: [];
            $new   = json_decode((string) $r['new_values'], true) ?: [];
            $actor = (int) $r['user_id'] === $userId ? 'Oleh pemilik akun' : ($r['actor'] ?: 'Sistem');
            $when  = self::local($r['created_at']);
            switch ($r['action']) {
                case 'create':
                    $out[] = ['title' => 'Akun dibuat', 'actor' => $actor, 'at' => $when, 'tone' => 'info',
                        'note' => 'Peran: ' . ($roles[$new['role'] ?? ''] ?? '—') . ' · ' . (! empty($new['is_active']) ? 'Aktif' : 'Nonaktif')];
                    break;
                case 'update':
                    if (array_key_exists('is_active', $new) && isset($old['is_active']) && (int) $old['is_active'] !== (int) $new['is_active']) {
                        $out[] = (int) $new['is_active']
                            ? ['title' => 'Nonaktif → Aktif', 'actor' => $actor, 'at' => $when, 'note' => 'Akun diaktifkan kembali', 'tone' => 'success']
                            : ['title' => 'Aktif → Nonaktif', 'actor' => $actor, 'at' => $when, 'note' => 'Akun dinonaktifkan; tidak dapat login', 'tone' => 'warning'];
                    }
                    if (isset($new['role'], $old['role']) && $old['role'] !== $new['role']) {
                        $out[] = ['title' => ($roles[$old['role']] ?? $old['role']) . ' → ' . ($roles[$new['role']] ?? $new['role']), 'actor' => $actor, 'at' => $when, 'note' => 'Peran diubah', 'tone' => 'info'];
                    }
                    break;
                case 'password_reset':
                    $out[] = ['title' => 'Kata sandi diatur ulang', 'actor' => $actor, 'at' => $when, 'note' => null, 'tone' => 'neutral'];
                    break;
                case 'password_change':
                    $out[] = ['title' => 'Kata sandi diganti', 'actor' => $actor, 'at' => $when, 'note' => null, 'tone' => 'neutral'];
                    break;
                case 'login':
                    if (! $logged) {
                        $logged = true;
                        $out[] = ['title' => 'Login pertama', 'actor' => 'Oleh pemilik akun', 'at' => $when, 'note' => null, 'tone' => 'success'];
                    }
                    break;
            }
        }

        return array_slice($out, -$limit);
    }

    public static function local(?string $utc): string
    {
        if (! $utc) {
            return '—';
        }
        try {
            $tz = new DateTimeZone(\App\Controllers\BaseController::schoolProfile()['timezone'] ?? 'Asia/Jakarta');
            $d  = (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone($tz);

            return fmt_date($d->format('Y-m-d'), true) . ' ' . $d->format('H.i');
        } catch (Throwable $e) {
            return (string) $utc;
        }
    }
}
