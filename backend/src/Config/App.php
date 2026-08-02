<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\HealthController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\RateLimiterMiddleware;
use App\Router\Router;

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

        $healthController = new HealthController();

        $routes = [
            ['method' => 'GET', 'path' => '/api/v1/health', 'handler' => [$healthController, 'index']],
        ];

        $this->router = new Router($routes);

        $allowedOrigins = array_filter(
            array_map('trim', explode(',', $this->config->get('CORS_ALLOWED_ORIGINS', 'http://localhost:4200') ?? 'http://localhost:4200')),
            static fn (string $origin): bool => $origin !== '',
        );

        $jwtSecret = $this->config->get('JWT_SECRET', 'dev-secret-key-change-in-production') ?? 'dev-secret-key-change-in-production';

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