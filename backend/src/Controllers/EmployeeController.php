<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\EmployeeService;

/**
 * Kontroler sekcji Pracownicy (Etap 7) — endpointy:
 * GET    /api/v1/employees
 * POST   /api/v1/employees
 * GET    /api/v1/employees/{id}
 * PUT    /api/v1/employees/{id}
 * DELETE /api/v1/employees/{id}
 * PATCH  /api/v1/employees/{id}/assignment
 * GET    /api/v1/employees/{id}/documents
 * POST   /api/v1/employees/{id}/documents
 * PUT    /api/v1/documents/{id}
 * DELETE /api/v1/documents/{id}
 *
 * Wymaga uprawnienia sekcji `pracownicy` (PermissionMiddleware na trasie).
 */
final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    /**
     * GET /api/v1/employees — lista pracowników z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'q' => is_string($query['q'] ?? null) ? (string) $query['q'] : '',
            'imie' => is_string($query['imie'] ?? null) ? (string) $query['imie'] : '',
            'nazwisko' => is_string($query['nazwisko'] ?? null) ? (string) $query['nazwisko'] : '',
            'terminal_id' => is_string($query['terminal_id'] ?? null) ? (string) $query['terminal_id'] : '',
            'sprzet_id' => is_string($query['sprzet_id'] ?? null) ? (string) $query['sprzet_id'] : '',
            'is_active' => is_string($query['is_active'] ?? null) ? (string) $query['is_active'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        $result = $this->employeeService->list($filters, $page, $perPage);

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/employees — utworzenie pracownika.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->employeeService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/employees/{id} — szczegóły pracownika (z dokumentami).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/employees/{id} — edycja pracownika.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->update($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/employees/{id} — usunięcie pracownika (anonimizacja RODO).
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->delete($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PATCH /api/v1/employees/{id}/assignment — szybkie przypisanie terminala/sprzętu.
     *
     * @param array<string, string> $params
     */
    public function assign(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->assign($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    // --- Dokumenty pracownika ---

    /**
     * GET /api/v1/employees/{id}/documents — lista dokumentów pracownika.
     *
     * @param array<string, string> $params
     */
    public function listDocuments(Request $request, array $params = []): Response
    {
        $employeeId = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->listDocuments($employeeId);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/employees/{id}/documents — dodanie dokumentu (z uploadem pliku).
     *
     * Pola metadanych (nazwa, numer_dokumentu, data_wydania, data_waznosci)
     * pochodzą z JSON body lub multipart form-data; plik z $_FILES['plik'].
     *
     * @param array<string, string> $params
     */
    public function createDocument(Request $request, array $params = []): Response
    {
        $employeeId = $this->toInt($params['id'] ?? 0, 0);
        $data = $request->body();

        // Dla multipart/form-data PHP wypełnia $_POST, a body JSON jest puste —
        // uzupełnij dane z $_POST gdy body jest puste.
        if ($data === [] && !empty($_POST)) {
            /** @var array<string, mixed> $data */
            $data = $_POST;
        }

        $file = null;
        if (!empty($_FILES['plik']) && is_array($_FILES['plik'])) {
            /** @var array<string, mixed> $fileData */
            $fileData = $_FILES['plik'];
            if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $fileData;
            }
        }

        $result = $this->employeeService->createDocument($employeeId, $data, $file, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PUT /api/v1/documents/{id} — edycja dokumentu.
     *
     * @param array<string, string> $params
     */
    public function updateDocument(Request $request, array $params = []): Response
    {
        $documentId = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->updateDocument($documentId, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/documents/{id} — usunięcie dokumentu.
     *
     * @param array<string, string> $params
     */
    public function deleteDocument(Request $request, array $params = []): Response
    {
        $documentId = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->deleteDocument($documentId, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    // --- Stawki, urlopy, rozliczenia (Etap 7a) ---

    /**
     * GET /api/v1/employees/{id}/rates — historia stawek pracownika.
     *
     * @param array<string, string> $params
     */
    public function listRates(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->listRates($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/employees/{id}/rates — nowa stawka z datą wejścia w życie.
     *
     * @param array<string, string> $params
     */
    public function createRate(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->createRate($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/employees/{id}/vacations — lista urlopów pracownika.
     *
     * @param array<string, string> $params
     */
    public function listVacations(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->listVacations($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/employees/{id}/vacations — dodanie urlopu.
     *
     * @param array<string, string> $params
     */
    public function createVacation(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->createVacation($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PATCH /api/v1/vacations/{id}/status — zmiana statusu urlopu.
     *
     * @param array<string, string> $params
     */
    public function updateVacationStatus(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->updateVacationStatus($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/vacations/{id} — usunięcie urlopu.
     *
     * @param array<string, string> $params
     */
    public function deleteVacation(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->employeeService->deleteVacation($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * GET /api/v1/employees/settlement?month=&period= — rozliczenie per pracownik.
     *
     * @param array<string, string> $params
     */
    public function settlement(Request $request, array $params = []): Response
    {
        $query = $request->query();
        $month = is_string($query['month'] ?? null) ? (string) $query['month'] : '';
        $period = is_string($query['period'] ?? null) ? (string) $query['period'] : 'all';

        return $this->json($this->employeeService->settlement($month, $period), 200);
    }

    /**
     * GET /api/v1/employees/settlement/by-port?month=&period= — rozliczenie per port.
     *
     * @param array<string, string> $params
     */
    public function settlementByPort(Request $request, array $params = []): Response
    {
        $query = $request->query();
        $month = is_string($query['month'] ?? null) ? (string) $query['month'] : '';
        $period = is_string($query['period'] ?? null) ? (string) $query['period'] : 'all';

        return $this->json($this->employeeService->settlementByPort($month, $period), 200);
    }

    /**
     * GET /api/v1/employees/summary?month= — podsumowanie KPI.
     *
     * @param array<string, string> $params
     */
    public function summary(Request $request, array $params = []): Response
    {
        $query = $request->query();
        $month = is_string($query['month'] ?? null) ? (string) $query['month'] : '';

        return $this->json($this->employeeService->summary($month), 200);
    }
    // --- Pomocnicze ---

    /**
     * Bezpiecznie wyciąga błąd z wyniku serwisu (type-safe — PHPStan level 9).
     *
     * @param array<string, mixed> $result
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