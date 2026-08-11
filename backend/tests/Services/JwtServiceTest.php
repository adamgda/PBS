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

    it('supports RS256 with an RSA key pair (production)', function (): void {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        expect($res)->not->toBe(false);

        /** @var \OpenSSLAsymmetricKey $res */
        $privateKey = '';
        expect(openssl_pkey_export($res, $privateKey))->toBeTrue();
        $details = openssl_pkey_get_details($res);
        expect($details)->not->toBe(false);
        $publicKey = (string) $details['key'];

        $rs256 = new JwtService('ignored-in-rs256', 900, 604800, 'RS256', $privateKey, $publicKey);

        $token = $rs256->generateAccessToken(7, 'admin', ['dashboard' => true]);
        $decoded = $rs256->validateToken($token['token'], 'access');

        expect($decoded)->not->toBeNull();
        expect((int) $decoded->sub)->toBe(7);
        expect($decoded->typ)->toBe('access');
    });

    it('rejects token signed with a different issuer', function (): void {
        $issuerA = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800, issuer: 'pbs-backend');
        $issuerB = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800, issuer: 'attacker');

        $token = $issuerA->generateAccessToken(1, 'user', []);
        $decoded = $issuerB->validateToken($token['token'], 'access');

        expect($decoded)->toBeNull();
    });

    it('throws for unsupported algorithm', function (): void {
        expect(fn () => new JwtService('secret', 900, 604800, 'none'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws when RS256 is selected without keys', function (): void {
        expect(fn () => new JwtService('secret', 900, 604800, 'RS256'))
            ->toThrow(InvalidArgumentException::class);
    });
});