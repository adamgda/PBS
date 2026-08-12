<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 20 — Kody QR dla maszyn.
 *
 * Rozszerza `incidents` o:
 *  - `zrodlo` ENUM('panel','qr') DEFAULT 'panel' — źródło zgłoszenia
 *    (panel aplikacji / publiczna naklejka QR),
 *  - dopuszcza NULL dla `zgloszona_przez` (anonimowe zgłoszenia z QR).
 *
 * Dodaje INDEX(zrodlo) dla filtrowania.
 */
final class AlterIncidentsAddQrSource extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'incidents')) {
            return;
        }

        if (!$this->columnExists($pdo, 'incidents', 'zrodlo')) {
            $this->execute($pdo, "ALTER TABLE `incidents` ADD COLUMN `zrodlo` ENUM('panel','qr') NOT NULL DEFAULT 'panel' AFTER `zgloszona_przez`");
        }

        // Dopuszczenie NULL dla zgloszona_przez (anonimowe zgłoszenia z QR).
        if ($this->foreignKeyExists($pdo, 'incidents', 'fk_incidents_user')) {
            $this->execute($pdo, 'ALTER TABLE `incidents` DROP FOREIGN KEY `fk_incidents_user`');
        }

        $this->execute($pdo, 'ALTER TABLE `incidents` MODIFY `zgloszona_przez` INT UNSIGNED NULL');

        $this->execute($pdo, "
            ALTER TABLE `incidents`
            ADD CONSTRAINT `fk_incidents_user` FOREIGN KEY (`zgloszona_przez`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ");

        $this->execute($pdo, 'ALTER TABLE `incidents` ADD KEY `idx_incidents_zrodlo` (`zrodlo`)');
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'incidents')) {
            return;
        }

        $this->execute($pdo, 'ALTER TABLE `incidents` DROP KEY `idx_incidents_zrodlo`');

        if ($this->foreignKeyExists($pdo, 'incidents', 'fk_incidents_user')) {
            $this->execute($pdo, 'ALTER TABLE `incidents` DROP FOREIGN KEY `fk_incidents_user`');
        }

        // Anonimowe zgłoszenia nie mogą istnieć po przywróceniu NOT NULL — usuń je.
        $this->execute($pdo, 'DELETE FROM `incidents` WHERE `zgloszona_przez` IS NULL');

        $this->execute($pdo, 'ALTER TABLE `incidents` MODIFY `zgloszona_przez` INT UNSIGNED NOT NULL');

        $this->execute($pdo, "
            ALTER TABLE `incidents`
            ADD CONSTRAINT `fk_incidents_user` FOREIGN KEY (`zgloszona_przez`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ");

        if ($this->columnExists($pdo, 'incidents', 'zrodlo')) {
            $this->execute($pdo, 'ALTER TABLE `incidents` DROP COLUMN `zrodlo`');
        }
    }

    public function name(): string
    {
        return '20260820100100_alter_incidents_add_qr_source';
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

return new AlterIncidentsAddQrSource();
