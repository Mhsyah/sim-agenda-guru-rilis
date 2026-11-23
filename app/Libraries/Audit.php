<?php

namespace App\Libraries;

use App\Models\AuditLogModel;
use Throwable;

/** Pencatat audit perubahan sensitif. Waktu disimpan dalam UTC. */
final class Audit
{
    public static function log(string $action, string $entityType, ?int $entityId = null, $old = null, $new = null): void
    {
        try {
            $request = service('request');
            (new AuditLogModel())->insert([
                'user_id'     => Auth::id(),
                'action'      => substr($action, 0, 50),
                'entity_type' => substr($entityType, 0, 50),
                'entity_id'   => $entityId,
                'old_values'  => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                'new_values'  => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
                'ip_address'  => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Audit gagal: ' . $e->getMessage());
        }
    }
}
