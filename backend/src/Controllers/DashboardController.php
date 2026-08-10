<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DashboardService;

/**
 * Kontroler sekcji Dashboard (Etap 13) — endpointy:
 * GET    /api/v1/dashboard/summary
 * GET    /api/v1/dashboard/alerts
 *
 * Wymaga uprawnienia sekcji `dashboard` (PermissionMiddleware na trasie).
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * GET /api/v1/dashboard/summary — podsumowanie KPI.
     *
     * @param array<string, string> $params
     */
    public function summary(Request $request, array $params = []): Response
    {
        return $this->json($this->dashboardService->summary(), 200);
    }

    /**
     * GET /api/v1/dashboard/alerts — lista alertów.
     *
     * @param array<string, string> $params
     */
    public function alerts(Request $request, array $params = []): Response
    {
        return $this->json($this->dashboardService->alerts(), 200);
    }
}
