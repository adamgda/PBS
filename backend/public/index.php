<?php

declare(strict_types=1);

/**
 * Port Baltic Shipping (PBS) — Backend Entry Point
 *
 * Document root: backend/public/index.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// === Bezpieczeństwo: walidacja konfiguracji środowiska (CRED-02) ===
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
$apiBaseUrl = $_ENV['API_BASE_URL'] ?? getenv('API_BASE_URL') ?: 'http://localhost:8080';
$isProduction = !str_contains($apiBaseUrl, 'localhost');

// display_errors — tylko w dev; stack trace wyłącznie w logach (dokumentacja 14.5).
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');

if ($isProduction && $appDebug) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}

$jwtSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';
if ($isProduction && ($jwtSecret === '' || $jwtSecret === 'dev-secret-key-change-in-production' || strlen($jwtSecret) < 32)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}

// === Bezpieczeństwo: security headers (HEAD-01) ===
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

if ($isProduction) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

// === Bootstrap aplikacji ===
use App\Config\App;
use App\Config\Config;

$config = Config::fromEnvFile(__DIR__ . '/../.env');
$app = new App($config);
$app->run();