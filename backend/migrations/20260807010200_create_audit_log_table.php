<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Migracja: audit_log — append-only log akcji bezpieczeństwa (retencja 12 miesięcy).
 */
final class CreateAuditLogTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `audit_log` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `resource_type` VARCHAR(100) NULL,
                `resource_id` INT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `details` JSON NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_id_created_at` (`user_id`, `created_at`),
                INDEX `idx_action` (`action`),
                INDEX `idx_resource` (`resource_type`, `resource_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'audit_log')) {
            $this->execute($pdo, 'DROP TABLE `audit_log`');
        }
    }

    public function name(): string
    {
        return '20260807010200_create_audit_log_table';
    }
}

return new CreateAuditLogTable();