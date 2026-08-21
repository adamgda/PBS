<?php

declare(strict_types=1);

use App\Config\Config;
use App\Services\MailService;

/**
 * Testy MailService::sendErrorNotification (powiadomienia o błędach endpointów).
 * Używamy pustej konfiguracji → SMTP_HOST pusty → tryb dev (logowanie zamiast wysyłki).
 */

it('sendErrorNotification w trybie dev zwraca true i nie rzuca wyjątku', function (): void {
    $service = new MailService(new Config([]));

    $result = $service->sendErrorNotification(
        'super@pbs.local',
        500,
        'GET',
        '/api/v1/employees',
        'Coś się zepsuło',
        "Stack trace\nw linii 42",
        '127.0.0.1',
    );

    expect($result)->toBeTrue();
});

it('sendErrorNotification działa bez stack trace', function (): void {
    $service = new MailService(new Config([]));

    $result = $service->sendErrorNotification(
        'ops@pbs.local',
        503,
        'POST',
        '/api/v1/orders',
        'Usługa zewnętrzna niedostępna',
    );

    expect($result)->toBeTrue();
});

it('sendErrorNotification obsługuje status spoza zakresu 5xx', function (): void {
    $service = new MailService(new Config([]));

    $result = $service->sendErrorNotification('super@pbs.local', 500, 'DELETE', '/api/v1/users/1', 'x', null, '10.0.0.1');

    expect($result)->toBeTrue();
});
