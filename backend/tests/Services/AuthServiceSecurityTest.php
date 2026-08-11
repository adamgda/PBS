<?php

declare(strict_types=1);

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\RateLimitStore;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use PDO;
use Mockery as m;

/**
 * Testy rate-limitu bezpieczeństwa w AuthService (dokumentacja 9.3 / 9.7):
 * login 5/min per IP, 10/h per konto; set-password 3/h per token.
 */
describe('AuthService security rate limiting', function (): void {
    beforeEach(function (): void {
        $pdo = m::mock(PDO::class);

        $this->userRepository = m::mock(UserRepository::class, [$pdo]);
        $this->refreshTokenRepository = m::mock(RefreshTokenRepository::class, [$pdo]);
        $this->passwordResetRepository = m::mock(PasswordResetRepository::class, [$pdo]);
        $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
        $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

        $jwtService = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800);
        $passwordPolicy = new PasswordPolicyService();
        $mailService = m::mock(MailService::class);

        $this->rateLimitStore = new RateLimitStore();
        $this->authService = new AuthService(
            $this->userRepository,
            $this->refreshTokenRepository,
            $this->passwordResetRepository,
            $this->auditLogRepository,
            $jwtService,
            $passwordPolicy,
            $mailService,
            'http://localhost:4200',
            false,
            $this->rateLimitStore,
        );
    });

    afterEach(function (): void {
        m::close();
    });

    it('login is rate limited per IP (5/min)', function (): void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
        $request = new Request([], ['email' => 'a@b.pl', 'password' => 'x'], []);

        $this->userRepository->shouldReceive('findByEmail')->andReturnNull();

        // 5 prób → zwracają 401 (invalid credentials), nie 429
        for ($i = 0; $i < 5; $i++) {
            $result = $this->authService->login('a@b.pl', 'x', $request);
            expect($result['code'] ?? null)->not->toBe(429);
        }

        // 6. próba → limit per IP
        $sixth = $this->authService->login('a@b.pl', 'x', $request);
        expect($sixth['code'])->toBe(429);
        expect($sixth['error'])->toBe('Too many login attempts');
    });

    it('login is rate limited per account (10/h)', function (): void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
        $request = new Request([], ['email' => 'account@pbs.local', 'password' => 'x'], []);

        $this->userRepository->shouldReceive('findByEmail')->with('account@pbs.local')->andReturn([
            'id' => 7,
            'email' => 'account@pbs.local',
            'password_hash' => password_hash('DifferentPass123!', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => 'user',
            'permissions' => '[]',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
        // updateFailedLogin jest wywoływane przy nieudanych próbach
        $this->userRepository->shouldReceive('updateFailedLogin')->byDefault();
        $this->userRepository->shouldReceive('findById')->byDefault()->andReturn(['email' => 'account@pbs.local']);

        // Wykonujemy 10 nieudanych prób (hasło nie pasuje) — wyczerpują limit konta (10/h).
        // Rotujemy IP, aby nie wyzwolić wcześniej limitu per IP (5/min) i izolować limit konta.
        for ($i = 0; $i < 10; $i++) {
            $_SERVER['REMOTE_ADDR'] = '198.51.100.' . (21 + $i);
            $result = $this->authService->login('account@pbs.local', 'WrongPass123!', $request);
            expect($result['code'] ?? null)->not->toBe(429);
        }

        $eleventh = $this->authService->login('account@pbs.local', 'WrongPass123!', $request);
        expect($eleventh['code'])->toBe(429);
        expect($eleventh['error'])->toBe('Too many login attempts for this account');
    });

    it('set-password is rate limited per token (3/h)', function (): void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.30';
        $request = new Request([], [], []);

        $this->passwordResetRepository->shouldReceive('findByHash')->andReturnNull();

        for ($i = 0; $i < 3; $i++) {
            $result = $this->authService->setPassword('reset-token-123', 'StrongPass123!', $request);
            expect($result['code'] ?? null)->not->toBe(429);
        }

        $fourth = $this->authService->setPassword('reset-token-123', 'StrongPass123!', $request);
        expect($fourth['code'])->toBe(429);
        expect($fourth['error'])->toBe('Too many attempts. Try again later.');
    });
});
