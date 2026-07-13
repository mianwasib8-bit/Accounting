<?php
/**
 * Audit Trail Logger
 */

declare(strict_types=1);

namespace App\Core;

class Audit
{
    public static function log(
        ?int $userId,
        string $action,
        string $module,
        ?string $recordId = null,
        ?string $description = null
    ): void {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $db->query(
                'INSERT INTO audit_trail (user_id, action, module, record_id, description, ip_address)
                 VALUES (:uid, :act, :mod, :rid, :des, :ip)',
                [
                    'uid' => $userId,
                    'act' => $action,
                    'mod' => $module,
                    'rid' => $recordId,
                    'des' => $description,
                    'ip'  => $ip,
                ]
            );
        } catch (\Throwable $e) {
            // Never break main flow for audit failures
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
