<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Middleware\RateLimiterMiddleware;
use App\Security\RateLimitStore;

describe('RateLimiterMiddleware', function (): void {
    beforeEach(function (): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    });

    it('allows requests within IP limit and adds headers', function (): void {
        $middleware = new RateLimiterMiddleware(maxIpRequests: 5, windowSeconds: 60, store: new RateLimitStore());

        $request = new Request([], [], []);
        $response = $middleware->process($request, static fn (): Response => Response::json(200));

        expect($response->statusCode())->toBe(200);
        expect($response->getHeader('X-RateLimit-Limit'))->toBe('5');
        expect((int) $response->getHeader('X-RateLimit-Remaining'))->toBeGreaterThanOrEqual(0);
    });

    it('returns 429 when IP limit exceeded', function (): void {
        $middleware = new RateLimiterMiddleware(maxIpRequests: 2, windowSeconds: 60, store: new RateLimitStore());

        $request = new Request([], [], []);
        $middleware->process($request, static fn (): Response => Response::json(200));
        $middleware->process($request, static fn (): Response => Response::json(200));

        $third = $middleware->process($request, static fn (): Response => Response::json(200));
        expect($third->statusCode())->toBe(429);
        expect($third->data()['error'])->toBe('Too many requests');
        expect($third->getHeader('Retry-After'))->not->toBeNull();
    });

    it('limits authenticated user independently of IP', function (): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $middleware = new RateLimiterMiddleware(maxIpRequests: 100, maxUserRequests: 2, windowSeconds: 60, store: new RateLimitStore());

        $request = new Request([], [], []);
        $request->setAttribute('user_id', 42);

        $middleware->process($request, static fn (): Response => Response::json(200));
        $middleware->process($request, static fn (): Response => Response::json(200));

        $third = $middleware->process($request, static fn (): Response => Response::json(200));
        expect($third->statusCode())->toBe(429);
    });

    it('does not apply user limit for anonymous requests', function (): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $middleware = new RateLimiterMiddleware(maxIpRequests: 100, maxUserRequests: 1, windowSeconds: 60, store: new RateLimitStore());

        $request = new Request([], [], []);

        $middleware->process($request, static fn (): Response => Response::json(200));
        $middleware->process($request, static fn (): Response => Response::json(200));

        $third = $middleware->process($request, static fn (): Response => Response::json(200));
        expect($third->statusCode())->toBe(200);
    });
});
