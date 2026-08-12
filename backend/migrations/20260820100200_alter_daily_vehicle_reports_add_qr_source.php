<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 20 — Kody QR dla maszyn.
 *
 * Rozszerza `daily_vehicle_reports` o:
 *  - `zrodlo` ENUM('panel','qr') DEFAULT 'panel' — źródło raportu,
 *  - dopuszcza NULL dla `utworzony_przez` (anonimowy raport OC z QR).
 *
 * Dodaje INDEX(zrodlo) dla filtrowania.
 */
final class AlterDailyVehicleReportsAddQrSource extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'daily_vehicle_reports')) {
            return;
        }

        if (!$this->columnExists($pdo, 'daily_vehicle_reports', 'zrodlo')) {
            $this->execute($pdo, "ALTER TABLE `daily_vehicle_reports` ADD COLUMN `zrodlo` ENUM('panel','qr') NOT NULL DEFAULT 'panel' AFTER `utworzony_przez`");
        }

        if ($this->foreignKeyExists($pdo, 'daily_vehicle_reports', 'fk_daily_vehicle_reports_user')) {
            $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` DROP FOREIGN KEY `fk_daily_vehicle_reports_user`');
        }

        $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` MODIFY `utworzony_przez` INT UNSIGNED NULL');

        $this->execute($pdo, "
            ALTER TABLE `daily_vehicle_reports`
            ADD CONSTRAINT `fk_daily_vehicle_reports_user` FOREIGN KEY (`utworzony_przez`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ");

        $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` ADD KEY `idx_daily_vehicle_reports_zrodlo` (`zrodlo`)');
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'daily_vehicle_reports')) {
            return;
        }

        $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` DROP KEY `idx_daily_vehicle_reports_zrodlo`');

        if ($this->foreignKeyExists($pdo, 'daily_vehicle_reports', 'fk_daily_vehicle_reports_user')) {
            $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` DROP FOREIGN KEY `fk_daily_vehicle_reports_user`');
        }

        $this->execute($pdo, 'DELETE FROM `daily_vehicle_reports` WHERE `utworzony_przez` IS NULL');

        $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` MODIFY `utworzony_przez` INT UNSIGNED NOT NULL');

        $this->execute($pdo, "
            ALTER TABLE `daily_vehicle_reports`
            ADD CONSTRAINT `fk_daily_vehicle_reports_user` FOREIGN KEY (`utworzony_przez`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ");

        if ($this->columnExists($pdo, 'daily_vehicle_reports', 'zrodlo')) {
            $this->execute($pdo, 'ALTER TABLE `daily_vehicle_reports` DROP COLUMN `zrodlo`');
        }
    }

    public function name(): string
    {
        return '20260820100200_alter_daily_vehicle_reports_add_qr_source';
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $stmt->execute([$table, $column]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
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

return new AlterDailyVehicleReportsAddQrSource();
