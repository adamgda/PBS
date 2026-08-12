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

    /**
     * Lista logów z paginacją, filtrowaniem (akcja, e-mail użytkownika) i sortowaniem.
     * E-mail użytkownika pobierany jest LEFT JOIN-em z tabeli `users` (user_id może być NULL).
     *
     * @param array{action?: string, user_email?: string, sort?: string, direction?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'action', 'resource_type', 'created_at', 'user_id'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $action = is_string($filters['action'] ?? null) ? trim($filters['action']) : '';
        if ($action !== '') {
            $where[] = '`al`.`action` LIKE :action';
            $params[':action'] = '%' . $action . '%';
        }

        $userEmail = is_string($filters['user_email'] ?? null) ? trim($filters['user_email']) : '';
        if ($userEmail !== '') {
            $where[] = '`u`.`email` LIKE :user_email';
            $params[':user_email'] = '%' . $userEmail . '%';
        }

        $sql = 'SELECT `al`.*, `u`.`email` AS `user_email`
                FROM `audit_log` `al`
                LEFT JOIN `users` `u` ON `u`.`id` = `al`.`user_id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `al`.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Licznik logów pasujących do filtrów (dla paginacji).
     *
     * @param array{action?: string, user_email?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $action = is_string($filters['action'] ?? null) ? trim($filters['action']) : '';
        if ($action !== '') {
            $where[] = '`al`.`action` LIKE :action';
            $params[':action'] = '%' . $action . '%';
        }

        $userEmail = is_string($filters['user_email'] ?? null) ? trim($filters['user_email']) : '';
        if ($userEmail !== '') {
            $where[] = '`u`.`email` LIKE :user_email';
            $params[':user_email'] = '%' . $userEmail . '%';
        }

        $sql = 'SELECT COUNT(*) FROM `audit_log` `al`
                LEFT JOIN `users` `u` ON `u`.`id` = `al`.`user_id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Usuwa wszystkie wpisy z audit_log.
     *
     * @return int liczba usuniętych wierszy
     */
    public function clear(): int
    {
        $stmt = $this->executeQuery('DELETE FROM `audit_log`');

        return $stmt->rowCount();
    }
}