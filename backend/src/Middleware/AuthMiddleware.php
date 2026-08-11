<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

/**
 * Middleware Auth — weryfikuje token JWT (access) w nagłówku Authorization.
 * Poprawny token ładuje claims do atrybutów requesta: user_id, role, permissions.
 *
 * Algorytm (dokumentacja 9.4): HS256 w dev, RS256 na produkcji — jawnie wskazany,
 * z walidacją issuera.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private readonly string $secret;
    private readonly string $algorithm;
    private readonly string $issuer;

    /**
     * @param array<int, string> $publicRoutes ścieżki niewymagające autoryzacji
     */
    public function __construct(
        string $jwtSecret,
        private readonly array $publicRoutes = [],
        string $algorithm = 'HS256',
        private readonly ?string $publicKey = null,
        string $issuer = 'pbs-backend',
    ) {
        $this->secret = $jwtSecret;
        $this->algorithm = $algorithm;
        $this->issuer = $issuer;
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->path();
        foreach ($this->publicRoutes as $route) {
            if ($path === $route) {
                return $next($request);
            }
        }

        $authHeader = $request->header('Authorization');
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return Response::error(401, 'Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        if ($token === '') {
            return Response::error(401, 'Empty token');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->verificationKey(), $this->algorithm));
        } catch (ExpiredException) {
            return Response::error(401, 'Token expired');
        } catch (SignatureInvalidException) {
            return Response::error(401, 'Invalid token signature');
        } catch (UnexpectedValueException) {
            return Response::error(401, 'Invalid token');
        }

        // Weryfikacja, że to access token (nie refresh)
        /** @var object{typ?: string} $decoded */
        if (!isset($decoded->typ) || $decoded->typ !== 'access') {
            return Response::error(401, 'Invalid token type');
        }

        // Walidacja issuera
        /** @var object{iss?: string} $decoded */
        if (isset($decoded->iss) && $decoded->iss !== $this->issuer) {
            return Response::error(401, 'Invalid token issuer');
        }

        /** @var object{sub?: int|string, role?: string, permissions?: array<string,bool>} $decoded */
        $userId = $decoded->sub ?? null;
        $role = $decoded->role ?? null;
        $permissions = $decoded->permissions ?? [];

        if ($userId === null) {
            return Response::error(401, 'Token missing sub claim');
        }

        $request->setAttribute('user_id', (int) $userId);
        $request->setAttribute('role', $role);
        $request->setAttribute('permissions', $permissions);

        return $next($request);
    }

    /**
     * Klucz używany do weryfikacji podpisu.
     */
    private function verificationKey(): string
    {
        if ($this->algorithm === 'RS256') {
            return $this->publicKey ?? '';
        }

        return $this->secret;
    }
}