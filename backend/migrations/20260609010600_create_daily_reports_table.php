<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateDailyReportsTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `daily_terminal_reports` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `terminal_id` INT UNSIGNED NOT NULL,
                `data_raportu` DATE NOT NULL,
                `opis` TEXT NOT NULL,
                `uwagi` TEXT NULL,
                `utworzony_przez` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_terminal_report_date` (`terminal_id`, `data_raportu`),
                CONSTRAINT `fk_daily_terminal_reports_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_daily_terminal_reports_user` FOREIGN KEY (`utworzony_przez`) REFERENCES `users`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `daily_vehicle_reports` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `equipment_id` INT UNSIGNED NOT NULL,
                `data_raportu` DATE NOT NULL,
                `aktualny_przebieg` INT UNSIGNED NOT NULL DEFAULT 0,
                `przebieg_oc` TEXT NOT NULL,
                `uwagi` TEXT NULL,
                `utworzony_przez` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_vehicle_report_date` (`equipment_id`, `data_raportu`),
                CONSTRAINT `fk_daily_vehicle_reports_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_daily_vehicle_reports_user` FOREIGN KEY (`utworzony_przez`) REFERENCES `users`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'daily_vehicle_reports')) {
            $this->execute($pdo, 'DROP TABLE `daily_vehicle_reports`');
        }
        if ($this->tableExists($pdo, 'daily_terminal_reports')) {
            $this->execute($pdo, 'DROP TABLE `daily_terminal_reports`');
        }
    }

    public function name(): string
    {
        return '20260609010600_create_daily_reports_table';
    }
}

return new CreateDailyReportsTable();