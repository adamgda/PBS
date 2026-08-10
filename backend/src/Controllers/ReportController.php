<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ReportService;

/**
 * Kontroler sekcji Raportowanie (Etap 11) — endpointy:
 * GET    /api/v1/reports/terminal
 * POST   /api/v1/reports/terminal
 * GET    /api/v1/reports/terminal/{id}
 * PUT    /api/v1/reports/terminal/{id}
 * GET    /api/v1/reports/vehicle
 * POST   /api/v1/reports/vehicle
 * GET    /api/v1/reports/vehicle/{id}
 * PUT    /api/v1/reports/vehicle/{id}
 *
 * Wymaga uprawnienia sekcji `raportowanie` (PermissionMiddleware na trasie).
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    // --- Raporty terminalowe ---

    /**
     * GET /api/v1/reports/terminal — lista raportów terminalowych.
     *
     * @param array<string, string> $params
     */
    public function terminalIndex(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'terminal_id' => is_string($query['terminal_id'] ?? null) ? (string) $query['terminal_id'] : '',
            'date_from' => is_string($query['date_from'] ?? null) ? (string) $query['date_from'] : '',
            'date_to' => is_string($query['date_to'] ?? null) ? (string) $query['date_to'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        return $this->json($this->reportService->listTerminalReports($filters, $page, $perPage), 200);
    }

    /**
     * POST /api/v1/reports/terminal — utworzenie raportu terminalowego.
     *
     * @param array<string, string> $params
     */
    public function terminalStore(Request $request, array $params = []): Response
    {
        $result = $this->reportService->createTerminalReport($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/reports/terminal/{id} — szczegóły raportu terminalowego.
     *
     * @param array<string, string> $params
     */
    public function terminalShow(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->reportService->getTerminalReport($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/reports/terminal/{id} — edycja raportu terminalowego.
     *
     * @param array<string, string> $params
     */
    public function terminalUpdate(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->reportService->updateTerminalReport($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    // --- Raporty pojazdowe ---

    /**
     * GET /api/v1/reports/vehicle — lista raportów pojazdowych.
     *
     * @param array<string, string> $params
     */
    public function vehicleIndex(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'equipment_id' => is_string($query['equipment_id'] ?? null) ? (string) $query['equipment_id'] : '',
            'date_from' => is_string($query['date_from'] ?? null) ? (string) $query['date_from'] : '',
            'date_to' => is_string($query['date_to'] ?? null) ? (string) $query['date_to'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        return $this->json($this->reportService->listVehicleReports($filters, $page, $perPage), 200);
    }

    /**
     * POST /api/v1/reports/vehicle — utworzenie raportu pojazdowego.
     *
     * @param array<string, string> $params
     */
    public function vehicleStore(Request $request, array $params = []): Response
    {
        $result = $this->reportService->createVehicleReport($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/reports/vehicle/{id} — szczegóły raportu pojazdowego.
     *
     * @param array<string, string> $params
     */
    public function vehicleShow(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->reportService->getVehicleReport($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/reports/vehicle/{id} — edycja raportu pojazdowego.
     *
     * @param array<string, string> $params
     */
    public function vehicleUpdate(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->reportService->updateVehicleReport($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    // --- Pomocnicze ---

    /**
     * Bezpiecznie wyciąga błąd z wyniku serwisu (type-safe — PHPStan level 9).
     *
     * @param array<int|string, mixed> $result
     */
    private function errorResponse(array $result): ?Response
    {
        if (!array_key_exists('error', $result)) {
            return null;
        }

        $code = $result['code'] ?? 500;
        $message = $result['error'];

        if (is_int($code) && is_string($message)) {
            return $this->error($code, $message);
        }

        return $this->error(500, 'Unexpected error');
    }

    private function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
