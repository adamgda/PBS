<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateAlertNotificationsTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `alert_notifications` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `alert_config_id` INT UNSIGNED NULL,
                `typ` ENUM('certyfikat_wygasa', 'przeglad_wymagany', 'brak_raportu_oc', 'awaria_zgloszona') NOT NULL,
                `ref_type` VARCHAR(32) NOT NULL,
                `ref_id` INT UNSIGNED NOT NULL,
                `data_wysylki` DATE NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_alert_notification_daily` (`alert_config_id`, `ref_type`, `ref_id`, `data_wysylki`),
                CONSTRAINT `fk_alert_notifications_config` FOREIGN KEY (`alert_config_id`)
                    REFERENCES `alert_settings`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'alert_notifications')) {
            $this->execute($pdo, 'DROP TABLE `alert_notifications`');
        }
    }

    public function name(): string
    {
        return '20260814100000_create_alert_notifications_table';
    }
}

return new CreateAlertNotificationsTable();
