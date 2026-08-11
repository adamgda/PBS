<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Prosty, in-memory store limitu zapytań (okienkowy) używany przez RateLimiterMiddleware
 * oraz AuthService (logowanie, set-password).
 *
 * UWAGA: w środowisku wielo-procesowym / produkcyjnym należy zastąpić implementacją
 * opartą o Redis (patrz dokumentacja 9.3 oraz Etap 15a — CACHE_DRIVER).
 */
final class RateLimitStore
{
    /**
     * @var array<string, array{count: int, reset: int}>
     */
    private array $buckets = [];

    /**
     * Rejestruje trafienie dla klucza. Zwraca `true` jeśli dozwolone, `false` jeśli limit przekroczony.
     *
     * @return array{allowed: bool, remaining: int, retryAfter: int}
     */
    public function hit(string $key, int $max, int $windowSeconds, ?int $now = null): array
    {
        $now = $now ?? time();
        $bucket = $this->buckets[$key] ?? null;

        if ($bucket === null || $now > $bucket['reset']) {
            $this->buckets[$key] = ['count' => 1, 'reset' => $now + $windowSeconds];
            $remaining = max(0, $max - 1);

            return ['allowed' => true, 'remaining' => $remaining, 'retryAfter' => 0];
        }

        $bucket['count']++;
        $this->buckets[$key] = $bucket;

        if ($bucket['count'] > $max) {
            return ['allowed' => false, 'remaining' => 0, 'retryAfter' => $bucket['reset'] - $now];
        }

        return ['allowed' => true, 'remaining' => $max - $bucket['count'], 'retryAfter' => 0];
    }

    /**
     * Sprząta wygasłe wpisy (dobrowolne — zapobiega narastaniu pamięci).
     */
    public function purge(int $now = 0): int
    {
        $now = $now === 0 ? time() : $now;
        $removed = 0;

        foreach ($this->buckets as $key => $bucket) {
            if ($now > $bucket['reset']) {
                unset($this->buckets[$key]);
                $removed++;
            }
        }

        return $removed;
    }
}
