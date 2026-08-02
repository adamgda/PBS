<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;

/**
 * Abstrakcyjna migracja bazowa — dostarcza metody pomocnicze do wykonywania SQL.
 */
abstract class AbstractMigration implements MigrationInterface
{
    protected function execute(PDO $pdo, string $sql): void
    {
        $pdo->exec($sql);
    }

    protected function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $stmt->execute([$table]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}