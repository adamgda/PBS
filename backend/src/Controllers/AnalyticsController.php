<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AnalyticsService;

/**
 * Kontroler sekcji Analityka (Etap 12) — endpointy:
 * GET    /api/v1/analytics/overview
 * GET    /api/v1/analytics/terminals
 * GET    /api/v1/analytics/employees
 * GET    /api/v1/analytics/equipment
 * GET    /api/v1/analytics/relations
 *
 * Wymaga uprawnienia sekcji `analityka` (PermissionMiddleware na trasie).
 * Każdy endpoint przyjmuje opcjonalne `date_from` / `date_to` (Y-m-d).
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * GET /api/v1/analytics/overview — główne statystyki (KPI).
     *
     * @param array<string, string> $params
     */
    public function overview(Request $request, array $params = []): Response
    {
        return $this->serviceResponse($this->analyticsService->overview($this->filters($request)));
    }

    /**
     * GET /api/v1/analytics/terminals — statystyki terminali.
     *
     * @param array<string, string> $params
     */
    public function terminals(Request $request, array $params = []): Response
    {
        return $this->serviceResponse($this->analyticsService->terminals($this->filters($request)));
    }

    /**
     * GET /api/v1/analytics/employees — statystyki pracowników.
     *
     * @param array<string, string> $params
     */
    public function employees(Request $request, array $params = []): Response
    {
        return $this->serviceResponse($this->analyticsService->employees($this->filters($request)));
    }

    /**
     * GET /api/v1/analytics/equipment — statystyki sprzętu.
     *
     * @param array<string, string> $params
     */
    public function equipment(Request $request, array $params = []): Response
    {
        return $this->serviceResponse($this->analyticsService->equipment($this->filters($request)));
    }

    /**
     * GET /api/v1/analytics/relations — relacje między zasobami.
     *
     * @param array<string, string> $params
     */
    public function relations(Request $request, array $params = []): Response
    {
        return $this->serviceResponse($this->analyticsService->relations($this->filters($request)));
    }

    /**
     * @return array{date_from?: string, date_to?: string}
     */
    private function filters(Request $request): array
    {
        $query = $request->query();

        return [
            'date_from' => is_string($query['date_from'] ?? null) ? (string) $query['date_from'] : '',
            'date_to' => is_string($query['date_to'] ?? null) ? (string) $query['date_to'] : '',
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function serviceResponse(array $result): Response
    {
        if (array_key_exists('error', $result)) {
            $code = $result['code'] ?? 500;
            $message = $result['error'];

            if (is_int($code) && is_string($message)) {
                return $this->error($code, $message);
            }

            return $this->error(500, 'Unexpected error');
        }

        return $this->json($result, 200);
    }
}
