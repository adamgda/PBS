<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

final class CreateTerminalsTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `terminals` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `nazwa` VARCHAR(255) NOT NULL,
                `adres` TEXT NOT NULL,
                `operator` VARCHAR(255) NOT NULL,
                `telefon_operatora` VARCHAR(20) NULL,
                `email_operatora` VARCHAR(255) NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'terminals')) {
            $this->execute($pdo, 'DROP TABLE `terminals`');
        }
    }

    public function name(): string
    {
        return '20260609010100_create_terminals_table';
    }
}

return new CreateTerminalsTable();