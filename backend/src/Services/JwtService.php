<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Serwis JWT — generacja i walidacja tokenów access oraz refresh.
 *
 * Claims: sub (user ID), role, permissions, iat, exp, jti, typ (access|refresh).
 * Algorytm: HS256 (dev) — docelowo RS256 na produkcji (Etap 15).
 */
final class JwtService
{
    private readonly string $secret;
    private readonly int $accessTtl;
    private readonly int $refreshTtl;

    public function __construct(string $jwtSecret, int $accessTtl = 900, int $refreshTtl = 604800)
    {
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
            'iss' => 'pbs-backend',
            'sub' => $userId,
            'role' => $role,
            'permissions' => $permissions,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => $jti,
            'typ' => 'access',
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');

        return [
            'token' => $token,
            'jti' => $jti,
            'expiresAt' => $now + $this->accessTtl,
        ];
    }

    /**
     * @return array{token: string, jti: string, expiresAt: int}
     */
    public function generateRefreshToken(int $userId): array
    {
        $now = time();
        $jti = $this->generateJti();

        $payload = [
            'iss' => 'pbs-backend',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'jti' => $jti,
            'typ' => 'refresh',
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');

        return [
            'token' => $token,
            'jti' => $jti,
            'expiresAt' => $now + $this->refreshTtl,
        ];
    }

    /**
     * @return object{sub?: int|string, role?: string, permissions?: array<string,bool>, jti?: string, typ?: string, exp?: int}|null
     */
    public function validateToken(string $token, string $expectedType = 'access'): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        /** @var object{typ?: string} $decoded */
        if (!isset($decoded->typ) || $decoded->typ !== $expectedType) {
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

    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }
}