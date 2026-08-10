<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\InvoiceRepository;

/**
 * Serwis zarządzania fakturami — sekcja Pracownicy / Faktury (Etap 7a).
 *
 * Operacje: lista (paginacja + filtry: numer, klient, status, termin wystawienia,
 * data), tworzenie, edycja, usuwanie, zmiana statusu, detekcja zleceń zakończonych
 * bez wystawionej faktury (invoices/missing).
 */
final class InvoiceService
{
    private const int MAX_NUMER_LENGTH = 50;
    private const int MAX_KLIENT_LENGTH = 255;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista faktur z paginacją i filtrami.
     *
     * @param array{numer?: string, klient?: string, status?: string, typ_wystawienia?: string, date_from?: string, date_to?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->invoiceRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->invoiceRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $invoice = $this->invoiceRepository->findById($id);
        if ($invoice === null) {
            return ['error' => 'Invoice not found', 'code' => 404];
        }

        return $this->toDto($invoice);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(array $data, Request $request): array
    {
        $err = $this->validate($data);
        if ($err !== null) {
            return $err;
        }

        $numer = trim($this->toStr($data['numer_faktury']));
        if ($this->invoiceRepository->findByNumber($numer) !== null) {
            return ['error' => 'Invoice number already exists', 'code' => 409];
        }

        $created = $this->invoiceRepository->createInvoice($this->prepareData($data));
        $this->auditLog($request, 'invoice_created', $this->toInt($created['id'] ?? 0));

        return $this->toDto($created);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $existing = $this->invoiceRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Invoice not found', 'code' => 404];
        }

        $err = $this->validate($data, $id);
        if ($err !== null) {
            return $err;
        }

        $numer = trim($this->toStr($data['numer_faktury']));
        $byNumber = $this->invoiceRepository->findByNumber($numer);
        if ($byNumber !== null && $this->toInt($byNumber['id'] ?? 0) !== $id) {
            return ['error' => 'Invoice number already exists', 'code' => 409];
        }

        $updated = $this->invoiceRepository->updateInvoice($id, $this->prepareData($data));
        $this->auditLog($request, 'invoice_updated', $id);

        return $updated !== null ? $this->toDto($updated) : ['success' => true];
    }

    /**
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        if ($this->invoiceRepository->findById($id) === null) {
            return ['error' => 'Invoice not found', 'code' => 404];
        }

        $this->invoiceRepository->delete($id);
        $this->auditLog($request, 'invoice_deleted', $id);

        return ['success' => true];
    }

    /**
     * PATCH /api/v1/invoices/{id}/status — zmiana statusu faktury.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateStatus(int $id, array $data, Request $request): array
    {
        $existing = $this->invoiceRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Invoice not found', 'code' => 404];
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : '';
        if (!in_array($status, ['wystawiona', 'zaplacona', 'przeterminowana'], true)) {
            return ['error' => 'Invalid invoice status', 'code' => 422];
        }

        $updated = $this->invoiceRepository->updateInvoice($id, ['status' => $status]);
        $this->auditLog($request, 'invoice_status_updated', $id);

        return $updated !== null ? $this->toDto($updated) : ['success' => true];
    }

    /**
     * GET /api/v1/invoices/missing — zlecenia zakończone bez wystawionej faktury.
     *
     * @return array<string, mixed>
     */
    public function missing(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = $this->invoiceRepository->findOrdersWithoutInvoice($perPage, $offset);
        $total = $this->invoiceRepository->countOrdersWithoutInvoice();

        return [
            'data' => array_map(fn (array $row): array => [
                'order_id' => $this->toInt($row['id'] ?? 0),
                'numer_zlecenia' => is_string($row['numer_zlecenia'] ?? null) ? $row['numer_zlecenia'] : null,
                'klient_nazwa' => is_string($row['klient_nazwa'] ?? null) ? $row['klient_nazwa'] : null,
                'terminal_id' => isset($row['terminal_id']) ? $this->toInt($row['terminal_id']) : null,
                'data_zakonczenia' => is_string($row['data_zakonczenia'] ?? null) ? $row['data_zakonczenia'] : null,
                'wartosc_pln' => isset($row['wartosc_pln']) ? (is_numeric($row['wartosc_pln']) ? (float) $row['wartosc_pln'] : 0.0) : 0.0,
            ], $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    // --- Pomocnicze ---

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validate(array $data, ?int $ignoreId = null): ?array
    {
        $numer = is_string($data['numer_faktury'] ?? null) ? trim($data['numer_faktury']) : '';
        if ($numer === '') {
            return ['error' => 'Numer faktury is required', 'code' => 422];
        }
        if (mb_strlen($numer) > self::MAX_NUMER_LENGTH) {
            return ['error' => 'Numer faktury is too long', 'code' => 422];
        }

        $klient = is_string($data['klient_nazwa'] ?? null) ? trim($data['klient_nazwa']) : '';
        if ($klient === '') {
            return ['error' => 'Klient nazwa is required', 'code' => 422];
        }
        if (mb_strlen($klient) > self::MAX_KLIENT_LENGTH) {
            return ['error' => 'Klient nazwa is too long', 'code' => 422];
        }

        $dataWystawienia = is_string($data['data_wystawienia'] ?? null) ? trim($data['data_wystawienia']) : '';
        if ($dataWystawienia === '' || strtotime($dataWystawienia) === false) {
            return ['error' => 'Valid data_wystawienia is required', 'code' => 422];
        }

        $kwota = $this->toFloat($data['kwota_pln'] ?? null);
        if ($kwota < 0) {
            return ['error' => 'Kwota must not be negative', 'code' => 422];
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : 'wystawiona';
        if (!in_array($status, ['wystawiona', 'zaplacona', 'przeterminowana'], true)) {
            return ['error' => 'Invalid invoice status', 'code' => 422];
        }

        $typ = is_string($data['typ_wystawienia'] ?? null) ? $data['typ_wystawienia'] : 'po_zleceniu';
        if (!in_array($typ, ['po_zleceniu', 'po_tygodniu', 'koniec_miesiaca'], true)) {
            return ['error' => 'Invalid typ_wystawienia', 'code' => 422];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        $orderId = isset($data['order_id']) && is_numeric($data['order_id']) ? (int) $data['order_id'] : null;

        $terminPlatnosci = is_string($data['termin_platnosci'] ?? null) ? trim($data['termin_platnosci']) : '';
        if ($terminPlatnosci === '' || strtotime($terminPlatnosci) === false) {
            $terminPlatnosci = null;
        }

        return [
            'order_id' => $orderId,
            'numer_faktury' => trim($this->toStr($data['numer_faktury'])),
            'klient_nazwa' => trim($this->toStr($data['klient_nazwa'])),
            'kwota_pln' => $this->toFloat($data['kwota_pln'] ?? null),
            'data_wystawienia' => trim($this->toStr($data['data_wystawienia'])),
            'termin_platnosci' => $terminPlatnosci,
            'status' => is_string($data['status'] ?? null) ? $data['status'] : 'wystawiona',
            'typ_wystawienia' => is_string($data['typ_wystawienia'] ?? null) ? $data['typ_wystawienia'] : 'po_zleceniu',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'order_id' => isset($row['order_id']) && $row['order_id'] !== null ? $this->toInt($row['order_id']) : null,
            'numer_faktury' => is_string($row['numer_faktury'] ?? null) ? $row['numer_faktury'] : '',
            'klient_nazwa' => is_string($row['klient_nazwa'] ?? null) ? $row['klient_nazwa'] : '',
            'kwota_pln' => $this->toFloat($row['kwota_pln'] ?? null),
            'data_wystawienia' => is_string($row['data_wystawienia'] ?? null) ? $row['data_wystawienia'] : null,
            'termin_platnosci' => is_string($row['termin_platnosci'] ?? null) && $row['termin_platnosci'] !== '' ? $row['termin_platnosci'] : null,
            'status' => is_string($row['status'] ?? null) ? $row['status'] : 'wystawiona',
            'typ_wystawienia' => is_string($row['typ_wystawienia'] ?? null) ? $row['typ_wystawienia'] : 'po_zleceniu',
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function toStr(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'invoice',
            $resourceId,
        );
    }
}