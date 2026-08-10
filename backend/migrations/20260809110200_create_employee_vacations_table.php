<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 7a — urlopy pracowników.
 *
 * Tabela `employee_vacations`:
 *  - `employee_id` FK → employees.id ON DELETE CASCADE
 *  - `data_od` / `data_do` — zakres urlopu
 *  - `typ`    ENUM('wypoczynkowy','na_zadanie','L4')
 *  - `status` ENUM('oczekujacy','zatwierdzony','odrzucony','zrealizowany')
 *
 * Indeks (employee_id, status) — szybkie filtrowanie aktywnych urlopów.
 */
final class CreateEmployeeVacationsTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `employee_vacations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `employee_id` INT UNSIGNED NOT NULL,
                `data_od` DATE NOT NULL,
                `data_do` DATE NOT NULL,
                `typ` ENUM('wypoczynkowy','na_zadanie','L4') NOT NULL DEFAULT 'wypoczynkowy',
                `status` ENUM('oczekujacy','zatwierdzony','odrzucony','zrealizowany') NOT NULL DEFAULT 'oczekujacy',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_employee_vacations_employee_status` (`employee_id`, `status`),
                CONSTRAINT `fk_employee_vacations_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'employee_vacations')) {
            $this->execute($pdo, 'DROP TABLE `employee_vacations`');
        }
    }

    public function name(): string
    {
        return '20260809110200_create_employee_vacations_table';
    }
}

return new CreateEmployeeVacationsTable();