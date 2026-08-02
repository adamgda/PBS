<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateEquipmentTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        // Tabela equipment — FK do employees zostanie dodane w migracji employees (zależność cykliczna).
        $this->execute($pdo, "
            CREATE TABLE `equipment` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `kategoria` ENUM('pojazd', 'inne') NOT NULL,
                `nazwa` VARCHAR(255) NOT NULL,
                `numer_seryjny` VARCHAR(100) NULL,
                `current_employee_id` INT UNSIGNED NULL,
                `current_terminal_id` INT UNSIGNED NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_equipment_terminal` FOREIGN KEY (`current_terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `vehicle_details` (
                `equipment_id` INT UNSIGNED PRIMARY KEY,
                `ostatni_przebieg` INT UNSIGNED NOT NULL DEFAULT 0,
                `ostatni_serwis_olejowy` DATE NULL,
                `ostatnia_awaria` DATETIME NULL,
                `data_ostatniej_oc` DATE NULL,
                `wynik_ostatniej_oc` TEXT NULL,
                CONSTRAINT `fk_vehicle_details_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `vehicle_service_plans` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `equipment_id` INT UNSIGNED NOT NULL,
                `typ_przegladu` VARCHAR(255) NOT NULL,
                `interwal_km` INT UNSIGNED NULL,
                `interwal_dni` INT UNSIGNED NULL,
                `data_ostatniego_wykonania` DATE NULL,
                `data_nastepnego_planowanego` DATE NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT `fk_service_plans_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `equipment_history` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `equipment_id` INT UNSIGNED NOT NULL,
                `typ` ENUM('przebieg', 'serwis', 'awaria', 'przypisanie', 'inne') NOT NULL,
                `opis` TEXT NOT NULL,
                `data` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created_by` INT UNSIGNED NULL,
                CONSTRAINT `fk_equipment_history_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_equipment_history_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'equipment_history')) {
            $this->execute($pdo, 'DROP TABLE `equipment_history`');
        }
        if ($this->tableExists($pdo, 'vehicle_service_plans')) {
            $this->execute($pdo, 'DROP TABLE `vehicle_service_plans`');
        }
        if ($this->tableExists($pdo, 'vehicle_details')) {
            $this->execute($pdo, 'DROP TABLE `vehicle_details`');
        }
        if ($this->tableExists($pdo, 'equipment')) {
            $this->execute($pdo, 'DROP TABLE `equipment`');
        }
    }

    public function name(): string
    {
        return '20260609010200_create_equipment_table';
    }
}

return new CreateEquipmentTable();