<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\IncidentService;

/**
 * Kontroler sekcji Awaria (Etap 10) — endpointy:
 * GET    /api/v1/incidents
 * POST   /api/v1/incidents
 * GET    /api/v1/incidents/{id}
 * PATCH  /api/v1/incidents/{id}/status
 * POST   /api/v1/incidents/{id}/comments
 *
 * Wymaga uprawnienia sekcji `awaria` (PermissionMiddleware na trasie).
 */
final class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {}

    /**
     * GET /api/v1/incidents — lista awarii z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'typ' => is_string($query['typ'] ?? null) ? (string) $query['typ'] : '',
            'status' => is_string($query['status'] ?? null) ? (string) $query['status'] : '',
            'equipment_id' => is_string($query['equipment_id'] ?? null) ? (string) $query['equipment_id'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        $result = $this->incidentService->list($filters, $page, $perPage);

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/incidents — zgłoszenie awarii.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->incidentService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/incidents/{id} — szczegóły awarii (komentarze + historia statusów).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->incidentService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PATCH /api/v1/incidents/{id}/status — zmiana statusu awarii.
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $status = is_string($request->body()['status'] ?? null) ? (string) $request->body()['status'] : '';
        $result = $this->incidentService->changeStatus($id, $status, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/incidents/{id}/comments — dodanie komentarza.
     *
     * @param array<string, string> $params
     */
    public function addComment(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $tresc = is_string($request->body()['tresc'] ?? null) ? (string) $request->body()['tresc'] : '';
        $result = $this->incidentService->addComment($id, $tresc, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
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