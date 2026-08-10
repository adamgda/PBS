<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;

/**
 * Serwis autentykacji — logika biznesowa dla:
 * login, refresh, logout, set-password, forgot-password.
 *
 * Implementuje blokadę konta: 5 nieudanych prób → 15 min, 20 prób/24h → ręczne odblokowanie.
 */
final class AuthService
{
    private const int MAX_FAILED_ATTEMPTS = 5;
    private const int LOCKOUT_MINUTES = 15;
    private const int DAILY_FAILED_LIMIT = 20;
    private const int RESET_TOKEN_TTL_MINUTES = 60;
    private const int REMEMBER_REFRESH_TTL = 2592000; // 30 dni — sesja przy „zapamiętaj mnie"

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly PasswordResetRepository $passwordResetRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly JwtService $jwtService,
        private readonly PasswordPolicyService $passwordPolicy,
        private readonly MailService $mailService,
        private readonly string $frontendBaseUrl = 'http://localhost:4200',
        private readonly bool $debug = false,
    ) {}

    /**
     * Logowanie użytkownika.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}|array{error: string, code: int}
     */
    public function login(string $email, string $password, Request $request, bool $remember = false): array
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $this->auditLogRepository->logFromRequest(null, 'login_failed', $request, 'user', null, ['email' => $email, 'reason' => 'user_not_found']);

            return ['error' => 'Invalid credentials', 'code' => 401];
        }

        $userId = $this->toInt($user['id'] ?? 0);

        // Sprawdzenie czy konto jest aktywne
        $isActive = (bool) ($user['is_active'] ?? false);
        if (!$isActive) {
            $this->auditLogRepository->logFromRequest($userId, 'login_failed', $request, 'user', $userId, ['reason' => 'account_inactive']);

            return ['error' => 'Account is inactive', 'code' => 403];
        }

        // Sprawdzenie blokady konta
        $lockedUntilRaw = $user['locked_until'] ?? null;
        if ($lockedUntilRaw !== null) {
            $lockedUntilStr = is_string($lockedUntilRaw) ? $lockedUntilRaw : '';
            $lockTime = $lockedUntilStr !== '' ? strtotime($lockedUntilStr) : false;
            if ($lockTime !== false && $lockTime > time()) {
                $this->auditLogRepository->logFromRequest($userId, 'login_failed', $request, 'user', $userId, ['reason' => 'account_locked', 'locked_until' => $lockedUntilStr]);

                return ['error' => 'Account is locked. Try again later.', 'code' => 423];
            }
        }

        // Weryfikacja hasła
        $passwordHash = is_string($user['password_hash'] ?? null) ? $user['password_hash'] : '';
        if (!$this->passwordPolicy->verify($password, $passwordHash)) {
            $attempts = $this->toInt($user['failed_login_attempts'] ?? 0) + 1;
            $this->handleFailedLogin($userId, $attempts, $request);

            return ['error' => 'Invalid credentials', 'code' => 401];
        }

        // Sukces — reset licznika
        $this->userRepository->resetFailedLogin($userId);
        $this->auditLogRepository->logFromRequest($userId, 'login_success', $request, 'user', $userId);

        $role = is_string($user['role'] ?? null) ? $user['role'] : 'user';
        /** @var array<string, bool> $permissions */
        $permissions = $this->decodePermissions($user['permissions'] ?? null);

        $access = $this->jwtService->generateAccessToken($userId, $role, $permissions);
        $refresh = $remember
            ? $this->jwtService->generateRefreshToken($userId, self::REMEMBER_REFRESH_TTL)
            : $this->jwtService->generateRefreshToken($userId);

        return [
            'access_token' => $access['token'],
            'refresh_token' => $refresh['token'],
            'expires_in' => $access['expiresAt'] - time(),
            'user' => [
                'id' => $userId,
                'email' => is_string($user['email'] ?? null) ? $user['email'] : '',
                'role' => $role,
                'permissions' => $permissions,
                'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            ],
        ];
    }

    /**
     * Odświeżenie tokena — single-use rotation.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}|array{error: string, code: int}
     */
    public function refresh(string $refreshToken, Request $request): array
    {
        $decoded = $this->jwtService->validateToken($refreshToken, 'refresh');
        if ($decoded === null) {
            return ['error' => 'Invalid refresh token', 'code' => 401];
        }

        $jti = isset($decoded->jti) ? (string) $decoded->jti : '';
        $userId = isset($decoded->sub) ? (int) $decoded->sub : 0;

        if ($jti === '' || $userId === 0) {
            return ['error' => 'Invalid refresh token', 'code' => 401];
        }

        // Sprawdzenie denylist
        if ($this->refreshTokenRepository->isRevoked($jti)) {
            $this->auditLogRepository->logFromRequest($userId, 'refresh_failed', $request, 'user', $userId, ['reason' => 'token_revoked']);

            return ['error' => 'Refresh token has been revoked', 'code' => 401];
        }

        // Unieważnienie starego tokena (rotacja)
        $expiresAt = isset($decoded->exp) ? (int) $decoded->exp : time();
        $this->refreshTokenRepository->revoke($jti, $userId, date('Y-m-d H:i:s', $expiresAt));

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 401];
        }

        $role = is_string($user['role'] ?? null) ? $user['role'] : 'user';
        /** @var array<string, bool> $permissions */
        $permissions = $this->decodePermissions($user['permissions'] ?? null);

        $access = $this->jwtService->generateAccessToken($userId, $role, $permissions);
        $newRefresh = $this->jwtService->generateRefreshToken($userId);

        $this->auditLogRepository->logFromRequest($userId, 'refresh_success', $request, 'user', $userId);

        return [
            'access_token' => $access['token'],
            'refresh_token' => $newRefresh['token'],
            'expires_in' => $access['expiresAt'] - time(),
        ];
    }

    /**
     * Wylogowanie — dodanie refresh tokena do denylist.
     *
     * @return array{success: bool}
     */
    public function logout(string $refreshToken, ?int $userId, Request $request): array
    {
        $decoded = $this->jwtService->validateToken($refreshToken, 'refresh');
        if ($decoded !== null) {
            $jti = isset($decoded->jti) ? (string) $decoded->jti : '';
            $tokenUserId = isset($decoded->sub) ? (int) $decoded->sub : 0;
            $expiresAt = isset($decoded->exp) ? (int) $decoded->exp : time();

            if ($jti !== '') {
                $this->refreshTokenRepository->revoke($jti, $tokenUserId, date('Y-m-d H:i:s', $expiresAt));
            }

            $logUserId = $tokenUserId !== 0 ? $tokenUserId : $userId;
            $this->auditLogRepository->logFromRequest($logUserId, 'logout', $request, 'user', $logUserId);
        }

        return ['success' => true];
    }

    /**
     * Generuje token resetujący hasło (forgot-password).
     *
     * @return array{token: string, reset_url: ?string}
     */
    public function forgotPassword(string $email, Request $request): array
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            // Nie ujawniamy czy email istnieje — ale logujemy
            $this->auditLogRepository->logFromRequest(null, 'password_reset_requested', $request, 'user', null, ['email' => $email, 'found' => false]);

            // Zwracamy sukces aby nie ujawnić informacji
            return ['token' => '', 'reset_url' => null];
        }

        $userId = $this->toInt($user['id'] ?? 0);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_TTL_MINUTES * 60);

        $this->passwordResetRepository->createToken($userId, $tokenHash, $expiresAt);
        $this->auditLogRepository->logFromRequest($userId, 'password_reset_requested', $request, 'user', $userId);

        // Wysyłka e-mail z linkiem resetującym
        $userEmail = is_string($user['email'] ?? null) ? $user['email'] : '';
        if ($userEmail !== '') {
            $this->mailService->sendPasswordResetEmail($userEmail, $token, $this->frontendBaseUrl);
        }

        // W dev mode (APP_DEBUG) ujawniamy link resetujący w odpowiedzi dla testów.
        // W produkcji token nie wychodzi z API — wysyłany jest wyłącznie e-mailem.
        $resetUrl = $this->debug
            ? rtrim($this->frontendBaseUrl, '/') . '/set-password?token=' . $token
            : null;

        return ['token' => $token, 'reset_url' => $resetUrl];
    }

    /**
     * Ustawia nowe hasło z tokenem resetującym (set-password).
     *
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function setPassword(string $token, string $newPassword, Request $request): array
    {
        $tokenHash = hash('sha256', $token);
        $tokenRecord = $this->passwordResetRepository->findByHash($tokenHash);

        if ($tokenRecord === null) {
            return ['error' => 'Invalid or already used token', 'code' => 400];
        }

        $expiresAtStr = is_string($tokenRecord['expires_at'] ?? null) ? $tokenRecord['expires_at'] : '';
        $expiresAt = $expiresAtStr !== '' ? strtotime($expiresAtStr) : false;
        if ($expiresAt === false || $expiresAt < time()) {
            return ['error' => 'Token has expired', 'code' => 400];
        }

        $userId = $this->toInt($tokenRecord['user_id'] ?? 0);
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        $email = is_string($user['email'] ?? null) ? $user['email'] : '';
        $errors = $this->passwordPolicy->validate($newPassword, $email);
        if ($errors !== []) {
            return ['error' => implode(' ', $errors), 'code' => 422];
        }

        $hash = $this->passwordPolicy->hash($newPassword);
        $this->userRepository->updatePassword($userId, $hash);
        $tokenId = $this->toInt($tokenRecord['id'] ?? 0);
        $this->passwordResetRepository->markUsed($tokenId);
        $this->auditLogRepository->logFromRequest($userId, 'password_changed', $request, 'user', $userId);

        return ['success' => true];
    }

    /**
     * Obsługa nieudanego logowania — blokada konta.
     */
    private function handleFailedLogin(int $userId, int $attempts, Request $request): void
    {
        $user = $this->userRepository->findById($userId);
        $userEmail = is_string($user['email'] ?? null) ? $user['email'] : '';

        if ($attempts >= self::DAILY_FAILED_LIMIT) {
            // Blokada do ręcznego odblokowania — ustawiamy bardzo odległą datę
            $this->userRepository->updateFailedLogin($userId, $attempts, '2099-12-31 23:59:59');
            $this->auditLogRepository->logFromRequest($userId, 'account_locked_manual', $request, 'user', $userId, ['attempts' => $attempts]);
            // Powiadomienie e-mail o blokadzie — ręczne odblokowanie
            if ($userEmail !== '') {
                $this->mailService->sendAccountLockedNotification($userEmail, $attempts, true);
            }
        } elseif ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
            $this->userRepository->updateFailedLogin($userId, $attempts, $lockedUntil);
            $this->auditLogRepository->logFromRequest($userId, 'account_locked', $request, 'user', $userId, ['attempts' => $attempts, 'locked_until' => $lockedUntil]);
            // Powiadomienie e-mail o blokadzie — 15 min
            if ($userEmail !== '') {
                $this->mailService->sendAccountLockedNotification($userEmail, $attempts, false);
            }
        } else {
            $this->userRepository->updateFailedLogin($userId, $attempts);
        }
    }

    /**
     * @param mixed $permissions
     * @return array<string, bool>
     */
    private function decodePermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            if (is_array($decoded)) {
                /** @var array<string, bool> $result */
                $result = [];
                foreach ($decoded as $key => $value) {
                    if (is_bool($value)) {
                        $result[$key] = $value;
                    }
                }

                return $result;
            }
        }

        if (is_array($permissions)) {
            /** @var array<string, bool> $result */
            $result = [];
            foreach ($permissions as $key => $value) {
                if (is_bool($value)) {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        return [];
    }

    /**
     * Bezpieczna konwersja mixed → int (dla wartości z DB, które mogą być stringiem).
     */
    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}