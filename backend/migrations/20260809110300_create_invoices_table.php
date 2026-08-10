<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 7a — faktury wystawione powiązane ze zleceniami.
 *
 * Tabela `invoices`:
 *  - `order_id`        FK → orders.id NULLABLE (ON DELETE SET NULL)
 *  - `numer_faktury`   VARCHAR(50) UNIQUE
 *  - `klient_nazwa`     VARCHAR(255)
 *  - `kwota_pln`       DECIMAL(12,2)
 *  - `data_wystawienia` DATE
 *  - `termin_platnosci` DATE NULL
 *  - `status`          ENUM('wystawiona','zaplacona','przeterminowana')
 *  - `typ_wystawienia` ENUM('po_zleceniu','po_tygodniu','koniec_miesiaca')
 *
 * Indeksy: (order_id, status, typ_wystawienia, data_wystawienia, klient_nazwa)
 * — obsługa filtrowania i detekcji brakujących/przeterminowanych faktur.
 */
final class CreateInvoicesTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `invoices` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT UNSIGNED NULL,
                `numer_faktury` VARCHAR(50) NOT NULL,
                `klient_nazwa` VARCHAR(255) NOT NULL,
                `kwota_pln` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `data_wystawienia` DATE NOT NULL,
                `termin_platnosci` DATE NULL,
                `status` ENUM('wystawiona','zaplacona','przeterminowana') NOT NULL DEFAULT 'wystawiona',
                `typ_wystawienia` ENUM('po_zleceniu','po_tygodniu','koniec_miesiaca') NOT NULL DEFAULT 'po_zleceniu',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_invoices_numer_faktury` (`numer_faktury`),
                INDEX `idx_invoices_order_status_typ_data_klient` (`order_id`, `status`, `typ_wystawienia`, `data_wystawienia`, `klient_nazwa`),
                CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'invoices')) {
            $this->execute($pdo, 'DROP TABLE `invoices`');
        }
    }

    public function name(): string
    {
        return '20260809110300_create_invoices_table';
    }
}

return new CreateInvoicesTable();