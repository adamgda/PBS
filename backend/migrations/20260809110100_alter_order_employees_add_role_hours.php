<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 7a — rola dnia i godziny w przypisaniu pracownika do zlecenia.
 *
 * Rozszerza `order_employees` o:
 *  - `rola`    ENUM('operator','brygadzista','sztauer','lukowy','operator_zurawia') NULLABLE
 *  - `godziny` DECIMAL(5,2) NULLABLE — liczba przepracowanych godzin w zleceniu
 *
 * Indeks złożony (rola, employee_id) — agregacje rozliczeń per rola/pracownik.
 */
final class AlterOrderEmployeesAddRoleHours extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'order_employees')) {
            return;
        }

        $this->execute($pdo, "
            ALTER TABLE `order_employees`
            ADD COLUMN `rola` ENUM('operator','brygadzista','sztauer','lukowy','operator_zurawia') NULL AFTER `employee_id`,
            ADD COLUMN `godziny` DECIMAL(5,2) NULL AFTER `rola`
        ");

        $this->execute($pdo, "
            ALTER TABLE `order_employees`
            ADD INDEX `idx_order_employees_rola_employee` (`rola`, `employee_id`)
        ");
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'order_employees')) {
            return;
        }

        $this->execute($pdo, 'ALTER TABLE `order_employees` DROP INDEX `idx_order_employees_rola_employee`');
        $this->execute($pdo, 'ALTER TABLE `order_employees` DROP COLUMN `godziny`');
        $this->execute($pdo, 'ALTER TABLE `order_employees` DROP COLUMN `rola`');
    }

    public function name(): string
    {
        return '20260809110100_alter_order_employees_add_role_hours';
    }
}

return new AlterOrderEmployeesAddRoleHours();