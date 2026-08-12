<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Middleware\QrRateLimiterMiddleware;
use App\Security\RateLimitStore;

function qrRequest(string $path, string $ip = '10.0.0.1'): Request
{
    $_SERVER['REMOTE_ADDR'] = $ip;
    $_SERVER['REQUEST_URI'] = $path;

    return new Request(query: [], body: [], headers: []);
}

it('does not limit non-QR routes', function (): void {
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['REQUEST_URI'] = '/api/v1/equipment';

    $middleware = new QrRateLimiterMiddleware(maxIpRequests: 1, windowSeconds: 60, store: new RateLimitStore());
    $response = $middleware->process(new Request(query: [], body: [], headers: []), fn (Request $r): Response => Response::json(200, ['ok' => true]));

    expect($response->statusCode())->toBe(200);
});

it('applies strict limit to QR routes', function (): void {
    $store = new RateLimitStore();
    $middleware = new QrRateLimiterMiddleware(maxIpRequests: 2, windowSeconds: 60, store: $store);

    $ok = $middleware->process(qrRequest('/api/v1/qr/tok', '1.2.3.4'), fn (Request $r): Response => Response::json(200, ['ok' => true]));
    $ok2 = $middleware->process(qrRequest('/api/v1/qr/tok', '1.2.3.4'), fn (Request $r): Response => Response::json(200, ['ok' => true]));
    $limited = $middleware->process(qrRequest('/api/v1/qr/tok', '1.2.3.4'), fn (Request $r): Response => Response::json(200, ['ok' => true]));

    expect($ok->statusCode())->toBe(200);
    expect($ok2->statusCode())->toBe(200);
    expect($limited->statusCode())->toBe(429);
    expect($limited->getHeader('Retry-After'))->not->toBeNull();
});

it('keeps separate buckets per IP', function (): void {
    $store = new RateLimitStore();
    $middleware = new QrRateLimiterMiddleware(maxIpRequests: 1, windowSeconds: 60, store: $store);

    $a1 = $middleware->process(qrRequest('/api/v1/qr/tok', '1.1.1.1'), fn (Request $r): Response => Response::json(200));
    $a2 = $middleware->process(qrRequest('/api/v1/qr/tok', '1.1.1.1'), fn (Request $r): Response => Response::json(200));
    $b1 = $middleware->process(qrRequest('/api/v1/qr/tok', '2.2.2.2'), fn (Request $r): Response => Response::json(200));

    expect($a1->statusCode())->toBe(200);
    expect($a2->statusCode())->toBe(429);
    expect($b1->statusCode())->toBe(200);
});
