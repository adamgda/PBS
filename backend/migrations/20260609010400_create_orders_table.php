<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateOrdersTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `orders` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `numer_zlecenia` VARCHAR(50) NOT NULL UNIQUE,
                `klient_nazwa` VARCHAR(255) NOT NULL,
                `terminal_id` INT UNSIGNED NOT NULL,
                `data_rozpoczecia` DATETIME NOT NULL,
                `data_zakonczenia` DATETIME NOT NULL,
                `zakres_prac` TEXT NOT NULL,
                `wartosc_pln` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `status` ENUM('nowe', 'w_realizacji', 'zakonczone') NOT NULL DEFAULT 'nowe',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_orders_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `order_employees` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                UNIQUE KEY `uq_order_employee` (`order_id`, `employee_id`),
                CONSTRAINT `fk_order_employees_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_order_employees_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->execute($pdo, "
            CREATE TABLE `order_equipment` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT UNSIGNED NOT NULL,
                `equipment_id` INT UNSIGNED NOT NULL,
                UNIQUE KEY `uq_order_equipment` (`order_id`, `equipment_id`),
                CONSTRAINT `fk_order_equipment_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_order_equipment_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'order_equipment')) {
            $this->execute($pdo, 'DROP TABLE `order_equipment`');
        }
        if ($this->tableExists($pdo, 'order_employees')) {
            $this->execute($pdo, 'DROP TABLE `order_employees`');
        }
        if ($this->tableExists($pdo, 'orders')) {
            $this->execute($pdo, 'DROP TABLE `orders`');
        }
    }

    public function name(): string
    {
        return '20260609010400_create_orders_table';
    }
}

return new CreateOrdersTable();