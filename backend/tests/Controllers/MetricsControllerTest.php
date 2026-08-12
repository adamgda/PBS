<?php

declare(strict_types=1);

use App\Controllers\MetricsController;
use App\Http\Request;

it('webVitals returns 204 and accepts metric payload', function (): void {
    $controller = new MetricsController();
    $request = new Request([], ['name' => 'LCP', 'value' => 1200, 'rating' => 'good'], []);

    $response = $controller->webVitals($request);

    expect($response->statusCode())->toBe(204);
});

it('webVitals tolerates malformed payload', function (): void {
    $controller = new MetricsController();
    $request = new Request([], [], []);

    $response = $controller->webVitals($request);

    expect($response->statusCode())->toBe(204);
});
