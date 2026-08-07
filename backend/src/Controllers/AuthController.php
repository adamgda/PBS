<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;

/**
 * Kontroler autentykacji — endpointy:
 * POST /api/v1/auth/login
 * POST /api/v1/auth/refresh
 * POST /api/v1/auth/logout
 * POST /api/v1/auth/forgot-password
 * POST /api/v1/auth/set-password
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly bool $debug = false,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';

        if ($email === '' || $password === '') {
            return $this->error(422, 'Email and password are required');
        }

        $result = $this->authService->login($email, $password, $request);

        if (isset($result['error'])) {
            return $this->error($result['code'], $result['error']);
        }

        return $this->json($result, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function refresh(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $refreshToken = is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : '';

        if ($refreshToken === '') {
            return $this->error(422, 'Refresh token is required');
        }

        $result = $this->authService->refresh($refreshToken, $request);

        if (isset($result['error'])) {
            return $this->error($result['code'], $result['error']);
        }

        return $this->json($result, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $refreshToken = is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : '';

        if ($refreshToken === '') {
            return $this->error(422, 'Refresh token is required');
        }

        $userId = $request->attribute('user_id');
        $result = $this->authService->logout($refreshToken, is_int($userId) ? $userId : null, $request);

        return $this->json($result, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function forgotPassword(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';

        if ($email === '') {
            return $this->error(422, 'Email is required');
        }

        $result = $this->authService->forgotPassword($email, $request);

        // Zawsze zwracamy 200 aby nie ujawnić czy email istnieje
        $payload = ['message' => 'If the email exists, a reset link has been sent.'];

        // W trybie debug (APP_DEBUG=true) ujawniamy token + link resetujący dla testów.
        // W produkcji te pola nigdy nie są zwracane — token wychodzi wyłącznie e-mailem.
        $resetUrl = is_string($result['reset_url']) ? $result['reset_url'] : '';
        if ($this->debug && $resetUrl !== '') {
            $payload['token'] = $result['token'];
            $payload['reset_url'] = $resetUrl;
        }

        return $this->json($payload, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function setPassword(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $token = is_string($body['token'] ?? null) ? $body['token'] : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';

        if ($token === '' || $password === '') {
            return $this->error(422, 'Token and password are required');
        }

        $result = $this->authService->setPassword($token, $password, $request);

        if (isset($result['error'])) {
            return $this->error($result['code'], $result['error']);
        }

        return $this->json($result, 200);
    }
}