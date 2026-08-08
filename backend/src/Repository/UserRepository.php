<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium użytkowników — dostęp do tabeli `users`.
 */
class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `email` = :email LIMIT 1";
        $stmt = $this->executeQuery($sql, [':email' => $email]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * Wyszukiwanie użytkowników z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{email?: string, role?: string, is_active?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'email', 'role', 'is_active', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $email = is_string($filters['email'] ?? null) ? trim($filters['email']) : '';
        if ($email !== '') {
            $where[] = '`email` LIKE :email';
            $params[':email'] = '%' . $email . '%';
        }

        $role = is_string($filters['role'] ?? null) ? $filters['role'] : '';
        if ($role !== '') {
            $where[] = '`role` = :role';
            $params[':role'] = $role;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT `id`, `email`, `role`, `permissions`, `is_active`, `must_change_password`, `created_at`, `updated_at` FROM `users`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{email?: string, role?: string, is_active?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $email = is_string($filters['email'] ?? null) ? trim($filters['email']) : '';
        if ($email !== '') {
            $where[] = '`email` LIKE :email';
            $params[':email'] = '%' . $email . '%';
        }

        $role = is_string($filters['role'] ?? null) ? $filters['role'] : '';
        if ($role !== '') {
            $where[] = '`role` = :role';
            $params[':role'] = $role;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT COUNT(*) FROM `users`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Tworzy użytkownika z placeholder-owym hashem hasła (konto „zaproszony").
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        return $this->create($data);
    }

    /**
     * Aktywacja / blokada konta (is_active).
     */
    public function setActive(int $userId, bool $active): void
    {
        $sql = "UPDATE `{$this->table}` SET `is_active` = :active WHERE `id` = :id";
        $this->executeQuery($sql, [':active' => $active, ':id' => $userId]);
    }

    /**
     * Aktualizuje licznik nieudanych logowań i opcjonalnie ustawia blokadę.
     */
    public function updateFailedLogin(int $userId, int $attempts, ?string $lockedUntil = null): void
    {
        if ($lockedUntil !== null) {
            $sql = "UPDATE `{$this->table}` SET `failed_login_attempts` = :attempts, `locked_until` = :locked WHERE `id` = :id";
            $this->executeQuery($sql, [':attempts' => $attempts, ':locked' => $lockedUntil, ':id' => $userId]);
        } else {
            $sql = "UPDATE `{$this->table}` SET `failed_login_attempts` = :attempts, `locked_until` = NULL WHERE `id` = :id";
            $this->executeQuery($sql, [':attempts' => $attempts, ':id' => $userId]);
        }
    }

    public function resetFailedLogin(int $userId): void
    {
        $sql = "UPDATE `{$this->table}` SET `failed_login_attempts` = 0, `locked_until` = NULL WHERE `id` = :id";
        $this->executeQuery($sql, [':id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $sql = "UPDATE `{$this->table}` SET `password_hash` = :hash, `password_changed_at` = NOW(), `must_change_password` = FALSE, `failed_login_attempts` = 0, `locked_until` = NULL WHERE `id` = :id";
        $this->executeQuery($sql, [':hash' => $passwordHash, ':id' => $userId]);
    }

    /**
     * @param array<string, bool> $permissions
     */
    public function updatePermissions(int $userId, array $permissions): void
    {
        $sql = "UPDATE `{$this->table}` SET `permissions` = :perms WHERE `id` = :id";
        $this->executeQuery($sql, [':perms' => json_encode($permissions, JSON_THROW_ON_ERROR), ':id' => $userId]);
    }
}