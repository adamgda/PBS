<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateEmployeesTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `employees` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `imie` VARCHAR(100) NOT NULL,
                `nazwisko` VARCHAR(100) NOT NULL,
                `telefon` VARCHAR(20) NULL,
                `email` VARCHAR(255) NULL,
                `current_terminal_id` INT UNSIGNED NULL,
                `current_sprzet_id` INT UNSIGNED NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_employees_terminal` FOREIGN KEY (`current_terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_employees_equipment` FOREIGN KEY (`current_sprzet_id`) REFERENCES `equipment`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Uzupełnij odroczony FK: equipment.current_employee_id → employees.id
        $this->execute($pdo, "
            ALTER TABLE `equipment`
            ADD CONSTRAINT `fk_equipment_employee` FOREIGN KEY (`current_employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
        ");

        $this->execute($pdo, "
            CREATE TABLE `employee_documents` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `employee_id` INT UNSIGNED NOT NULL,
                `nazwa` VARCHAR(255) NOT NULL,
                `numer_dokumentu` VARCHAR(100) NULL,
                `data_wydania` DATE NULL,
                `data_waznosci` DATE NULL,
                `plik` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_employee_documents_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'employee_documents')) {
            $this->execute($pdo, 'DROP TABLE `employee_documents`');
        }

        // Usuń FK equipment → employees przed usunięciem tabeli employees.
        $this->execute($pdo, "
            ALTER TABLE `equipment` DROP FOREIGN KEY `fk_equipment_employee`
        ");

        if ($this->tableExists($pdo, 'employees')) {
            $this->execute($pdo, 'DROP TABLE `employees`');
        }
    }

    public function name(): string
    {
        return '20260609010300_create_employees_table';
    }
}

return new CreateEmployeesTable();