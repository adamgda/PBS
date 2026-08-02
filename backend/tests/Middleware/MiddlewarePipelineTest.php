<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;
use App\Middleware\MiddlewareInterface;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\RateLimiterMiddleware;

it('passes request through middleware chain to final handler', function (): void {
    $pipeline = new MiddlewarePipeline([]);

    $request = new Request(query: [], body: [], headers: []);

    $finalHandler = fn (Request $req): Response => Response::json(200, ['ok' => true]);

    $response = $pipeline->handle($request, $finalHandler);

    expect($response->statusCode())->toBe(200);
    expect($response->data())->toBe(['ok' => true]);
});

/**
 * Testowy middleware śledzący kolejność wywołań we współdzielonej tablicy.
 */
final class TestOrderMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    public array $sharedOrder;

    /**
     * @param array<int, string> $sharedOrder
     */
    public function __construct(
        array &$sharedOrder,
        private readonly string $label,
    ) {
        $this->sharedOrder = &$sharedOrder;
    }

    public function process(Request $request, callable $next): Response
    {
        $this->sharedOrder[] = "before{$this->label}";
        $response = $next($request);
        $this->sharedOrder[] = "after{$this->label}";

        return $response;
    }
}

it('executes middleware in order', function (): void {
    /** @var array<int, string> $order */
    $order = [];

    $middleware1 = new TestOrderMiddleware($order, '1');
    $middleware2 = new TestOrderMiddleware($order, '2');

    $pipeline = new MiddlewarePipeline([$middleware1, $middleware2]);

    $request = new Request(query: [], body: [], headers: []);
    $finalHandler = fn (Request $req): Response => Response::json(200);

    $pipeline->handle($request, $finalHandler);

    expect($order)->toBe(['before1', 'before2', 'after2', 'after1']);
});

it('middleware can short-circuit the chain', function (): void {
    $called = false;

    $blockingMiddleware = new class() implements MiddlewareInterface {
        public function process(Request $request, callable $next): Response
        {
            return Response::error(403, 'Forbidden');
        }
    };

    $pipeline = new MiddlewarePipeline([$blockingMiddleware]);

    $request = new Request(query: [], body: [], headers: []);
    $finalHandler = function (Request $req) use (&$called): Response {
        $called = true;

        return Response::json(200);
    };

    $response = $pipeline->handle($request, $finalHandler);

    expect($response->statusCode())->toBe(403);
    expect($called)->toBeFalse();
});

it('CORS middleware adds headers for allowed origin', function (): void {
    $middleware = new CorsMiddleware(['http://localhost:4200']);

    $request = new Request(query: [], body: [], headers: ['Origin' => 'http://localhost:4200']);

    $finalHandler = fn (Request $req): Response => Response::json(200);

    $response = $middleware->process($request, $finalHandler);

    expect($response->getHeader('Access-Control-Allow-Origin'))->toBe('http://localhost:4200');
    expect($response->getHeader('Access-Control-Allow-Credentials'))->toBe('true');
});

it('CORS middleware passes through for OPTIONS', function (): void {
    $middleware = new CorsMiddleware(['http://localhost:4200']);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

    $finalHandler = fn (Request $req): Response => Response::json(200);

    $response = $middleware->process($request, $finalHandler);

    expect($response->statusCode())->toBe(204);
});

it('RateLimiter allows requests within limit', function (): void {
    $middleware = new RateLimiterMiddleware(maxRequests: 5, windowSeconds: 60);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    $finalHandler = fn (Request $req): Response => Response::json(200);

    $response = $middleware->process($request, $finalHandler);

    expect($response->statusCode())->toBe(200);
    expect($response->getHeader('X-RateLimit-Limit'))->toBe('5');
});

it('RateLimiter blocks requests exceeding limit', function (): void {
    $middleware = new RateLimiterMiddleware(maxRequests: 2, windowSeconds: 60);

    $request = new Request(query: [], body: [], headers: []);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.2';

    $finalHandler = fn (Request $req): Response => Response::json(200);

    // Pierwsze 2 zapytania OK
    $middleware->process($request, $finalHandler);
    $middleware->process($request, $finalHandler);

    // Trzecie powinno być zablokowane
    $response = $middleware->process($request, $finalHandler);

    expect($response->statusCode())->toBe(429);
    expect($response->getHeader('Retry-After'))->not->toBeNull();
});