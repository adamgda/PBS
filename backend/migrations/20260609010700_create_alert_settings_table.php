<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateAlertSettingsTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `alert_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `email_odbiorcy` VARCHAR(255) NOT NULL,
                `typ_alertu` ENUM('certyfikat_wygasa', 'przeglad_wymagany', 'brak_raportu_oc', 'awaria_zgloszona') NOT NULL,
                `czy_aktywny` BOOLEAN NOT NULL DEFAULT TRUE,
                `czas_wysylki` TIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'alert_settings')) {
            $this->execute($pdo, 'DROP TABLE `alert_settings`');
        }
    }

    public function name(): string
    {
        return '20260609010700_create_alert_settings_table';
    }
}

return new CreateAlertSettingsTable();