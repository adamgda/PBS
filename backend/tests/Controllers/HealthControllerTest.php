<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Http\Request;

it('health controller returns ok status', function (): void {
    $controller = new HealthController();

    $request = new Request(query: [], body: [], headers: []);

    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data())->toHaveKey('status');
    expect($response->data()['status'])->toBe('ok');
    expect($response->data())->toHaveKey('service');
    expect($response->data()['service'])->toBe('PBS Backend API');
});

it('health controller returns timestamp', function (): void {
    $controller = new HealthController();
    $request = new Request(query: [], body: [], headers: []);

    $response = $controller->index($request);

    expect($response->data())->toHaveKey('timestamp');
});