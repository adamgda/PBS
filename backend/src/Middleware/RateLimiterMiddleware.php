<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\RateLimitStore;

/**
 * Middleware Rate Limiter (dokumentacja sekcja 9.3).
 *
 * Wymusza limity:
 *  - globalny per IP (domyślnie 100 req/min),
 *  - per użytkownik (domyślnie 1000 req/min) — aktywowany, gdy AuthMiddleware
 *    ustawi atrybut `user_id` (wymaga, aby Auth działał przed tym middleware).
 *
 * Implementacja in-memory — w produkcji (wielo-procesowej) zastąpić Redis (Etap 15a).
 */
final class RateLimiterMiddleware implements MiddlewareInterface
{
    private readonly RateLimitStore $store;

    public function __construct(
        private readonly int $maxIpRequests = 100,
        private readonly int $maxUserRequests = 1000,
        private readonly int $windowSeconds = 60,
        ?RateLimitStore $store = null,
    ) {
        $this->store = $store ?? new RateLimitStore();
    }

    public function process(Request $request, callable $next): Response
    {
        $now = time();
        $ip = $request->ip();

        $ipResult = $this->store->hit("ip:{$ip}", $this->maxIpRequests, $this->windowSeconds, $now);
        if (!$ipResult['allowed']) {
            return $this->limitedResponse($this->maxIpRequests, $ipResult);
        }

        // Limit per użytkownik (tylko dla uwierzytelnionych).
        $userId = $request->attribute('user_id');
        if (is_int($userId) && $userId > 0) {
            $userResult = $this->store->hit("user:{$userId}", $this->maxUserRequests, $this->windowSeconds, $now);
            if (!$userResult['allowed']) {
                return $this->limitedResponse($this->maxUserRequests, $userResult);
            }
        }

        $response = $next($request);

        $this->addRateLimitHeaders($response, $this->maxIpRequests, $ipResult);

        return $response;
    }

    /**
     * @param array{allowed: bool, remaining: int, retryAfter: int} $result
     */
    private function limitedResponse(int $limit, array $result): Response
    {
        $response = Response::error(429, 'Too many requests');
        $this->addRateLimitHeaders($response, $limit, $result);
        if ($result['retryAfter'] > 0) {
            $response->header('Retry-After', (string) $result['retryAfter']);
        }

        return $response;
    }

    /**
     * @param array{allowed: bool, remaining: int, retryAfter: int} $result
     */
    private function addRateLimitHeaders(Response $response, int $limit, array $result): void
    {
        $response->header('X-RateLimit-Limit', (string) $limit);
        $response->header('X-RateLimit-Remaining', (string) max(0, $result['remaining']));
    }
}