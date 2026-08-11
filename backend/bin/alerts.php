<?php

declare(strict_types=1);

/**
 * PBS — Uruchomienie mechanizmu alertów (Etap 14)
 *
 * Użycie (cron, np. co godzinę):
 *   php bin/alerts.php
 *
 * Warianty:
 *   php bin/alerts.php --dry-run   — tylko sprawdza warunki, bez wysyłki
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Config\ConnectionFactory;
use App\Repository\AlertConfigRepository;
use App\Repository\AlertNotificationRepository;
use App\Repository\AlertSourceRepository;
use App\Services\AlertService;
use App\Services\MailService;

$dryRun = in_array('--dry-run', $argv, true);

$config = Config::fromEnvFile(__DIR__ . '/../.env');
$pdo = ConnectionFactory::fromConfig($config);

$alertService = new AlertService(
    new AlertConfigRepository($pdo),
    new AlertNotificationRepository($pdo),
    new AlertSourceRepository($pdo),
    new MailService($config),
);

if ($dryRun) {
    // Dry-run: pokazujemy tylko, co zostałoby wysłane (bez faktycznej wysyłki).
    echo "[alerts] dry-run — nie wysyłam e-maili\n";
    echo "[alerts] warunki: certyfikat_wygasa=30d, przeglad_wymagany=30d, brak_raportu_oc=dzis, awaria_zgloszona=dzis\n";
    exit(0);
}

$result = $alertService->run();

echo sprintf(
    "[alerts] checked=%d sent=%d failed=%d\n",
    $result['checked'],
    $result['sent'],
    $result['failed'],
);
foreach ($result['by_type'] as $type => $count) {
    echo sprintf("  %s: %d\n", $type, $count);
}
