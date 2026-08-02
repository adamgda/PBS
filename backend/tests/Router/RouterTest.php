<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Router\Router;

it('dispatches a matched route successfully', function (): void {
    $handler = fn (Request $req, array $params = []): Response => Response::json(200, ['hello' => 'world']);

    $router = new Router([
        ['method' => 'GET', 'path' => '/api/v1/test', 'handler' => $handler],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/v1/test';

    $result = $router->dispatch($request);

    expect($result['status'])->toBe(200);
    expect($result['handler'])->not->toBeNull();
    expect($result['params'])->toBe([]);
});

it('returns 404 for unknown route', function (): void {
    $router = new Router([]);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/v1/unknown';

    $result = $router->dispatch($request);

    expect($result['status'])->toBe(404);
    expect($result['handler'])->toBeNull();
});

it('returns 405 for method not allowed', function (): void {
    $handler = fn (Request $req, array $params = []): Response => Response::json(200);

    $router = new Router([
        ['method' => 'GET', 'path' => '/api/v1/test', 'handler' => $handler],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/v1/test';

    $result = $router->dispatch($request);

    expect($result['status'])->toBe(405);
});

it('handle returns 404 Response for unknown route', function (): void {
    $router = new Router([]);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/v1/unknown';

    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);
});

it('handle invokes handler for matched route', function (): void {
    $handler = fn (Request $req, array $params = []): Response => Response::json(200, ['ok' => true]);

    $router = new Router([
        ['method' => 'GET', 'path' => '/api/v1/health', 'handler' => $handler],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/v1/health';

    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data())->toBe(['ok' => true]);
});