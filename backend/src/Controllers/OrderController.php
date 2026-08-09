<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\OrderService;

/**
 * Kontroler sekcji Harmonogram / Zlecenia (Etap 9) — endpointy:
 * GET    /api/v1/orders
 * POST   /api/v1/orders
 * GET    /api/v1/orders/{id}
 * PUT    /api/v1/orders/{id}
 * DELETE /api/v1/orders/{id}
 * POST   /api/v1/orders/{id}/copy-week
 * POST   /api/v1/orders/{id}/assign-employee
 * DELETE /api/v1/orders/{id}/assign-employee
 * POST   /api/v1/orders/{id}/assign-equipment
 * DELETE /api/v1/orders/{id}/assign-equipment
 *
 * Wymaga uprawnienia sekcji `harmonogram` (PermissionMiddleware na trasie).
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * GET /api/v1/orders — lista zleceń z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'numer' => is_string($query['numer'] ?? null) ? (string) $query['numer'] : '',
            'klient' => is_string($query['klient'] ?? null) ? (string) $query['klient'] : '',
            'terminal_id' => is_string($query['terminal_id'] ?? null) ? (string) $query['terminal_id'] : '',
            'status' => is_string($query['status'] ?? null) ? (string) $query['status'] : '',
            'date_from' => is_string($query['date_from'] ?? null) ? (string) $query['date_from'] : '',
            'date_to' => is_string($query['date_to'] ?? null) ? (string) $query['date_to'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        $result = $this->orderService->list($filters, $page, $perPage);

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/orders — utworzenie zlecenia.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->orderService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/orders/{id} — szczegóły zlecenia (z przypisaniami).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->orderService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/orders/{id} — edycja zlecenia.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->orderService->update($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/orders/{id} — usunięcie zlecenia.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->orderService->delete($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/orders/{id}/copy-week — kopiowanie tygodnia jako szablon.
     *
     * @param array<string, string> $params
     */
    public function copyWeek(Request $request, array $params = []): Response
    {
        $result = $this->orderService->copyWeek($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/orders/{id}/assign-employee — przypisanie pracownika.
     *
     * @param array<string, string> $params
     */
    public function assignEmployee(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $employeeId = $this->toInt($request->body()['employee_id'] ?? 0, 0);
        $result = $this->orderService->assignEmployee($id, $employeeId, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * DELETE /api/v1/orders/{id}/assign-employee — odpisanie pracownika.
     *
     * @param array<string, string> $params
     */
    public function unassignEmployee(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $employeeId = $this->toInt($params['employee_id'] ?? 0, 0);
        $result = $this->orderService->unassignEmployee($id, $employeeId, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/orders/{id}/assign-equipment — przypisanie sprzętu.
     *
     * @param array<string, string> $params
     */
    public function assignEquipment(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $equipmentId = $this->toInt($request->body()['equipment_id'] ?? 0, 0);
        $result = $this->orderService->assignEquipment($id, $equipmentId, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * DELETE /api/v1/orders/{id}/assign-equipment — odpisanie sprzętu.
     *
     * @param array<string, string> $params
     */
    public function unassignEquipment(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $equipmentId = $this->toInt($params['equipment_id'] ?? 0, 0);
        $result = $this->orderService->unassignEquipment($id, $equipmentId, $request);

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