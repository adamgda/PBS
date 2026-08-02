<?php

declare(strict_types=1);

/**
 * PBS — Skrypt migracji bazy danych
 * Użycie: php bin/migrate.php        — wykonaj migracje
 *          php bin/migrate.php rollback  — cofnij ostatnią migrację
 *          php bin/migrate.php rollback-all — cofnij wszystkie migracje
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Config\ConnectionFactory;
use App\Migrations\MigrationRunner;

// Załaduj migracje z katalogu migrations/
$migrationsDir = __DIR__ . '/../migrations';
$migrations = [];

if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '/*.php');
    if ($files !== false) {
        sort($files);
        foreach ($files as $file) {
            $migration = require $file;
            if ($migration instanceof App\Migrations\MigrationInterface) {
                $migrations[] = $migration;
            }
        }
    }
}

$config = Config::fromEnvFile(__DIR__ . '/../.env');
$pdo = ConnectionFactory::fromConfig($config);

$runner = new MigrationRunner($pdo, $migrations);

$action = $argv[1] ?? 'migrate';

match ($action) {
    'rollback' => $runner->rollback(),
    'rollback-all' => $runner->rollbackAll(),
    default => $runner->migrate(),
};

$applied = $runner->getAppliedMigrations();
echo "Migracje wykonane: " . count($applied) . "\n";
foreach ($applied as $name) {
    echo "  ✓ {$name}\n";
}