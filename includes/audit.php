<?php
function logAudit(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            $ipAddress,
            $userAgent
        ]);
    } catch (Throwable $e) {
        // Do not break main workflow if audit logging fails in V1.
        return;
    }
}
