<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

/**
 * Runner seedów — wykonuje seedery w kolejności, śledzi wykonane w tabeli `seeds`.
 */
final class SeederRunner
{
    private readonly PDO $pdo;

    /** @var array<int, SeederInterface> */
    private readonly array $seeders;

    /**
     * @param array<int, SeederInterface> $seeders
     */
    public function __construct(PDO $pdo, array $seeders)
    {
        $this->pdo = $pdo;
        $this->seeders = $seeders;
    }

    public function seed(): void
    {
        $this->ensureSeedsTable();

        $applied = $this->getAppliedSeeds();

        foreach ($this->seeders as $seeder) {
            $name = $seeder->name();

            if (in_array($name, $applied, true)) {
                continue;
            }

            $seeder->run($this->pdo);

            $stmt = $this->pdo->prepare('INSERT INTO `seeds` (`name`, `applied_at`) VALUES (?, ?)');
            $stmt->execute([$name, date('Y-m-d H:i:s')]);
        }
    }

    public function refresh(): void
    {
        $this->ensureSeedsTable();
        $this->pdo->exec('DELETE FROM `seeds`');

        foreach ($this->seeders as $seeder) {
            $seeder->run($this->pdo);

            $stmt = $this->pdo->prepare('INSERT INTO `seeds` (`name`, `applied_at`) VALUES (?, ?)');
            $stmt->execute([$seeder->name(), date('Y-m-d H:i:s')]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function getAppliedSeeds(): array
    {
        $stmt = $this->pdo->query('SELECT `name` FROM `seeds` ORDER BY `id` ASC');

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{name: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $row): string => $row['name'], $rows);
    }

    private function ensureSeedsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `seeds` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
    }
}