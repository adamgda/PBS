<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\InvoiceService;

/**
 * Kontroler sekcji Faktury (Etap 7a) — endpointy:
 * GET    /api/v1/invoices
 * POST   /api/v1/invoices
 * GET    /api/v1/invoices/{id}
 * PUT    /api/v1/invoices/{id}
 * DELETE /api/v1/invoices/{id}
 * PATCH  /api/v1/invoices/{id}/status
 * GET    /api/v1/invoices/missing
 *
 * Wymaga uprawnienia sekcji `pracownicy` (PermissionMiddleware na trasie).
 */
final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * GET /api/v1/invoices — lista faktur z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'numer' => is_string($query['numer'] ?? null) ? (string) $query['numer'] : '',
            'klient' => is_string($query['klient'] ?? null) ? (string) $query['klient'] : '',
            'status' => is_string($query['status'] ?? null) ? (string) $query['status'] : '',
            'typ_wystawienia' => is_string($query['typ_wystawienia'] ?? null) ? (string) $query['typ_wystawienia'] : '',
            'date_from' => is_string($query['date_from'] ?? null) ? (string) $query['date_from'] : '',
            'date_to' => is_string($query['date_to'] ?? null) ? (string) $query['date_to'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        return $this->json($this->invoiceService->list($filters, $page, $perPage), 200);
    }

    /**
     * GET /api/v1/invoices/missing — zlecenia zakończone bez faktury.
     *
     * @param array<string, string> $params
     */
    public function missing(Request $request, array $params = []): Response
    {
        $query = $request->query();
        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        return $this->json($this->invoiceService->missing($page, $perPage), 200);
    }

    /**
     * GET /api/v1/invoices/{id} — szczegóły faktury.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->invoiceService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/invoices — utworzenie faktury.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->invoiceService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PUT /api/v1/invoices/{id} — edycja faktury.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->invoiceService->update($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/invoices/{id} — usunięcie faktury.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->invoiceService->delete($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PATCH /api/v1/invoices/{id}/status — zmiana statusu faktury.
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->invoiceService->updateStatus($id, $request->body(), $request);

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

    private function toInt(mixed $value, int $default = 0): int
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