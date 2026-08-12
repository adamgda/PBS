<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 20 — Kody QR dla maszyn.
 *
 * Rozszerza `equipment` o `qr_token` CHAR(64) UNIQUE NULLABLE — publiczny,
 * losowy token maszyny (NIE id), używany do zgłoszeń z naklejki QR.
 * Token generowany jako hex `random_bytes(32)` (64 znaki).
 */
final class AlterEquipmentAddQrToken extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'equipment')) {
            return;
        }

        if (!$this->columnExists($pdo, 'equipment', 'qr_token')) {
            $this->execute($pdo, "ALTER TABLE `equipment` ADD COLUMN `qr_token` CHAR(64) NULL AFTER `is_active`");
        }

        $this->execute($pdo, 'ALTER TABLE `equipment` ADD UNIQUE KEY `uq_equipment_qr_token` (`qr_token`)');
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'equipment')) {
            return;
        }

        $this->execute($pdo, 'ALTER TABLE `equipment` DROP KEY `uq_equipment_qr_token`');

        if ($this->columnExists($pdo, 'equipment', 'qr_token')) {
            $this->execute($pdo, 'ALTER TABLE `equipment` DROP COLUMN `qr_token`');
        }
    }

    public function name(): string
    {
        return '20260820100000_alter_equipment_add_qr_token';
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
}

return new AlterEquipmentAddQrToken();
