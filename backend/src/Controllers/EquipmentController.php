<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\EquipmentService;

/**
 * Kontroler sekcji Sprzęt (Etap 8) — endpointy:
 * GET    /api/v1/equipment
 * POST   /api/v1/equipment
 * GET    /api/v1/equipment/{id}
 * PUT    /api/v1/equipment/{id}
 * DELETE /api/v1/equipment/{id}
 * PATCH  /api/v1/equipment/{id}/assignment
 * GET    /api/v1/equipment/{id}/timeline
 * GET    /api/v1/equipment/{id}/service-plans
 * POST   /api/v1/equipment/{id}/service-plans
 * PUT    /api/v1/service-plans/{id}
 * DELETE /api/v1/service-plans/{id}
 *
 * Wymaga uprawnienia sekcji `sprzet` (PermissionMiddleware na trasie).
 */
final class EquipmentController extends Controller
{
    public function __construct(
        private readonly EquipmentService $equipmentService,
    ) {}

    /**
     * GET /api/v1/equipment — lista sprzętu z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'nazwa' => is_string($query['nazwa'] ?? null) ? (string) $query['nazwa'] : '',
            'kategoria' => is_string($query['kategoria'] ?? null) ? (string) $query['kategoria'] : '',
            'employee_id' => is_string($query['employee_id'] ?? null) ? (string) $query['employee_id'] : '',
            'terminal_id' => is_string($query['terminal_id'] ?? null) ? (string) $query['terminal_id'] : '',
            'is_active' => is_string($query['is_active'] ?? null) ? (string) $query['is_active'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        $result = $this->equipmentService->list($filters, $page, $perPage);

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/equipment — utworzenie sprzętu.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->equipmentService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/equipment/{id} — szczegóły sprzętu (z danymi pojazdu, planami, osią czasu).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/equipment/{id} — edycja sprzętu.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->update($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/equipment/{id} — usunięcie sprzętu.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->delete($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }
    /**
     * PATCH /api/v1/equipment/{id}/assignment — szybkie przypisanie.
     *
     * @param array<string, string> $params
     */
    public function assign(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->assign($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * GET /api/v1/equipment/{id}/timeline — oś czasu sprzętu.
     *
     * @param array<string, string> $params
     */
    public function timeline(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->timeline($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json(['data' => $result], 200);
    }

    /**
     * GET /api/v1/equipment/{id}/service-plans — lista planów przeglądów.
     *
     * @param array<string, string> $params
     */
    public function listServicePlans(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->listServicePlans($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json(['data' => $result], 200);
    }

    /**
     * POST /api/v1/equipment/{id}/service-plans — dodanie planu przeglądu.
     *
     * @param array<string, string> $params
     */
    public function createServicePlan(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->createServicePlan($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PUT /api/v1/service-plans/{id} — edycja planu przeglądu.
     *
     * @param array<string, string> $params
     */
    public function updateServicePlan(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->updateServicePlan($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/service-plans/{id} — usunięcie planu przeglądu.
     *
     * @param array<string, string> $params
     */
    public function deleteServicePlan(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->equipmentService->deleteServicePlan($id, $request);

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
     * Akceptuje wyniki serwisu o postaci list (klucze int) lub map z błędem (klucze string).
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