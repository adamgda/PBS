<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Migracja: revoked_refresh_tokens — denylist jednorazowych refresh tokenów z TTL.
 */
final class CreateRevokedRefreshTokensTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `revoked_refresh_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `jti` VARCHAR(64) NOT NULL UNIQUE,
                `user_id` INT UNSIGNED NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'revoked_refresh_tokens')) {
            $this->execute($pdo, 'DROP TABLE `revoked_refresh_tokens`');
        }
    }

    public function name(): string
    {
        return '20260807010000_create_revoked_refresh_tokens_table';
    }
}

return new CreateRevokedRefreshTokensTable();