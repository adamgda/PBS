<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 7 — fizyczne usuwanie pracowników.
 *
 * Zmienia FK `order_employees.employee_id` z `ON DELETE CASCADE` na
 * `ON DELETE SET NULL` (pole NULLABLE), by zachować historię przypisań do
 * zleceń po fizycznym usunięciu pracownika. Powiązania z orphan `employee_id`
 * (NULL) są interpretowane przez frontend jako „Pracownik usunięty".
 *
 * `employee_documents` (CASCADE) i `equipment.current_employee_id` (SET NULL)
 * pozostają bez zmian — dokumenty usuniętego pracownika usuwają się same,
 * a bieżące przypisanie sprzętu zostaje wyczyszczone.
 */
final class AlterOrderEmployeesEmployeeNullable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'order_employees')) {
            return;
        }

        // Sprawdź czy FK istnieje przed próbą usunięcia (MySQL nie ma IF EXISTS dla DROP FOREIGN KEY).
        if ($this->foreignKeyExists($pdo, 'order_employees', 'fk_order_employees_employee')) {
            $this->execute($pdo, 'ALTER TABLE `order_employees` DROP FOREIGN KEY `fk_order_employees_employee`');
        }

        $this->execute($pdo, 'ALTER TABLE `order_employees` MODIFY `employee_id` INT UNSIGNED NULL');

        $this->execute($pdo, "
            ALTER TABLE `order_employees`
            ADD CONSTRAINT `fk_order_employees_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
        ");
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'order_employees')) {
            return;
        }

        // Wyrzuć wiersze z NULL'em employee_id, by przywrócić NOT NULL.
        $this->execute($pdo, 'DELETE FROM `order_employees` WHERE `employee_id` IS NULL');

        if ($this->foreignKeyExists($pdo, 'order_employees', 'fk_order_employees_employee')) {
            $this->execute($pdo, 'ALTER TABLE `order_employees` DROP FOREIGN KEY `fk_order_employees_employee`');
        }

        $this->execute($pdo, 'ALTER TABLE `order_employees` MODIFY `employee_id` INT UNSIGNED NOT NULL');

        $this->execute($pdo, "
            ALTER TABLE `order_employees`
            ADD CONSTRAINT `fk_order_employees_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
        ");
    }

    public function name(): string
    {
        return '20260809010000_alter_order_employees_employee_nullable';
    }

    private function foreignKeyExists(PDO $pdo, string $table, string $constraintName): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = \'FOREIGN KEY\'';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $constraintName]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}

return new AlterOrderEmployeesEmployeeNullable();