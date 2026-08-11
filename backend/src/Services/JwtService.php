<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Serwis JWT — generacja i walidacja tokenów access oraz refresh.
 *
 * Claims: sub (user ID), role, permissions, iat, exp, jti, typ (access|refresh).
 *
 * Algorytm (dokumentacja sekcja 9.4):
 *  - HS256 — środowisko developerskie (klucz symetryczny `JWT_SECRET`),
 *  - RS256 — produkcja (asymetryczna para RSA 2048-bit; klucz prywatny w .env,
 *    publiczny dystrybuowany).
 */
final class JwtService
{
    private const array ALLOWED_ALGORITHMS = ['HS256', 'RS256'];

    private readonly string $secret;
    private readonly int $accessTtl;
    private readonly int $refreshTtl;

    public function __construct(
        string $jwtSecret,
        int $accessTtl = 900,
        int $refreshTtl = 604800,
        private readonly string $algorithm = 'HS256',
        private readonly ?string $privateKey = null,
        private readonly ?string $publicKey = null,
        private readonly string $issuer = 'pbs-backend',
    ) {
        if (!in_array($algorithm, self::ALLOWED_ALGORITHMS, true)) {
            throw new \InvalidArgumentException("Unsupported JWT algorithm: {$algorithm}");
        }

        if ($algorithm === 'RS256' && ($privateKey === null || $publicKey === null)) {
            throw new \InvalidArgumentException('RS256 requires both JWT_PRIVATE_KEY and JWT_PUBLIC_KEY');
        }

        $this->secret = $jwtSecret;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
    }

    /**
     * @param array<string, bool> $permissions
     * @return array{token: string, jti: string, expiresAt: int}
     */
    public function generateAccessToken(int $userId, string $role, array $permissions): array
    {
        $now = time();
        $jti = $this->generateJti();

        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'role' => $role,
            'permissions' => $permissions,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => $jti,
            'typ' => 'access',
        ];

        $token = JWT::encode($payload, $this->signingKey(), $this->algorithm);

        return [
            'token' => $token,
            'jti' => $jti,
            'expiresAt' => $now + $this->accessTtl,
        ];
    }

    /**
     * Generuje refresh token. Opcjonalny `$ttl` pozwala przedłużyć/skrócić czas życia
     * (np. dłuższa sesja dla „zapamiętaj mnie"). Domyślnie używa refreshTtl z konfiguracji.
     *
     * @return array{token: string, jti: string, expiresAt: int}
     */
    public function generateRefreshToken(int $userId, ?int $ttl = null): array
    {
        $now = time();
        $jti = $this->generateJti();
        $tokenTtl = $ttl ?? $this->refreshTtl;

        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $tokenTtl,
            'jti' => $jti,
            'typ' => 'refresh',
        ];

        $token = JWT::encode($payload, $this->signingKey(), $this->algorithm);

        return [
            'token' => $token,
            'jti' => $jti,
            'expiresAt' => $now + $tokenTtl,
        ];
    }

    /**
     * @return object{sub?: int|string, role?: string, permissions?: array<string,bool>, jti?: string, typ?: string, exp?: int, iss?: string}|null
     */
    public function validateToken(string $token, string $expectedType = 'access'): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->verificationKey(), $this->algorithm));
        } catch (\Throwable) {
            return null;
        }

        /** @var object{typ?: string} $decoded */
        if (!isset($decoded->typ) || $decoded->typ !== $expectedType) {
            return null;
        }

        /** @var object{iss?: string} $decoded */
        if (isset($decoded->iss) && $decoded->iss !== $this->issuer) {
            return null;
        }

        return $decoded;
    }

    public function accessTtl(): int
    {
        return $this->accessTtl;
    }

    public function refreshTtl(): int
    {
        return $this->refreshTtl;
    }

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    public function issuer(): string
    {
        return $this->issuer;
    }

    /**
     * Klucz używany do podpisywania tokenów.
     */
    private function signingKey(): string
    {
        if ($this->algorithm === 'RS256') {
            return $this->privateKey ?? '';
        }

        return $this->secret;
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

    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }
}