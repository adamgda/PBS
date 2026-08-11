<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\AuthController;
use App\Controllers\AlertSettingsController;
use App\Controllers\AnalyticsController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\EquipmentController;
use App\Controllers\HealthController;
use App\Controllers\IncidentController;
use App\Controllers\InvoiceController;
use App\Controllers\NoteController;
use App\Controllers\OrderController;
use App\Controllers\ReportController;
use App\Controllers\TerminalController;
use App\Controllers\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\MiddlewarePipeline;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimiterMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Repository\AlertConfigRepository;
use App\Repository\AlertNotificationRepository;
use App\Repository\AlertSourceRepository;
use App\Repository\AuditLogRepository;
use App\Repository\AnalyticsRepository;
use App\Repository\DashboardRepository;
use App\Repository\DailyTerminalReportRepository;
use App\Repository\DailyVehicleReportRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRateRepository;
use App\Repository\EmployeeRepository;
use App\Repository\EmployeeVacationRepository;
use App\Repository\EquipmentHistoryRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\ServicePlanRepository;
use App\Repository\TerminalRepository;
use App\Repository\UserRepository;
use App\Repository\UserNoteRepository;
use App\Repository\VehicleDetailsRepository;
use App\Router\Router;
use App\Security\RateLimitStore;
use App\Services\AlertSettingsService;
use App\Services\AuthService;
use App\Services\AnalyticsService;
use App\Services\ClamAvScanner;
use App\Services\CryptoService;
use App\Services\DashboardService;
use App\Services\EmployeeService;
use App\Services\EquipmentService;
use App\Services\FileUploadService;
use App\Services\IncidentService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\NoteService;
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
        $userNoteRepository = new UserNoteRepository($pdo);
        $refreshTokenRepository = new RefreshTokenRepository($pdo);
        $passwordResetRepository = new PasswordResetRepository($pdo);
        $auditLogRepository = new AuditLogRepository($pdo);
        $terminalRepository = new TerminalRepository($pdo);
        $employeeRepository = new EmployeeRepository($pdo);
        $employeeDocumentRepository = new EmployeeDocumentRepository($pdo);
        $employeeRateRepository = new EmployeeRateRepository($pdo);
        $employeeVacationRepository = new EmployeeVacationRepository($pdo);
        $equipmentRepository = new EquipmentRepository($pdo);
        $vehicleDetailsRepository = new VehicleDetailsRepository($pdo);
        $servicePlanRepository = new ServicePlanRepository($pdo);
        $equipmentHistoryRepository = new EquipmentHistoryRepository($pdo);
        $orderRepository = new OrderRepository($pdo);
        $incidentRepository = new IncidentRepository($pdo);
        $invoiceRepository = new InvoiceRepository($pdo);
        $dailyTerminalReportRepository = new DailyTerminalReportRepository($pdo);
        $dailyVehicleReportRepository = new DailyVehicleReportRepository($pdo);
        $analyticsRepository = new AnalyticsRepository($pdo);
        $dashboardRepository = new DashboardRepository($pdo);
        $alertConfigRepository = new AlertConfigRepository($pdo);
        $alertNotificationRepository = new AlertNotificationRepository($pdo);
        $alertSourceRepository = new AlertSourceRepository($pdo);

        // === Serwisy ===
        $isProduction = $config->isProduction();
        $appDebug = $config->getBool('APP_DEBUG', false);

        // === Bezpieczeństwo: walidacja konfiguracji produkcji (Etap 15) ===
        if ($isProduction && $appDebug) {
            throw new \RuntimeException('APP_DEBUG must be false in production');
        }

        $jwtSecret = $config->get('JWT_SECRET', 'dev-secret-key-change-in-production') ?? 'dev-secret-key-change-in-production';
        if ($isProduction && (strlen($jwtSecret) < 32 || $jwtSecret === 'dev-secret-key-change-in-production')) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 chars and not the dev default in production');
        }

        $accessTtl = (int) ($config->get('JWT_ACCESS_TTL', '900') ?? '900');
        $refreshTtl = (int) ($config->get('JWT_REFRESH_TTL', '604800') ?? '604800');

        // === JWT algorithm: RS256 na produkcji, HS256 w dev (dokumentacja 9.4) ===
        $jwtAlgorithm = $config->get('JWT_ALGORITHM', $isProduction ? 'RS256' : 'HS256') ?? ($isProduction ? 'RS256' : 'HS256');
        $jwtPrivateKey = $config->get('JWT_PRIVATE_KEY');
        $jwtPublicKey = $config->get('JWT_PUBLIC_KEY');

        $jwtService = new JwtService($jwtSecret, $accessTtl, $refreshTtl, $jwtAlgorithm, $jwtPrivateKey, $jwtPublicKey);

        // === Usługi bezpieczeństwa (Etap 15) ===
        $cryptoService = new CryptoService($config->get('APP_KEY', '') ?? '');
        $rateLimitStore = new RateLimitStore();

        // === CORS whitelist z .env (dokumentacja 9.3) ===
        $allowedOrigins = array_filter(
            array_map('trim', explode(',', $config->get('CORS_ALLOWED_ORIGINS', 'http://localhost:4200') ?? 'http://localhost:4200')),
            static fn (string $origin): bool => $origin !== '',
        );

        // Produkcja: wildcard '*' w CORS jest zabroniony.
        if ($isProduction && in_array('*', $allowedOrigins, true)) {
            throw new \RuntimeException('Wildcard CORS origin "*" is not allowed in production');
        }

        $csrfSigningKey = $config->get('APP_KEY', '') ?? '';
        $csrfEnforce = $config->getBool('CSRF_ENFORCE', false);
        $csrfMiddleware = new CsrfMiddleware($allowedOrigins, $csrfSigningKey !== '' ? $csrfSigningKey : $jwtSecret, $csrfEnforce);
        $securityHeadersMiddleware = new SecurityHeadersMiddleware($isProduction);

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
            $appDebug,
            $rateLimitStore,
        );

        // === Kontrolery ===
        $healthController = new HealthController();
        $authController = new AuthController($authService, $appDebug, $csrfMiddleware);
        $userService = new UserService(
            $userRepository,
            $passwordResetRepository,
            $auditLogRepository,
            $mailService,
            $frontendBaseUrl,
            $appDebug,
        );
        $userController = new UserController($userService);
        $terminalService = new TerminalService(
            $terminalRepository,
            $auditLogRepository,
            $orderRepository,
            $employeeRateRepository,
        );
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
            $employeeRateRepository,
            $employeeVacationRepository,
            $orderRepository,
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

        // === Serwis faktur (Etap 7a) ===
        $invoiceService = new InvoiceService($invoiceRepository, $auditLogRepository);
        $invoiceController = new InvoiceController($invoiceService);

        // === Serwis szybkich notatek to-do (Etap 19) ===
        $noteService = new NoteService($userNoteRepository);
        $noteController = new NoteController($noteService);

        // === Serwis raportowania (Etap 11) ===
        $reportService = new ReportService(
            $dailyTerminalReportRepository,
            $dailyVehicleReportRepository,
            $terminalRepository,
            $equipmentRepository,
            $orderRepository,
            $auditLogRepository,
        );
        $reportController = new ReportController($reportService);

        // === Serwis analityki (Etap 12) ===
        $analyticsService = new AnalyticsService($analyticsRepository);
        $analyticsController = new AnalyticsController($analyticsService);

        // === Serwis dashboardu (Etap 13) ===
        $dashboardService = new DashboardService($dashboardRepository);
        $dashboardController = new DashboardController($dashboardService);

        // === Serwis konfiguracji alertów (Etap 14) ===
        $alertSettingsService = new AlertSettingsService($alertConfigRepository, $auditLogRepository);
        $alertSettingsController = new AlertSettingsController($alertSettingsService);

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

        // Guard uprawnień sekcji „raportowanie" (Etap 11).
        $raportowanieGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['raportowanie']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „analityka" (Etap 12).
        $analitykaGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['analityka']);

            return static function (Request $request, array $routeParams) use ($permissionMiddleware, $handler): Response {
                return $permissionMiddleware->process(
                    $request,
                    static fn (Request $req): Response => $handler($req, $routeParams),
                );
            };
        };

        // Guard uprawnień sekcji „dashboard" (Etap 13).
        $dashboardGuard = static function (callable $handler): callable {
            $permissionMiddleware = new PermissionMiddleware(['dashboard']);

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
            ['method' => 'GET', 'path' => '/api/v1/auth/csrf', 'handler' => [$authController, 'csrf']],

            // Szybkie notatki to-do (Etap 19) — globalny widget, wymaga logowania (bez uprawnienia sekcji)
            ['method' => 'GET', 'path' => '/api/v1/notes', 'handler' => [$noteController, 'index']],
            ['method' => 'POST', 'path' => '/api/v1/notes', 'handler' => [$noteController, 'store']],
            ['method' => 'PATCH', 'path' => '/api/v1/notes/{id}', 'handler' => [$noteController, 'update']],
            ['method' => 'PATCH', 'path' => '/api/v1/notes/{id}/done', 'handler' => [$noteController, 'toggleDone']],
            ['method' => 'DELETE', 'path' => '/api/v1/notes/{id}', 'handler' => [$noteController, 'destroy']],
            ['method' => 'DELETE', 'path' => '/api/v1/notes', 'handler' => [$noteController, 'clear']],

            // Sekcja Ustawienia → Użytkownicy (Etap 5) — wymagane uprawnienie `ustawienia`
            ['method' => 'GET', 'path' => '/api/v1/users', 'handler' => $ustawieniaGuard([$userController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/users', 'handler' => $ustawieniaGuard([$userController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'update'])],
            ['method' => 'PATCH', 'path' => '/api/v1/users/{id}/permissions', 'handler' => $ustawieniaGuard([$userController, 'permissions'])],
            ['method' => 'DELETE', 'path' => '/api/v1/users/{id}', 'handler' => $ustawieniaGuard([$userController, 'destroy'])],

            // Sekcja Ustawienia → Alerty (Etap 14) — wymagane uprawnienie `ustawienia`
            ['method' => 'GET', 'path' => '/api/v1/settings/alert-configs', 'handler' => $ustawieniaGuard([$alertSettingsController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/settings/alert-configs', 'handler' => $ustawieniaGuard([$alertSettingsController, 'store'])],
            ['method' => 'PUT', 'path' => '/api/v1/settings/alert-configs/{id}', 'handler' => $ustawieniaGuard([$alertSettingsController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/settings/alert-configs/{id}', 'handler' => $ustawieniaGuard([$alertSettingsController, 'destroy'])],

            // Sekcja Terminale (Etap 6) — wymagane uprawnienie `terminale`
            ['method' => 'GET', 'path' => '/api/v1/terminals', 'handler' => $terminaleGuard([$terminalController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/terminals', 'handler' => $terminaleGuard([$terminalController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/terminals/hours-summary', 'handler' => $terminaleGuard([$terminalController, 'hoursSummary'])],
            ['method' => 'GET', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/terminals/{id}', 'handler' => $terminaleGuard([$terminalController, 'destroy'])],

            // Sekcja Pracownicy (Etap 7) — wymagane uprawnienie `pracownicy`
            ['method' => 'GET', 'path' => '/api/v1/employees', 'handler' => $pracownicyGuard([$employeeController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/employees', 'handler' => $pracownicyGuard([$employeeController, 'store'])],
            // Statyczne trasy GET /employees/* muszą poprzedzać trasę zmienną {id}
            // (wymóg nikic/fast-route — trasa zmienna „zasłania" statyczne).
            ['method' => 'GET', 'path' => '/api/v1/employees/settlement', 'handler' => $pracownicyGuard([$employeeController, 'settlement'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/settlement/by-port', 'handler' => $pracownicyGuard([$employeeController, 'settlementByPort'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/summary', 'handler' => $pracownicyGuard([$employeeController, 'summary'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/employees/{id}', 'handler' => $pracownicyGuard([$employeeController, 'destroy'])],
            ['method' => 'PATCH', 'path' => '/api/v1/employees/{id}/assignment', 'handler' => $pracownicyGuard([$employeeController, 'assign'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}/documents', 'handler' => $pracownicyGuard([$employeeController, 'listDocuments'])],
            ['method' => 'POST', 'path' => '/api/v1/employees/{id}/documents', 'handler' => $pracownicyGuard([$employeeController, 'createDocument'])],
            ['method' => 'PUT', 'path' => '/api/v1/documents/{id}', 'handler' => $pracownicyGuard([$employeeController, 'updateDocument'])],
            ['method' => 'DELETE', 'path' => '/api/v1/documents/{id}', 'handler' => $pracownicyGuard([$employeeController, 'deleteDocument'])],
            // Sekcja Pracownicy — rozszerzenie Etap 7a (stawki, urlopy, rozliczenia, faktury)
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}/rates', 'handler' => $pracownicyGuard([$employeeController, 'listRates'])],
            ['method' => 'POST', 'path' => '/api/v1/employees/{id}/rates', 'handler' => $pracownicyGuard([$employeeController, 'createRate'])],
            ['method' => 'GET', 'path' => '/api/v1/employees/{id}/vacations', 'handler' => $pracownicyGuard([$employeeController, 'listVacations'])],
            ['method' => 'POST', 'path' => '/api/v1/employees/{id}/vacations', 'handler' => $pracownicyGuard([$employeeController, 'createVacation'])],
            ['method' => 'PATCH', 'path' => '/api/v1/vacations/{id}/status', 'handler' => $pracownicyGuard([$employeeController, 'updateVacationStatus'])],
            ['method' => 'DELETE', 'path' => '/api/v1/vacations/{id}', 'handler' => $pracownicyGuard([$employeeController, 'deleteVacation'])],

            // Sekcja Faktury (Etap 7a) — wymagane uprawnienie `pracownicy`
            ['method' => 'GET', 'path' => '/api/v1/invoices', 'handler' => $pracownicyGuard([$invoiceController, 'index'])],
            ['method' => 'POST', 'path' => '/api/v1/invoices', 'handler' => $pracownicyGuard([$invoiceController, 'store'])],
            ['method' => 'GET', 'path' => '/api/v1/invoices/missing', 'handler' => $pracownicyGuard([$invoiceController, 'missing'])],
            ['method' => 'GET', 'path' => '/api/v1/invoices/{id}', 'handler' => $pracownicyGuard([$invoiceController, 'show'])],
            ['method' => 'PUT', 'path' => '/api/v1/invoices/{id}', 'handler' => $pracownicyGuard([$invoiceController, 'update'])],
            ['method' => 'DELETE', 'path' => '/api/v1/invoices/{id}', 'handler' => $pracownicyGuard([$invoiceController, 'destroy'])],
            ['method' => 'PATCH', 'path' => '/api/v1/invoices/{id}/status', 'handler' => $pracownicyGuard([$invoiceController, 'updateStatus'])],

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

            // Sekcja Raportowanie (Etap 11) — wymagane uprawnienie `raportowanie`
            ['method' => 'GET', 'path' => '/api/v1/reports/terminal', 'handler' => $raportowanieGuard([$reportController, 'terminalIndex'])],
            ['method' => 'POST', 'path' => '/api/v1/reports/terminal', 'handler' => $raportowanieGuard([$reportController, 'terminalStore'])],
            ['method' => 'GET', 'path' => '/api/v1/reports/terminal/{id}', 'handler' => $raportowanieGuard([$reportController, 'terminalShow'])],
            ['method' => 'PUT', 'path' => '/api/v1/reports/terminal/{id}', 'handler' => $raportowanieGuard([$reportController, 'terminalUpdate'])],
            ['method' => 'GET', 'path' => '/api/v1/reports/vehicle', 'handler' => $raportowanieGuard([$reportController, 'vehicleIndex'])],
            ['method' => 'POST', 'path' => '/api/v1/reports/vehicle', 'handler' => $raportowanieGuard([$reportController, 'vehicleStore'])],
            ['method' => 'GET', 'path' => '/api/v1/reports/vehicle/{id}', 'handler' => $raportowanieGuard([$reportController, 'vehicleShow'])],
            ['method' => 'PUT', 'path' => '/api/v1/reports/vehicle/{id}', 'handler' => $raportowanieGuard([$reportController, 'vehicleUpdate'])],

            // Sekcja Analityka (Etap 12) — wymagane uprawnienie `analityka`
            ['method' => 'GET', 'path' => '/api/v1/analytics/overview', 'handler' => $analitykaGuard([$analyticsController, 'overview'])],
            ['method' => 'GET', 'path' => '/api/v1/analytics/terminals', 'handler' => $analitykaGuard([$analyticsController, 'terminals'])],
            ['method' => 'GET', 'path' => '/api/v1/analytics/employees', 'handler' => $analitykaGuard([$analyticsController, 'employees'])],
            ['method' => 'GET', 'path' => '/api/v1/analytics/equipment', 'handler' => $analitykaGuard([$analyticsController, 'equipment'])],
            ['method' => 'GET', 'path' => '/api/v1/analytics/relations', 'handler' => $analitykaGuard([$analyticsController, 'relations'])],

            // Sekcja Dashboard (Etap 13) — wymagane uprawnienie `dashboard`
            ['method' => 'GET', 'path' => '/api/v1/dashboard/summary', 'handler' => $dashboardGuard([$dashboardController, 'summary'])],
            ['method' => 'GET', 'path' => '/api/v1/dashboard/alerts', 'handler' => $dashboardGuard([$dashboardController, 'alerts'])],
            ['method' => 'GET', 'path' => '/api/v1/dashboard/charts', 'handler' => $dashboardGuard([$dashboardController, 'charts'])],

        ];

        $this->router = new Router($routes);

        // === Middleware (kolejność: CORS → SecurityHeaders → Auth → Csrf → RateLimiter → Router) ===
        $middleware = [
            new CorsMiddleware($allowedOrigins),
            $securityHeadersMiddleware,
            new AuthMiddleware(
                jwtSecret: $jwtSecret,
                publicRoutes: [
                    '/api/v1/health',
                    '/api/v1/auth/login',
                    '/api/v1/auth/refresh',
                    '/api/v1/auth/forgot-password',
                    '/api/v1/auth/set-password',
                ],
                algorithm: $jwtAlgorithm,
                publicKey: $jwtPublicKey,
            ),
            $csrfMiddleware,
            new RateLimiterMiddleware(
                maxIpRequests: 100,
                maxUserRequests: 1000,
                windowSeconds: 60,
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
            // Loguj wyjątek do stderr (widoczne w konsoli serwera PHP built-in / start-backend.sh),
            // aby ułatwić diagnostykę błędów 500 — wcześniej wyjątek był połykany bez śladu.
            error_log('[PBS] Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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