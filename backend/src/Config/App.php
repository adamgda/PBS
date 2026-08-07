<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\RateLimiterMiddleware;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Router\Router;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;

/**
 * Główna klasa aplikacji — bootstrap, konfiguracja middleware pipeline i routingu.
 */
final class App
{
    private readonly Config $config;
    private readonly Router $router;
    private readonly MiddlewarePipeline $pipeline;

    public function __construct(Config $config)
    {
        $this->config = $config;

        // === Połączenie z bazą (PDO) ===
        $pdo = ConnectionFactory::fromConfig($config);

        // === Repozytoria ===
        $userRepository = new UserRepository($pdo);
        $refreshTokenRepository = new RefreshTokenRepository($pdo);
        $passwordResetRepository = new PasswordResetRepository($pdo);
        $auditLogRepository = new AuditLogRepository($pdo);

        // === Serwisy ===
        $jwtSecret = $config->get('JWT_SECRET', 'dev-secret-key-change-in-production') ?? 'dev-secret-key-change-in-production';
        $accessTtl = (int) ($config->get('JWT_ACCESS_TTL', '900') ?? '900');
        $refreshTtl = (int) ($config->get('JWT_REFRESH_TTL', '604800') ?? '604800');

        $jwtService = new JwtService($jwtSecret, $accessTtl, $refreshTtl);
        $passwordPolicyService = new PasswordPolicyService();
        $mailService = new MailService($config);
        $frontendBaseUrl = $config->get('FRONTEND_BASE_URL', 'http://localhost:4200') ?? 'http://localhost:4200';
        $authService = new AuthService(
            $userRepository,
            $refreshTokenRepository,
            $passwordResetRepository,
            $auditLogRepository,
            $jwtService,
            $passwordPolicyService,
            $mailService,
            $frontendBaseUrl,
        );

        // === Kontrolery ===
        $healthController = new HealthController();
        $authController = new AuthController($authService);

        // === Trasy ===
        $routes = [
            ['method' => 'GET', 'path' => '/api/v1/health', 'handler' => [$healthController, 'index']],
            ['method' => 'POST', 'path' => '/api/v1/auth/login', 'handler' => [$authController, 'login']],
            ['method' => 'POST', 'path' => '/api/v1/auth/refresh', 'handler' => [$authController, 'refresh']],
            ['method' => 'POST', 'path' => '/api/v1/auth/logout', 'handler' => [$authController, 'logout']],
            ['method' => 'POST', 'path' => '/api/v1/auth/forgot-password', 'handler' => [$authController, 'forgotPassword']],
            ['method' => 'POST', 'path' => '/api/v1/auth/set-password', 'handler' => [$authController, 'setPassword']],
        ];

        $this->router = new Router($routes);

        // === Middleware ===
        $allowedOrigins = array_filter(
            array_map('trim', explode(',', $this->config->get('CORS_ALLOWED_ORIGINS', 'http://localhost:4200') ?? 'http://localhost:4200')),
            static fn (string $origin): bool => $origin !== '',
        );

        $middleware = [
            new CorsMiddleware($allowedOrigins),
            new RateLimiterMiddleware(
                maxRequests: 100,
                windowSeconds: 60,
            ),
            new AuthMiddleware(
                jwtSecret: $jwtSecret,
                publicRoutes: [
                    '/api/v1/health',
                    '/api/v1/auth/login',
                    '/api/v1/auth/refresh',
                    '/api/v1/auth/forgot-password',
                    '/api/v1/auth/set-password',
                ],
            ),
        ];

        $this->pipeline = new MiddlewarePipeline($middleware);
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline->handle($request, fn (Request $req): Response => $this->router->handle($req));
    }

    public function run(): void
    {
        $request = Request::fromGlobals();

        try {
            $response = $this->handle($request);
        } catch (\Throwable $e) {
            $appDebug = filter_var($this->config->get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
            $response = Response::error(
                500,
                'Internal Server Error',
                $appDebug ? ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] : null,
            );
        }

        $response->send();
    }
}