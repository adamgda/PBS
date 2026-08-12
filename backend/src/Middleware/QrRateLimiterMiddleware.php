<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\RateLimitStore;

/**
 * Osobny Rate Limiter dla publicznych endpointów QR (Etap 20).
 *
 * Publiczne zgłoszenia z naklejki QR są podatne na spam — wymagają
 * ostrzejszego limitu niż globalny (domyślnie 10 req/min na IP).
 *
 * Aplikuje limit tylko do ścieżek zaczynających się od `/api/v1/qr/`.
 */
final class QrRateLimiterMiddleware implements MiddlewareInterface
{
    private readonly RateLimitStore $store;

    public function __construct(
        private readonly int $maxIpRequests = 10,
        private readonly int $windowSeconds = 60,
        ?RateLimitStore $store = null,
    ) {
        $this->store = $store ?? new RateLimitStore();
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->path();

        if (!str_starts_with($path, '/api/v1/qr/')) {
            return $next($request);
        }

        $now = time();
        $ip = $request->ip();

        $result = $this->store->hit("qr-ip:{$ip}", $this->maxIpRequests, $this->windowSeconds, $now);
        if (!$result['allowed']) {
            $response = Response::error(429, 'Too many requests');
            $response->header('Retry-After', (string) max(0, $result['retryAfter']));
            $this->addRateLimitHeaders($response, $result);

            return $response;
        }

        $response = $next($request);

        $this->addRateLimitHeaders($response, $result);

        return $response;
    }

    /**
     * @param array{allowed: bool, remaining: int, retryAfter: int} $result
     */
    private function addRateLimitHeaders(Response $response, array $result): void
    {
        $response->header('X-RateLimit-Limit', (string) $this->maxIpRequests);
        $response->header('X-RateLimit-Remaining', (string) max(0, $result['remaining']));
    }
}
