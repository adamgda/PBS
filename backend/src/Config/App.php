<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\AuthController;
use App\Controllers\EmployeeController;
use App\Controllers\EquipmentController;
use App\Controllers\HealthController;
use App\Controllers\IncidentController;
use App\Controllers\OrderController;
use App\Controllers\TerminalController;
use App\Controllers\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimiterMiddleware;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\EquipmentHistoryRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;
use App\Repository\OrderRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\ServicePlanRepository;
use App\Repository\TerminalRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleDetailsRepository;
use App\Router\Router;
use App\Services\AuthService;
use App\Services\ClamAvScanner;
use App\Services\EmployeeService;
use App\Services\EquipmentService;
use App\Services\FileUploadService;
use App\Services\IncidentService;
use App\Services\OrderService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use App\Services\TerminalService;
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
        $terminalRepository = new TerminalRepository($pdo);
        $employeeRepository = new EmployeeRepository($pdo);
        $employeeDocumentRepository = new EmployeeDocumentRepository($pdo);
        $equipmentRepository = new EquipmentRepository($pdo);
        $vehicleDetailsRepository = new VehicleDetailsRepository($pdo);
        $servicePlanRepository = new ServicePlanRepository($pdo);
        $equipmentHistoryRepository = new EquipmentHistoryRepository($pdo);
        $orderRepository = new OrderRepository($pdo);
        $incidentRepository = new IncidentRepository($pdo);

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
        $terminalService = new TerminalService($terminalRepository, $auditLogRepository);
        $terminalController = new TerminalController($terminalService);

        // === Serwis uploadu plików + skaner antywirusowy (Etap 7) ===
        $storageDir = __DIR__ . '/../../storage/private/employee_documents';
        $fileUploadService = new FileUploadService(
            storageDir: $storageDir,
            baseUrl: $frontendBaseUrl,
            hmacSecret: $jwtSecret,
            scanner: new ClamAvScanner(),
        );
        $employeeService = new EmployeeService(
            $employeeRepository,
            $employeeDocumentRepository,
            $auditLogRepository,
            $fileUploadService,
        );
        $employeeController = new EmployeeController($employeeService);

        // === Serwis sprzętu (Etap 8) ===
        $equipmentService = new EquipmentService(
            $equipmentRepository,
            $vehicleDetailsRepository,
            $servicePlanRepository,
            $equipmentHistoryRepository,
            $auditLogRepository,
        );
        $equipmentController = new EquipmentController($equipmentService);

        // === Serwis zleceń (Etap 9) ===
        $orderService = new OrderService(
            $orderRepository,
            $terminalRepository,
            $employeeRepository,
            $equipmentRepository,
            $auditLogRepository,
        );
        $orderController = new OrderController($orderService);

        // === Serwis awarii (Etap 10) ===
        $incidentService = new IncidentService(
            $incidentRepository,
            $equipmentRepository,
            $auditLogRepository,
        );
        $incidentController = new IncidentController($incidentService);

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

        // Guard uprawnień sekcji „terminale" (Etap 6).
        $terminaleGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['terminale']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „pracownicy" (Etap 7).
        $pracownicyGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['pracownicy']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „sprzet" (Etap 8).
        $sprzetGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['sprzet']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „harmonogram" (Etap 9).
        $harmonogramGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['harmonogram']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „awaria" (Etap 10).
        $awariaGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['awaria']);

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

            // Sekcja Terminale (Etap 6) — wymagane uprawnienie `terminale`
            ['method' => 'GET', 'path' => '/api/v1/terminals', 'handler' => $terminaleGuard([$terminalController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/terminals', 'handler' => $terminaleGuard([$terminalController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'destroy'])],

            // Sekcja Pracownicy (Etap 7) — wymagane uprawnienie `pracownicy`
            ['method' => 'GET', 'path' => '/api/v1/employees', 'handler' => $pracownicyGuard([$employeeController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/employees', 'handler' => $pracownicyGuard([$employeeController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'destroy'])],
            ['method' => 'PATCH', 'path' => '/api/v1/employees/{id}/assignment', 'handler' => $pracownicyGuard([$employeeController, 'assign'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}/documents', 'handler' => $pracownicyGuard([$employeeController, 'listDocuments'])],
            ['method' => 'POST', 'path' => '/api/v1/employees/{id}/documents', 'handler' => $pracownicyGuard([$employeeController, 'createDocument'])],
            ['method' => 'PUT', 'path' => '/api/v1/documents/{id}', 'handler' => $pracownicyGuard([$employeeController, 'updateDocument'])],
            ['method' => 'DELETE', 'path' => '/api/v1/documents/{id}', 'handler' => $pracownicyGuard([$employeeController, 'deleteDocument'])],

            // Sekcja Sprzęt (Etap 8) — wymagane uprawnienie `sprzet`
            ['method' => 'GET', 'path' => '/api/v1/equipment', 'handler' => $sprzetGuard([$equipmentController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/equipment', 'handler' => $sprzetGuard([$equipmentController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/equipment/{id}', 'handler' => $sprzetGuard([$equipmentController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/equipment/{id}', 'handler' => $sprzetGuard([$equipmentController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/equipment/{id}', 'handler' => $sprzetGuard([$equipmentController, 'destroy'])],
            ['method' => 'PATCH', 'path' => '/api/v1/equipment/{id}/assignment', 'handler' => $sprzetGuard([$equipmentController, 'assign'])],
            ['method' => 'GET', 'path' => '/api/v1/equipment/{id}/timeline', 'handler' => $sprzetGuard([$equipmentController, 'timeline'])],
            ['method' => 'GET', 'path' => '/api/v1/equipment/{id}/service-plans', 'handler' => $sprzetGuard([$equipmentController, 'listServicePlans'])],
            ['method' => 'POST', 'path' => '/api/v1/equipment/{id}/service-plans', 'handler' => $sprzetGuard([$equipmentController, 'createServicePlan'])],
            ['method' => 'PUT', 'path' => '/api/v1/service-plans/{id}', 'handler' => $sprzetGuard([$equipmentController, 'updateServicePlan'])],
            ['method' => 'DELETE', 'path' => '/api/v1/service-plans/{id}', 'handler' => $sprzetGuard([$equipmentController, 'deleteServicePlan'])],

            // Sekcja Harmonogram / Zlecenia (Etap 9) — wymagane uprawnienie `harmonogram`
            ['method' => 'GET', 'path' => '/api/v1/orders', 'handler' => $harmonogramGuard([$orderController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/orders', 'handler' => $harmonogramGuard([$orderController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/orders/{id}', 'handler' => $harmonogramGuard([$orderController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/orders/{id}', 'handler' => $harmonogramGuard([$orderController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/orders/{id}', 'handler' => $harmonogramGuard([$orderController, 'destroy'])],
            ['method' => 'POST', 'path' => '/api/v1/orders/copy-week', 'handler' => $harmonogramGuard([$orderController, 'copyWeek'])],
            ['method' => 'POST', 'path' => '/api/v1/orders/{id}/assign-employee', 'handler' => $harmonogramGuard([$orderController, 'assignEmployee'])],
            ['method' => 'DELETE', 'path' => '/api/v1/orders/{id}/assign-employee/{employee_id}', 'handler' => $harmonogramGuard([$orderController, 'unassignEmployee'])],
            ['method' => 'POST', 'path' => '/api/v1/orders/{id}/assign-equipment', 'handler' => $harmonogramGuard([$orderController, 'assignEquipment'])],
            ['method' => 'DELETE', 'path' => '/api/v1/orders/{id}/assign-equipment/{equipment_id}', 'handler' => $harmonogramGuard([$orderController, 'unassignEquipment'])],

            // Sekcja Awaria (Etap 10) — wymagane uprawnienie `awaria`
            ['method' => 'GET', 'path' => '/api/v1/incidents', 'handler' => $awariaGuard([$incidentController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/incidents', 'handler' => $awariaGuard([$incidentController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/incidents/{id}', 'handler' => $awariaGuard([$incidentController, 'show'])],
            ['method' => 'PATCH', 'path' => '/api/v1/incidents/{id}/status', 'handler' => $awariaGuard([$incidentController, 'updateStatus'])],
            ['method' => 'POST', 'path' => '/api/v1/incidents/{id}/comments', 'handler' => $awariaGuard([$incidentController, 'addComment'])],
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