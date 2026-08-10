<?php

declare(strict_types=1);

use App\Services\JwtService;

describe('JwtService', function (): void {
    beforeEach(function (): void {
        $this->secret = 'test-secret-key-for-testing-only-32chars!';
        $this->service = new JwtService($this->secret, 900, 604800);
    });

    it('generates a valid access token', function (): void {
        $permissions = ['dashboard' => true, 'ustawienia' => false];
        $result = $this->service->generateAccessToken(1, 'admin', $permissions);

        expect($result['token'])->toBeString();
        expect($result['jti'])->toBeString();
        expect(strlen($result['jti']))->toBe(32); // 16 bytes hex = 32 chars
        expect($result['expiresAt'])->toBeGreaterThan(time());

        $decoded = $this->service->validateToken($result['token'], 'access');
        expect($decoded)->not->toBeNull();
        expect((int) $decoded->sub)->toBe(1);
        expect($decoded->role)->toBe('admin');
        expect($decoded->typ)->toBe('access');
    });

    it('generates a valid refresh token', function (): void {
        $result = $this->service->generateRefreshToken(42);

        expect($result['token'])->toBeString();
        expect($result['jti'])->toBeString();
        expect($result['expiresAt'])->toBeGreaterThan(time());

        $decoded = $this->service->validateToken($result['token'], 'refresh');
        expect($decoded)->not->toBeNull();
        expect((int) $decoded->sub)->toBe(42);
        expect($decoded->typ)->toBe('refresh');
    });

    it('generates a refresh token with a custom TTL', function (): void {
        $customTtl = 30 * 86400; // 30 dni
        $result = $this->service->generateRefreshToken(42, $customTtl);

        expect($result['token'])->toBeString();
        // expiresAt powinno odpowiadać przekazanemu TTL (nie domyślnemu 604800)
        expect($result['expiresAt'] - time())->toBeGreaterThanOrEqual($customTtl - 5);
        expect($result['expiresAt'] - time())->toBeLessThanOrEqual($customTtl + 5);

        $decoded = $this->service->validateToken($result['token'], 'refresh');
        expect($decoded)->not->toBeNull();
        expect((int) $decoded->exp)->toBe($result['expiresAt']);
    });

    it('rejects access token when expecting refresh type', function (): void {
        $access = $this->service->generateAccessToken(1, 'user', []);
        $decoded = $this->service->validateToken($access['token'], 'refresh');
        expect($decoded)->toBeNull();
    });

    it('rejects refresh token when expecting access type', function (): void {
        $refresh = $this->service->generateRefreshToken(1);
        $decoded = $this->service->validateToken($refresh['token'], 'access');
        expect($decoded)->toBeNull();
    });

    it('rejects invalid token', function (): void {
        $decoded = $this->service->validateToken('invalid.token.here', 'access');
        expect($decoded)->toBeNull();
    });

    it('rejects token signed with different secret', function (): void {
        $otherService = new JwtService('different-secret-key-also-32chars!', 900, 604800);
        $result = $otherService->generateAccessToken(1, 'user', []);

        $decoded = $this->service->validateToken($result['token'], 'access');
        expect($decoded)->toBeNull();
    });
});