<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware Rate Limiter — limit zapytań per IP (in-memory).
 * W produkcji należy zastąpić implementacją opartą o Redis (Etap 15).
 */
final class RateLimiterMiddleware implements MiddlewareInterface
{
    /** @var array<string, array{count: int, reset: int}> */
    private array $buckets = [];

    /**
     * @param int $maxRequests Maksymalna liczba zapytań w oknie czasowym
     * @param int $windowSeconds Okno czasowe w sekundach
     */
    public function __construct(
        private readonly int $maxRequests = 100,
        private readonly int $windowSeconds = 60,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $ip = $this->getClientIp();
        $now = time();

        $bucket = $this->buckets[$ip] ?? null;

        if ($bucket === null || $now > $bucket['reset']) {
            $this->buckets[$ip] = ['count' => 1, 'reset' => $now + $this->windowSeconds];
            $response = $next($request);
            $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
            $response->header('X-RateLimit-Remaining', (string) ($this->maxRequests - 1));

            return $response;
        }

        $bucket['count']++;
        $this->buckets[$ip] = $bucket;

        if ($bucket['count'] > $this->maxRequests) {
            $response = Response::error(429, 'Too many requests');
            $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
            $response->header('X-RateLimit-Remaining', '0');
            $response->header('Retry-After', (string) ($bucket['reset'] - $now));

            return $response;
        }

        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->header('X-RateLimit-Remaining', (string) ($this->maxRequests - $bucket['count']));

        return $response;
    }

    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!is_string($ip)) {
            return '127.0.0.1';
        }

        return $ip;
    }
}