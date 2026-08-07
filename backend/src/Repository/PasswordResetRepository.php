<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium password_reset_tokens — jednorazowe tokeny resetujące hasło.
 */
class PasswordResetRepository extends BaseRepository
{
    protected string $table = 'password_reset_tokens';
    protected string $primaryKey = 'id';

    public function createToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $sql = "INSERT INTO `{$this->table}` (`user_id`, `token_hash`, `expires_at`) VALUES (:user_id, :token_hash, :expires_at)";
        $this->executeQuery($sql, [
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByHash(string $tokenHash): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `token_hash` = :hash AND `used_at` IS NULL LIMIT 1";
        $stmt = $this->executeQuery($sql, [':hash' => $tokenHash]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function markUsed(int $tokenId): void
    {
        $sql = "UPDATE `{$this->table}` SET `used_at` = NOW() WHERE `id` = :id";
        $this->executeQuery($sql, [':id' => $tokenId]);
    }

    public function purgeExpired(): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `expires_at` < NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }
}