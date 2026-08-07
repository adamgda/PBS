<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    // Mock PDO — repozytoria go nie używają bezpośrednio w testowanych metodach
    $pdo = m::mock(PDO::class);

    $this->userRepository = m::mock(UserRepository::class, [$pdo]);
    $this->refreshTokenRepository = m::mock(RefreshTokenRepository::class, [$pdo]);
    $this->passwordResetRepository = m::mock(PasswordResetRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);

    $jwtService = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800);
    $passwordPolicy = new PasswordPolicyService();
    $mailService = m::mock(MailService::class);
    $mailService->shouldReceive('sendAccountLockedNotification')->byDefault();
    $mailService->shouldReceive('sendPasswordResetEmail')->byDefault();

    $this->authService = new AuthService(
        $this->userRepository,
        $this->refreshTokenRepository,
        $this->passwordResetRepository,
        $this->auditLogRepository,
        $jwtService,
        $passwordPolicy,
        $mailService,
        'http://localhost:4200',
    );

    $this->authController = new AuthController($this->authService);

    // Domyślne mock — metody auditLog nie rzucają błędów
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();
});

afterEach(function (): void {
    m::close();
});

it('login returns 422 when email is missing', function (): void {
    $request = new Request(query: [], body: ['password' => 'pass'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(422);
});

it('login returns 422 when password is missing', function (): void {
    $request = new Request(query: [], body: ['email' => 'test@pbs.local'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(422);
});

it('login returns 401 for non-existent user', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('nobody@pbs.local')->andReturnNull();

    $request = new Request(query: [], body: ['email' => 'nobody@pbs.local', 'password' => 'wrong'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(401);
    expect($response->data()['error'])->toBe('Invalid credentials');
});

it('login returns 403 for inactive user', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('inactive@pbs.local')->andReturn([
        'id' => 5,
        'email' => 'inactive@pbs.local',
        'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'user',
        'permissions' => json_encode(['dashboard' => true]),
        'is_active' => false,
        'failed_login_attempts' => 0,
        'locked_until' => null,
    ]);

    $request = new Request(query: [], body: ['email' => 'inactive@pbs.local', 'password' => 'TestPassword123!'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(403);
    expect($response->data()['error'])->toBe('Account is inactive');
});

it('login returns 423 for locked account', function (): void {
    $future = date('Y-m-d H:i:s', time() + 600);
    $this->userRepository->shouldReceive('findByEmail')->with('locked@pbs.local')->andReturn([
        'id' => 6,
        'email' => 'locked@pbs.local',
        'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'user',
        'permissions' => json_encode(['dashboard' => true]),
        'is_active' => true,
        'failed_login_attempts' => 5,
        'locked_until' => $future,
    ]);

    $request = new Request(query: [], body: ['email' => 'locked@pbs.local', 'password' => 'TestPassword123!'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(423);
    expect($response->data()['error'])->toBe('Account is locked. Try again later.');
});

it('login succeeds with valid credentials', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('admin@pbs.local')->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
        'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'super_admin',
        'permissions' => json_encode(['dashboard' => true, 'ustawienia' => true]),
        'is_active' => true,
        'failed_login_attempts' => 0,
        'locked_until' => null,
        'must_change_password' => false,
    ]);
    $this->userRepository->shouldReceive('resetFailedLogin')->with(1)->andReturnNull();

    $request = new Request(query: [], body: ['email' => 'admin@pbs.local', 'password' => 'TestPassword123!'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(200);
    expect($response->data())->toHaveKey('access_token');
    expect($response->data())->toHaveKey('refresh_token');
    expect($response->data())->toHaveKey('expires_in');
    expect($response->data()['user']['email'])->toBe('admin@pbs.local');
});

it('login increments failed attempts on wrong password', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('admin@pbs.local')->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
        'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'user',
        'permissions' => json_encode(['dashboard' => true]),
        'is_active' => true,
        'failed_login_attempts' => 2,
        'locked_until' => null,
    ]);
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
    ]);
    $this->userRepository->shouldReceive('updateFailedLogin')->with(1, 3)->once();

    $request = new Request(query: [], body: ['email' => 'admin@pbs.local', 'password' => 'WrongPass123!'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(401);
});

it('login locks account after 5 failed attempts', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('admin@pbs.local')->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
        'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'user',
        'permissions' => json_encode(['dashboard' => true]),
        'is_active' => true,
        'failed_login_attempts' => 4,
        'locked_until' => null,
    ]);
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
    ]);
    // Po 5 nieudanych → blokada 15 min (locked_until !== null)
    $this->userRepository->shouldReceive('updateFailedLogin')->with(1, 5, m::type('string'))->once();

    $request = new Request(query: [], body: ['email' => 'admin@pbs.local', 'password' => 'WrongPass123!'], headers: []);
    $response = $this->authController->login($request);
    expect($response->statusCode())->toBe(401);
});

it('refresh returns 422 when token is missing', function (): void {
    $request = new Request(query: [], body: [], headers: []);
    $response = $this->authController->refresh($request);
    expect($response->statusCode())->toBe(422);
});

it('refresh returns 401 for invalid token', function (): void {
    $request = new Request(query: [], body: ['refresh_token' => 'invalid'], headers: []);
    $response = $this->authController->refresh($request);
    expect($response->statusCode())->toBe(401);
});

it('refresh succeeds with valid token', function (): void {
    $jwtService = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800);
    $refresh = $jwtService->generateRefreshToken(1);

    $this->refreshTokenRepository->shouldReceive('isRevoked')->andReturn(false);
    $this->refreshTokenRepository->shouldReceive('revoke')->once();
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
        'role' => 'super_admin',
        'permissions' => json_encode(['dashboard' => true]),
    ]);

    $request = new Request(query: [], body: ['refresh_token' => $refresh['token']], headers: []);
    $response = $this->authController->refresh($request);
    expect($response->statusCode())->toBe(200);
    expect($response->data())->toHaveKey('access_token');
    expect($response->data())->toHaveKey('refresh_token');
});

it('refresh rejects revoked token', function (): void {
    $jwtService = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800);
    $refresh = $jwtService->generateRefreshToken(1);

    $this->refreshTokenRepository->shouldReceive('isRevoked')->andReturn(true);

    $request = new Request(query: [], body: ['refresh_token' => $refresh['token']], headers: []);
    $response = $this->authController->refresh($request);
    expect($response->statusCode())->toBe(401);
    expect($response->data()['error'])->toBe('Refresh token has been revoked');
});

it('logout returns 422 when refresh token missing', function (): void {
    $request = new Request(query: [], body: [], headers: []);
    $response = $this->authController->logout($request);
    expect($response->statusCode())->toBe(422);
});

it('logout succeeds with valid refresh token', function (): void {
    $jwtService = new JwtService('test-secret-key-for-testing-only-32chars!', 900, 604800);
    $refresh = $jwtService->generateRefreshToken(1);

    $this->refreshTokenRepository->shouldReceive('revoke')->once();

    $request = new Request(query: [], body: ['refresh_token' => $refresh['token']], headers: []);
    $response = $this->authController->logout($request);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});

it('forgot-password returns 200 regardless of email existence', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('nobody@pbs.local')->andReturnNull();

    $request = new Request(query: [], body: ['email' => 'nobody@pbs.local'], headers: []);
    $response = $this->authController->forgotPassword($request);
    expect($response->statusCode())->toBe(200);
});

it('forgot-password returns 422 when email missing', function (): void {
    $request = new Request(query: [], body: [], headers: []);
    $response = $this->authController->forgotPassword($request);
    expect($response->statusCode())->toBe(422);
});

it('set-password returns 422 when token or password missing', function (): void {
    $request = new Request(query: [], body: ['token' => 'abc'], headers: []);
    $response = $this->authController->setPassword($request);
    expect($response->statusCode())->toBe(422);

    $request2 = new Request(query: [], body: ['password' => 'abc'], headers: []);
    $response2 = $this->authController->setPassword($request2);
    expect($response2->statusCode())->toBe(422);
});

it('set-password returns 400 for invalid token', function (): void {
    $this->passwordResetRepository->shouldReceive('findByHash')->andReturnNull();

    $request = new Request(query: [], body: ['token' => 'invalid', 'password' => 'Str0ngP@ssword!'], headers: []);
    $response = $this->authController->setPassword($request);
    expect($response->statusCode())->toBe(400);
});

it('set-password returns 422 for weak password', function (): void {
    $future = date('Y-m-d H:i:s', time() + 3600);
    $this->passwordResetRepository->shouldReceive('findByHash')->andReturn([
        'id' => 1,
        'user_id' => 1,
        'expires_at' => $future,
    ]);
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1,
        'email' => 'admin@pbs.local',
    ]);

    $request = new Request(query: [], body: ['token' => 'valid', 'password' => 'weak'], headers: []);
    $response = $this->authController->setPassword($request);
    expect($response->statusCode())->toBe(422);
});