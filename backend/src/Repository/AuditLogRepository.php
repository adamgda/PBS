<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium audit_log — append-only log akcji bezpieczeństwa.
 */
class AuditLogRepository extends BaseRepository
{
    protected string $table = 'audit_log';
    protected string $primaryKey = 'id';

    /**
     * @param array<string, mixed>|null $details
     */
    public function log(
        ?int $userId,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $details = null,
    ): void {
        $sql = "INSERT INTO `{$this->table}` (`user_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `user_agent`, `details`)
                VALUES (:user_id, :action, :resource_type, :resource_id, :ip_address, :user_agent, :details)";
        $this->executeQuery($sql, [
            ':user_id' => $userId,
            ':action' => $action,
            ':resource_type' => $resourceType,
            ':resource_id' => $resourceId,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':details' => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        ]);
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function logFromRequest(
        ?int $userId,
        string $action,
        \App\Http\Request $request,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $details = null,
    ): void {
        $this->log(
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $this->getClientIp($request),
            $request->header('User-Agent'),
            $details,
        );
    }

    private function getClientIp(\App\Http\Request $request): string
    {
        // Uwzględniamy X-Forwarded-For tylko jeśli jest ustawiony
        $forwarded = $request->header('X-Forwarded-For');
        if ($forwarded !== null && $forwarded !== '') {
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
            if ($ip !== '') {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}