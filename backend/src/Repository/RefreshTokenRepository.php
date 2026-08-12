<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium revoked_refresh_tokens — denylist jednorazowych refresh tokenów.
 */
class RefreshTokenRepository extends BaseRepository
{
    protected string $table = 'revoked_refresh_tokens';
    protected string $primaryKey = 'id';

    public function revoke(string $jti, int $userId, string $expiresAt): void
    {
        $sql = "INSERT IGNORE INTO `{$this->table}` (`jti`, `user_id`, `expires_at`) VALUES (:jti, :user_id, :expires_at)";
        $this->executeQuery($sql, [
            ':jti' => $jti,
            ':user_id' => $userId,
            ':expires_at' => $expiresAt,
        ]);
    }

    public function isRevoked(string $jti): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `jti` = :jti";
        $stmt = $this->executeQuery($sql, [':jti' => $jti]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }

    /**
     * Usuwa wygasłe wpisy (cleanup).
     */
    public function purgeExpired(): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `expires_at` < NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }
}