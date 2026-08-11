<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Middleware\SecurityHeadersMiddleware;

describe('SecurityHeadersMiddleware', function (): void {
    it('adds all security headers in dev', function (): void {
        $middleware = new SecurityHeadersMiddleware(false);
        $request = new Request(query: [], body: [], headers: []);

        $response = $middleware->process($request, static fn (): Response => Response::json(200));

        expect($response->getHeader('X-Content-Type-Options'))->toBe('nosniff');
        expect($response->getHeader('X-Frame-Options'))->toBe('DENY');
        expect($response->getHeader('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'");
        expect($response->getHeader('Referrer-Policy'))->toBe('no-referrer');
        expect($response->getHeader('Permissions-Policy'))->toBe('geolocation=(), camera=(), microphone=()');
        expect($response->getHeader('Cache-Control'))->toBe('no-store');
        expect($response->getHeader('Pragma'))->toBe('no-cache');
        expect($response->getHeader('X-Robots-Tag'))->toBe('noindex, nofollow');
    });

    it('adds HSTS only in production', function (): void {
        $dev = new SecurityHeadersMiddleware(false);
        $devResponse = $dev->process(new Request([], [], []), static fn (): Response => Response::json(200));
        expect($devResponse->getHeader('Strict-Transport-Security'))->toBeNull();

        $prod = new SecurityHeadersMiddleware(true);
        $prodResponse = $prod->process(new Request([], [], []), static fn (): Response => Response::json(200));
        expect($prodResponse->getHeader('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains; preload');
    });
});
