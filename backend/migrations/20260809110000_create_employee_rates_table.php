<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 7a — historia stawek godzinowych pracowników.
 *
 * Każdy pracownik posiada aktualną stawkę godzinową (PLN/h) edytowalną
 * w dedykowanym oknie „Zmień stawkę". Zmiana stawki wymaga podania daty
 * wejścia w życie — system przechowuje pełną historię zmian. Godziny
 * przepracowane przed tą datą rozliczane są po starej stawce, po dacie —
 * po nowej (historyczność rozliczeń).
 *
 * Tabela `employee_rates`:
 *  - `employee_id`     FK → employees.id ON DELETE CASCADE
 *  - `stawka_godzinowa` DECIMAL(10,2) — PLN/h
 *  - `data_od`         DATE — data wejścia w życie stawki
 *  - `data_do`         DATE NULL — data zamknięcia (ustawiana przy nowej stawce)
 *
 * Indeks: (employee_id, data_od) — szybkie wyszukiwanie aktualnej/histrycznej stawki.
 */
final class CreateEmployeeRatesTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `employee_rates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `employee_id` INT UNSIGNED NOT NULL,
                `stawka_godzinowa` DECIMAL(10,2) NOT NULL,
                `data_od` DATE NOT NULL,
                `data_do` DATE NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_employee_rates_employee_data_od` (`employee_id`, `data_od`),
                CONSTRAINT `fk_employee_rates_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'employee_rates')) {
            $this->execute($pdo, 'DROP TABLE `employee_rates`');
        }
    }

    public function name(): string
    {
        return '20260809110000_create_employee_rates_table';
    }
}

return new CreateEmployeeRatesTable();