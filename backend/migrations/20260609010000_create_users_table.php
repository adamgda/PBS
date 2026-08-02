<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user',
                `permissions` JSON NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'users')) {
            $this->execute($pdo, 'DROP TABLE `users`');
        }
    }

    public function name(): string
    {
        return '20260609010000_create_users_table';
    }
}

return new CreateUsersTable();