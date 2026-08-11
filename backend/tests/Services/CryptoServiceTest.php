<?php

declare(strict_types=1);

use App\Services\CryptoService;

describe('CryptoService', function (): void {
    beforeEach(function (): void {
        // 32 bajty base64 (jak APP_KEY z .env)
        $this->service = new CryptoService('BTizkxlZYISdBv6BSJIwTFKfX7WXoIps4qsZp97jxIk=');
    });

    it('encrypts and decrypts data round-trip', function (): void {
        $plain = 'Sekretne dane pracownika: PESEL 12345678901';

        $encrypted = $this->service->encrypt($plain);
        expect($encrypted)->toBeString();
        expect($encrypted)->not->toBe($plain);

        $decrypted = $this->service->decrypt($encrypted);
        expect($decrypted)->toBe($plain);
    });

    it('produces unique ciphertext for identical input (random nonce)', function (): void {
        $a = $this->service->encrypt('same');
        $b = $this->service->encrypt('same');

        expect($a)->not->toBe($b);
    });

    it('returns null for tampered payload', function (): void {
        $encrypted = $this->service->encrypt('important');
        // Zmień ostatni bajt (część szyfrogramu/tagu)
        $tampered = substr($encrypted, 0, -1) . ($encrypted[-1] === 'A' ? 'B' : 'A');

        expect($this->service->decrypt($tampered))->toBeNull();
    });

    it('returns null for invalid base64', function (): void {
        expect($this->service->decrypt('not-base64!!!'))->toBeNull();
    });

    it('returns null for payload too short', function (): void {
        expect($this->service->decrypt(base64_encode('short')))->toBeNull();
    });

    it('does not decrypt with a different key', function (): void {
        $other = new CryptoService('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $encrypted = $this->service->encrypt('secret');

        expect($other->decrypt($encrypted))->toBeNull();
    });
});
