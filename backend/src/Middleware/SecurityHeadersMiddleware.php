<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware dodający komplet nagłówków bezpieczeństwa (dokumentacja sekcja 9.3.1).
 *
 * Cache-Control: no-store stosowany dla całego API (JSON z danymi osobowymi).
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $production = false,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $this->applyHeaders($response);

        return $response;
    }

    private function applyHeaders(Response $response): void
    {
        // HSTS — tylko na produkcji (na HTTP w dev nagłówek byłby szkodliwy)
        if ($this->production) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $response->header('Cache-Control', 'no-store');
        $response->header('Pragma', 'no-cache');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
