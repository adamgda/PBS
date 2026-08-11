<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware CSRF (dokumentacja sekcja 9.3).
 *
 * Dla metod mutujących (POST/PUT/PATCH/DELETE):
 *  1) Weryfikuje nagłówek `Origin` (jeśli obecny) względem whitelist dozwolonych originów
 *     — zabezpiecza przed atakami CSRF, bo przeglądarka zawsze wysyła nagłówek Origin
 *     przy żądaniach cross-origin.
 *  2) Opcjonalnie (gdy `$enforce`) wymaga poprawnego, podpisanego nagłówka `X-CSRF-Token`.
 *
 * Token CSRF jest bezstanowy (HMAC) i wystawiany przez endpoint GET /api/v1/auth/csrf.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private const array MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private readonly string $key;

    /**
     * @param array<int, string> $allowedOrigins
     */
    public function __construct(
        private readonly array $allowedOrigins,
        string $signingKey,
        private readonly bool $enforce = false,
        private readonly int $tokenTtl = 1800,
    ) {
        $this->key = $signingKey;
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::MUTATING_METHODS, true)) {
            return $next($request);
        }

        // 1) Walidacja Origin dla metod mutujących.
        $origin = $request->header('Origin');
        if ($origin !== null && !in_array($origin, $this->allowedOrigins, true)) {
            return Response::error(403, 'Cross-origin request rejected');
        }

        // 2) Wymóg tokena CSRF (opcjonalny — aktywowany przez CSRF_ENFORCE).
        if ($this->enforce) {
            $token = $request->header('X-CSRF-Token');
            if ($token === null || !$this->verifyToken($token)) {
                return Response::error(403, 'Invalid or missing CSRF token');
            }
        }

        return $next($request);
    }

    /**
     * Wystawia podpisany token CSRF powiązany z (opcjonalnym) identyfikatorem użytkownika.
     */
    public function issueToken(string $userId = ''): string
    {
        $payload = ['sub' => $userId, 'exp' => time() + $this->tokenTtl, 'nonce' => bin2hex(random_bytes(8))];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return base64_encode($json) . '.' . $this->sign($json);
    }

    private function verifyToken(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $decoded = base64_decode($parts[0], true);
        if ($decoded === false) {
            return false;
        }

        // Stałoczasowe porównanie podpisu (hash_equals).
        $expected = $this->sign($decoded);
        if (!hash_equals($expected, $parts[1])) {
            return false;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp'])) {
            return false;
        }

        return $payload['exp'] > time();
    }

    private function sign(string $message): string
    {
        return hash_hmac('sha256', $message, $this->key);
    }
}
