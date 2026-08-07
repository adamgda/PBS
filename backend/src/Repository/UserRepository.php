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