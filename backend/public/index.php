<?php

declare(strict_types=1);

/**
 * Port Baltic Shipping (PBS) — Backend Entry Point
 *
 * Document root: backend/public/index.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Application bootstrap will be implemented in Etap 1
http_response_code(200);
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'service' => 'PBS Backend API',
    'version' => '1.0.0',
    'timestamp' => date('c'),
], JSON_PRETTY_PRINT);