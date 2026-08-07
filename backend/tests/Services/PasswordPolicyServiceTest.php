<?php

declare(strict_types=1);

use App\Services\PasswordPolicyService;

describe('PasswordPolicyService', function (): void {
    beforeEach(function (): void {
        $this->service = new PasswordPolicyService();
    });

    it('validates a strong password', function (): void {
        $result = $this->service->validate('Str0ngP@ssword!', 'user@pbs.local');
        expect($result)->toBeEmpty();
    });

    it('rejects password shorter than 12 chars', function (): void {
        $result = $this->service->validate('Short1!', 'user@pbs.local');
        expect($result)->not->toBeEmpty();
        expect($result)->toContain('Password must be at least 12 characters long');
    });

    it('rejects password with fewer than 3 character classes', function (): void {
        $result = $this->service->validate('onlylowercaseletters');
        expect($result)->not->toBeEmpty();
        expect($result)->toContain('Password must contain at least 3 of 4 character classes: lowercase, uppercase, digits, special');
    });

    it('rejects a common password', function (): void {
        $result = $this->service->validate('ZmienMnie123!');
        expect($result)->not->toBeEmpty();
        expect($result)->toContain('Password is too common');
    });

    it('rejects password containing email', function (): void {
        $result = $this->service->validate('MyUser@pbs.localPass1!', 'user@pbs.local');
        expect($result)->not->toBeEmpty();
        expect($result)->toContain('Password must not contain your email address');
    });

    it('rejects password longer than 128 chars', function (): void {
        $longPassword = str_repeat('a', 129) . 'A1!';
        $result = $this->service->validate($longPassword);
        expect($result)->toContain('Password must not exceed 128 characters');
    });

    it('hashes and verifies password', function (): void {
        $hash = $this->service->hash('Str0ngP@ssword!');
        expect($hash)->not->toBe('Str0ngP@ssword!');
        expect($this->service->verify('Str0ngP@ssword!', $hash))->toBeTrue();
        expect($this->service->verify('wrong', $hash))->toBeFalse();
    });

    it('accepts password with exactly 3 character classes', function (): void {
        $result = $this->service->validate('PasswordLetters123');
        expect($result)->toBeEmpty();
    });
});