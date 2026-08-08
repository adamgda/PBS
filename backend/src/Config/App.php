<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\PermissionMiddleware;
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
use App\Services\UserService;

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
        $appDebug = filter_var($config->get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
        $authService = new AuthService(
            $userRepository,
            $refreshTokenRepository,
            $passwordResetRepository,
            $auditLogRepository,
            $jwtService,
            $passwordPolicyService,
            $mailService,
            $frontendBaseUrl,
            $appDebug,
        );

        // === Kontrolery ===
        $healthController = new HealthController();
        $authController = new AuthController($authService, $appDebug);
        $userService = new UserService(
            $userRepository,
            $passwordResetRepository,
            $auditLogRepository,
            $mailService,
            $frontendBaseUrl,
            $appDebug,
        );
        $userController = new UserController($userService);

        // === Guard uprawnień per-route ===
        // Opakowuje handler kontrolera sprawdzaniem PermissionMiddleware dla wskazanych sekcji.
        $ustawieniaGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['ustawienia']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // === Trasy ===
        $routes = [
            ['method' => 'GET', 'path' => '/api/v1/health', 'handler' => [$healthController, 'index']],
            ['method' => 'POST', 'path' => '/api/v1/auth/login', 'handler' => [$authController, 'login']],
            ['method' => 'POST', 'path' => '/api/v1/auth/refresh', 'handler' => [$authController, 'refresh']],
            ['method' => 'POST', 'path' => '/api/v1/auth/logout', 'handler' => [$authController, 'logout']],
            ['method' => 'POST', 'path' => '/api/v1/auth/forgot-password', 'handler' => [$authController, 'forgotPassword']],
            ['method' => 'POST', 'path' => '/api/v1/auth/set-password', 'handler' => [$authController, 'setPassword']],

            // Sekcja Ustawienia → Użytkownicy (Etap 5) — wymagane uprawnienie `ustawienia`
            ['method' => 'GET', 'path' => '/api/v1/users', 'handler' => $ustawieniaGuard([$userController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/users', 'handler' => $ustawieniaGuard([$userController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'update'])],
            ['method' => 'PATCH', 'path' => '/api/v1/users/{id}/permissions', 'handler' => $ustawieniaGuard([$userController, 'permissions'])],
            ['method' => 'DELETE', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'destroy'])],
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