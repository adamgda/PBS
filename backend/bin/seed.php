<?php

declare(strict_types=1);

/**
 * PBS — Skrypt seedów danych
 * Użycie: php bin/seed.php         — wykonaj seedery
 *          php bin/seed.php refresh — wyczyść i wykonaj ponownie
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Config\ConnectionFactory;
use App\Seeders\SeederRunner;

// Załaduj seedery z katalogu seeds/
$seedsDir = __DIR__ . '/../seeds';
$seeders = [];

if (is_dir($seedsDir)) {
    $files = glob($seedsDir . '/*.php');
    if ($files !== false) {
        sort($files);
        foreach ($files as $file) {
            $seeder = require $file;
            if ($seeder instanceof App\Seeders\SeederInterface) {
                $seeders[] = $seeder;
            }
        }
    }
}

$config = Config::fromEnvFile(__DIR__ . '/../.env');
$pdo = ConnectionFactory::fromConfig($config);

$runner = new SeederRunner($pdo, $seeders);

$action = $argv[1] ?? 'seed';

match ($action) {
    'refresh' => $runner->refresh(),
    default => $runner->seed(),
};

$applied = $runner->getAppliedSeeds();
echo "Seedy wykonane: " . count($applied) . "\n";
foreach ($applied as $name) {
    echo "  ✓ {$name}\n";
}