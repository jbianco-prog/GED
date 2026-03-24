<?php
// src/Models/AuditLog.php

class AuditLog {
    public static function log(
        ?int    $userId,
        string  $action,
        ?string $cibleType = null,
        ?int    $cibleId   = null,
        ?string $detail    = null
    ): void {
        $ip = self::getIp();
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, action, cible_type, cible_id, detail, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $action, $cibleType, $cibleId, $detail, $ip]
            );
        } catch (Exception $e) {
            error_log('AuditLog::log failed: ' . $e->getMessage());
        }
    }

    private static function getIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }

    public static function getAll(int $limit = 200, int $offset = 0): array {
        return Database::fetchAll(
            'SELECT l.*, u.nom as user_nom, u.email as user_email
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.date_action DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function count(): int {
        return (int) Database::fetchOne('SELECT COUNT(*) as c FROM audit_logs')['c'];
    }
}
