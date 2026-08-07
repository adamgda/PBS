<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Migracja: password_reset_tokens — jednorazowe tokeny resetujące hasło (TTL 1h).
 */
final class CreatePasswordResetTokensTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `password_reset_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL UNIQUE,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'password_reset_tokens')) {
            $this->execute($pdo, 'DROP TABLE `password_reset_tokens`');
        }
    }

    public function name(): string
    {
        return '20260807010100_create_password_reset_tokens_table';
    }
}

return new CreatePasswordResetTokensTable();