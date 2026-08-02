<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use RuntimeException;

/**
 * Runner migracji — zarządza tabelą `migrations` i wykonuje migracje w kolejności.
 */
final class MigrationRunner
{
    private readonly PDO $pdo;

    /** @var array<int, MigrationInterface> */
    private readonly array $migrations;

    /**
     * @param array<int, MigrationInterface> $migrations
     */
    public function __construct(PDO $pdo, array $migrations)
    {
        $this->pdo = $pdo;
        $this->migrations = $migrations;
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();

        foreach ($this->migrations as $migration) {
            $name = $migration->name();

            if (in_array($name, $applied, true)) {
                continue;
            }

            $migration->up($this->pdo);

            $stmt = $this->pdo->prepare('INSERT INTO `migrations` (`name`, `applied_at`) VALUES (?, ?)');
            $stmt->execute([$name, date('Y-m-d H:i:s')]);
        }
    }

    public function rollback(): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();

        // Rollback w odwrotnej kolejności — tylko ostatnią migrację.
        if ($applied === []) {
            return;
        }

        /** @var string $lastApplied */
        $lastApplied = end($applied);

        foreach ($this->migrations as $migration) {
            if ($migration->name() === $lastApplied) {
                $migration->down($this->pdo);

                $stmt = $this->pdo->prepare('DELETE FROM `migrations` WHERE `name` = ?');
                $stmt->execute([$lastApplied]);

                return;
            }
        }
    }

    public function rollbackAll(): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $reversed = array_reverse($this->migrations);

        foreach ($reversed as $migration) {
            if (in_array($migration->name(), $applied, true)) {
                $migration->down($this->pdo);

                $stmt = $this->pdo->prepare('DELETE FROM `migrations` WHERE `name` = ?');
                $stmt->execute([$migration->name()]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT `name` FROM `migrations` ORDER BY `id` ASC');

        if ($stmt === false) {
            throw new RuntimeException('Failed to query migrations table');
        }

        /** @var array<int, array{name: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $row): string => $row['name'], $rows);
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
    }
}