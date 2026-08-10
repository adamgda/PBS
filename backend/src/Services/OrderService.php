<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\OrderRepository;
use App\Repository\TerminalRepository;

/**
 * Serwis zarządzania zleceniami — sekcja Harmonogram / Zlecenia (Etap 9).
 *
 * Operacje: lista (paginacja + filtry: numer, klient, terminal, status, zakres dat),
 * tworzenie, edycja, usuwanie, kopiowanie tygodnia jako szablon, przypisywanie
 * pracowników i sprzętu do zlecenia (N:M).
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (wymagane pola, enum statusu, unikalność numeru).
 * - Walidacja istnienia terminala (FK) oraz pracownika/sprzętu przy przypisaniach.
 * - Audit log dla każdej akcji mutującej.
 */
final class OrderService
{
    private const int MAX_NUMBER_LENGTH = 50;
    private const int MAX_CLIENT_LENGTH = 255;
    private const int MAX_SCOPE_LENGTH = 5000;
    private const array VALID_STATUSES = ['nowe', 'w_realizacji', 'zakonczone'];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly TerminalRepository $terminalRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly EquipmentRepository $equipmentRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista zleceń z paginacją i filtrami.
     *
     * @param array{numer?: string, klient?: string, terminal_id?: string, status?: string, date_from?: string, date_to?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->orderRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->orderRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły zlecenia (z przypisanymi pracownikami i sprzętem).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }

        $dto = $this->toDto($order);
        $dto['employees'] = array_map(fn (array $e): array => $this->employeeToDto($e), $this->orderRepository->findAssignedEmployees($id));
        $dto['equipment'] = array_map(fn (array $e): array => $this->equipmentAssignmentToDto($e), $this->orderRepository->findAssignedEquipment($id));

        return $dto;
    }

    /**
     * Tworzenie zlecenia.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(array $data, Request $request): array
    {
        $validation = $this->validate($data);
        if ($validation !== null) {
            return $validation;
        }

        $numer = is_string($data['numer_zlecenia'] ?? null) ? trim($data['numer_zlecenia']) : '';
        if ($this->orderRepository->findByNumber($numer) !== null) {
            return ['error' => 'Order with this number already exists', 'code' => 409];
        }

        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($this->terminalRepository->findById($terminalId) === null) {
            return ['error' => 'Terminal not found', 'code' => 422];
        }

        $order = $this->orderRepository->createOrder([
            'numer_zlecenia' => $numer,
            'klient_nazwa' => is_string($data['klient_nazwa'] ?? null) ? trim($data['klient_nazwa']) : '',
            'terminal_id' => $terminalId,
            'data_rozpoczecia' => is_string($data['data_rozpoczecia'] ?? null) ? $data['data_rozpoczecia'] : '',
            'data_zakonczenia' => is_string($data['data_zakonczenia'] ?? null) ? $data['data_zakonczenia'] : '',
            'zakres_prac' => is_string($data['zakres_prac'] ?? null) ? $data['zakres_prac'] : '',
            'wartosc_pln' => $this->toDecimal($data['wartosc_pln'] ?? 0),
            'status' => $this->resolveStatus($data['status'] ?? null),
        ]);

        $id = $this->toInt($order['id'] ?? 0);
        $this->auditLog($request, 'order.create', $id);

        return $this->toDto($order);
    }

    /**
     * Edycja zlecenia.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }

        $validation = $this->validate($data);
        if ($validation !== null) {
            return $validation;
        }

        $numer = is_string($data['numer_zlecenia'] ?? null) ? trim($data['numer_zlecenia']) : '';
        $existing = $this->orderRepository->findByNumber($numer);
        if ($existing !== null && $this->toInt($existing['id']) !== $id) {
            return ['error' => 'Order with this number already exists', 'code' => 409];
        }

        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($this->terminalRepository->findById($terminalId) === null) {
            return ['error' => 'Terminal not found', 'code' => 422];
        }

        $updated = $this->orderRepository->updateOrder($id, [
            'numer_zlecenia' => $numer,
            'klient_nazwa' => is_string($data['klient_nazwa'] ?? null) ? trim($data['klient_nazwa']) : '',
            'terminal_id' => $terminalId,
            'data_rozpoczecia' => is_string($data['data_rozpoczecia'] ?? null) ? $data['data_rozpoczecia'] : '',
            'data_zakonczenia' => is_string($data['data_zakonczenia'] ?? null) ? $data['data_zakonczenia'] : '',
            'zakres_prac' => is_string($data['zakres_prac'] ?? null) ? $data['zakres_prac'] : '',
            'wartosc_pln' => $this->toDecimal($data['wartosc_pln'] ?? 0),
            'status' => $this->resolveStatus($data['status'] ?? null),
        ]);

        $this->auditLog($request, 'order.update', $id);

        return $this->toDto($updated ?? $order);
    }

    /**
     * Usunięcie zlecenia (kaskadowo usuwa przypisania).
     *
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }

        $this->orderRepository->deleteOrder($id);
        $this->auditLog($request, 'order.delete', $id);

        return ['success' => true];
    }

    /**
     * Kopiowanie tygodnia jako szablon — zlecenia z tygodnia źródłowego są
     * duplikowane w tygodniu docelowym (daty przesunięte o różnicę dni).
     * Przypisania pracowników i sprzętu są kopiowane.
     *
     * @param array<string, mixed> $data
     * @return array{copied: int}|array{error: string, code: int}
     */
    public function copyWeek(array $data, Request $request): array
    {
        $sourceStart = is_string($data['source_week_start'] ?? null) ? trim($data['source_week_start']) : '';
        $targetStart = is_string($data['target_week_start'] ?? null) ? trim($data['target_week_start']) : '';

        if ($sourceStart === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourceStart)) {
            return ['error' => 'Invalid source_week_start (expected YYYY-MM-DD)', 'code' => 422];
        }
        if ($targetStart === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetStart)) {
            return ['error' => 'Invalid target_week_start (expected YYYY-MM-DD)', 'code' => 422];
        }

        $sourceEndTs = strtotime($sourceStart . ' +6 days');
        $sourceEnd = $sourceEndTs === false ? $sourceStart : date('Y-m-d', $sourceEndTs);
        $targetTs = strtotime($targetStart);
        $sourceTs = strtotime($sourceStart);
        $deltaSeconds = ($targetTs === false || $sourceTs === false) ? 0 : $targetTs - $sourceTs;

        $sourceOrders = $this->orderRepository->findBetweenDates($sourceStart, $sourceEnd);
        $copied = 0;
        foreach ($sourceOrders as $src) {
            $startRaw = is_string($src['data_rozpoczecia'] ?? null) ? $src['data_rozpoczecia'] : '';
            $endRaw = is_string($src['data_zakonczenia'] ?? null) ? $src['data_zakonczenia'] : '';
            $startTs = strtotime($startRaw);
            $endTs = strtotime($endRaw);
            $newStart = $startTs === false ? $startRaw : date('Y-m-d H:i:s', $startTs + $deltaSeconds);
            $newEnd = $endTs === false ? $endRaw : date('Y-m-d H:i:s', $endTs + $deltaSeconds);
            $baseNumer = is_string($src['numer_zlecenia'] ?? null) ? $src['numer_zlecenia'] : 'ZLEC';
            $newNumer = $this->uniqueNumber($baseNumer . '-' . str_replace('-', '', $targetStart));

            $created = $this->orderRepository->createOrder([
                'numer_zlecenia' => $newNumer,
                'klient_nazwa' => is_string($src['klient_nazwa'] ?? null) ? $src['klient_nazwa'] : '',
                'terminal_id' => $this->toInt($src['terminal_id'] ?? 0),
                'data_rozpoczecia' => $newStart,
                'data_zakonczenia' => $newEnd,
                'zakres_prac' => is_string($src['zakres_prac'] ?? null) ? $src['zakres_prac'] : '',
                'wartosc_pln' => $this->toDecimal($src['wartosc_pln'] ?? 0),
                'status' => 'nowe',
            ]);

            $newId = $this->toInt($created['id'] ?? 0);
            $srcId = $this->toInt($src['id'] ?? 0);

            foreach ($this->orderRepository->findAssignedEmployees($srcId) as $ae) {
                $empId = $this->nullableInt($ae['employee_id'] ?? null);
                if ($empId !== null) {
                    $this->orderRepository->attachEmployee($newId, $empId);
                }
            }
            foreach ($this->orderRepository->findAssignedEquipment($srcId) as $eq) {
                $eqId = $this->nullableInt($eq['equipment_id'] ?? null);
                if ($eqId !== null) {
                    $this->orderRepository->attachEquipment($newId, $eqId);
                }
            }
            $copied++;
        }

        $this->auditLog($request, 'order.copy_week', 0);
        if ($copied === 0) {
            return ['copied' => 0];
        }

        return ['copied' => $copied];
    }

    // --- Przypisania pracowników i sprzętu ---

    /**
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function assignEmployee(int $orderId, int $employeeId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 422];
        }
        if ($this->orderRepository->isEmployeeAssigned($orderId, $employeeId)) {
            return ['error' => 'Employee already assigned to this order', 'code' => 409];
        }

        $body = $request->body();
        $rola = is_string($body['rola'] ?? null) ? $body['rola'] : null;
        if ($rola !== null && !in_array($rola, ['operator', 'brygadzista', 'sztauer', 'lukowy', 'operator_zurawia'], true)) {
            return ['error' => 'Invalid rola', 'code' => 422];
        }

        $godziny = null;
        if (array_key_exists('godziny', $body) && $body['godziny'] !== null) {
            $godzinyVal = $body['godziny'];
            if (is_numeric($godzinyVal)) {
                $godziny = (float) $godzinyVal;
                if ($godziny < 0) {
                    return ['error' => 'godziny must not be negative', 'code' => 422];
                }
            } else {
                return ['error' => 'godziny must be numeric', 'code' => 422];
            }
        }

        $this->orderRepository->attachEmployee($orderId, $employeeId, $rola, $godziny);
        $this->auditLog($request, 'order.assign_employee', $orderId);

        return [
            'order_id' => $orderId,
            'employee_id' => $employeeId,
            'rola' => $rola,
            'godziny' => $godziny,
            'assigned' => true,
        ];
    }

    /**
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function unassignEmployee(int $orderId, int $employeeId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }
        if (!$this->orderRepository->isEmployeeAssigned($orderId, $employeeId)) {
            return ['error' => 'Employee is not assigned to this order', 'code' => 404];
        }

        $this->orderRepository->detachEmployee($orderId, $employeeId);
        $this->auditLog($request, 'order.unassign_employee', $orderId);

        return ['success' => true];
    }

    /**
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function assignEquipment(int $orderId, int $equipmentId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }
        if ($this->equipmentRepository->findById($equipmentId) === null) {
            return ['error' => 'Equipment not found', 'code' => 422];
        }
        if ($this->orderRepository->isEquipmentAssigned($orderId, $equipmentId)) {
            return ['error' => 'Equipment already assigned to this order', 'code' => 409];
        }

        $this->orderRepository->attachEquipment($orderId, $equipmentId);
        $this->auditLog($request, 'order.assign_equipment', $orderId);

        return ['order_id' => $orderId, 'equipment_id' => $equipmentId, 'assigned' => true];
    }

    /**
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function unassignEquipment(int $orderId, int $equipmentId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            return ['error' => 'Order not found', 'code' => 404];
        }
        if (!$this->orderRepository->isEquipmentAssigned($orderId, $equipmentId)) {
            return ['error' => 'Equipment is not assigned to this order', 'code' => 404];
        }

        $this->orderRepository->detachEquipment($orderId, $equipmentId);
        $this->auditLog($request, 'order.unassign_equipment', $orderId);

        return ['success' => true];
    }

    // --- DTO ---

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'numer_zlecenia' => is_string($row['numer_zlecenia'] ?? null) ? $row['numer_zlecenia'] : '',
            'klient_nazwa' => is_string($row['klient_nazwa'] ?? null) ? $row['klient_nazwa'] : '',
            'terminal_id' => $this->toInt($row['terminal_id'] ?? 0),
            'terminal_nazwa' => is_string($row['terminal_nazwa'] ?? null) ? $row['terminal_nazwa'] : null,
            'data_rozpoczecia' => is_string($row['data_rozpoczecia'] ?? null) ? $row['data_rozpoczecia'] : null,
            'data_zakonczenia' => is_string($row['data_zakonczenia'] ?? null) ? $row['data_zakonczenia'] : null,
            'zakres_prac' => is_string($row['zakres_prac'] ?? null) ? $row['zakres_prac'] : '',
            'wartosc_pln' => $this->toDecimal($row['wartosc_pln'] ?? 0),
            'status' => is_string($row['status'] ?? null) ? $row['status'] : 'nowe',
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function employeeToDto(array $row): array
    {
        $imie = is_string($row['imie'] ?? null) ? $row['imie'] : null;
        $nazwisko = is_string($row['nazwisko'] ?? null) ? $row['nazwisko'] : null;
        $rola = is_string($row['rola'] ?? null) && $row['rola'] !== '' ? $row['rola'] : null;

        $godziny = null;
        if (array_key_exists('godziny', $row) && $row['godziny'] !== null) {
            $godziny = is_numeric($row['godziny']) ? (float) $row['godziny'] : null;
        }

        $stawka = null;
        if (array_key_exists('stawka_godzinowa', $row) && $row['stawka_godzinowa'] !== null) {
            $stawka = is_numeric($row['stawka_godzinowa']) ? (float) $row['stawka_godzinowa'] : null;
        }

        $wynagrodzenie = 0.0;
        if ($godziny !== null && $stawka !== null) {
            $wynagrodzenie = round($godziny * $stawka, 2);
        }

        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'order_id' => $this->toInt($row['order_id'] ?? 0),
            'employee_id' => $this->nullableInt($row['employee_id'] ?? null),
            'employee_name' => ($imie === null && $nazwisko === null) ? null : trim(((string) $imie . ' ' . (string) $nazwisko)),
            'employee_email' => is_string($row['email'] ?? null) ? $row['email'] : null,
            'rola' => $rola,
            'godziny' => $godziny,
            'stawka_godzinowa' => $stawka,
            'wynagrodzenie' => $wynagrodzenie,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function equipmentAssignmentToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'order_id' => $this->toInt($row['order_id'] ?? 0),
            'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
            'equipment_nazwa' => is_string($row['nazwa'] ?? null) ? $row['nazwa'] : null,
            'equipment_numer_seryjny' => is_string($row['numer_seryjny'] ?? null) ? $row['numer_seryjny'] : null,
            'equipment_kategoria' => is_string($row['kategoria'] ?? null) ? $row['kategoria'] : null,
        ];
    }

    // --- Walidacja ---

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validate(array $data): ?array
    {
        $numer = is_string($data['numer_zlecenia'] ?? null) ? trim($data['numer_zlecenia']) : '';
        if ($numer === '') {
            return ['error' => 'Numer zlecenia is required', 'code' => 422];
        }
        if (mb_strlen($numer) > self::MAX_NUMBER_LENGTH) {
            return ['error' => 'Numer zlecenia is too long', 'code' => 422];
        }

        $klient = is_string($data['klient_nazwa'] ?? null) ? trim($data['klient_nazwa']) : '';
        if ($klient === '') {
            return ['error' => 'Klient is required', 'code' => 422];
        }
        if (mb_strlen($klient) > self::MAX_CLIENT_LENGTH) {
            return ['error' => 'Klient is too long', 'code' => 422];
        }

        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($terminalId <= 0) {
            return ['error' => 'Terminal is required', 'code' => 422];
        }

        $start = is_string($data['data_rozpoczecia'] ?? null) ? trim($data['data_rozpoczecia']) : '';
        if ($start === '' || strtotime($start) === false) {
            return ['error' => 'Invalid data_rozpoczecia', 'code' => 422];
        }

        $end = is_string($data['data_zakonczenia'] ?? null) ? trim($data['data_zakonczenia']) : '';
        if ($end === '' || strtotime($end) === false) {
            return ['error' => 'Invalid data_zakonczenia', 'code' => 422];
        }

        if (strtotime($end) < strtotime($start)) {
            return ['error' => 'Data_zakonczenia must be after data_rozpoczecia', 'code' => 422];
        }

        $zakres = is_string($data['zakres_prac'] ?? null) ? $data['zakres_prac'] : '';
        if (mb_strlen($zakres) > self::MAX_SCOPE_LENGTH) {
            return ['error' => 'Zakres prac is too long', 'code' => 422];
        }

        $wartosc = $data['wartosc_pln'] ?? 0;
        if (!is_numeric($wartosc) || (float) $wartosc < 0) {
            return ['error' => 'Invalid wartosc_pln', 'code' => 422];
        }

        $status = $data['status'] ?? null;
        if ($status !== null && (!is_string($status) || !in_array($status, self::VALID_STATUSES, true))) {
            return ['error' => 'Invalid status', 'code' => 422];
        }

        return null;
    }

    // --- Pomocnicze ---

    private function resolveStatus(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::VALID_STATUSES, true)) {
            return $value;
        }

        return 'nowe';
    }

    private function uniqueNumber(string $base): string
    {
        $candidate = $base;
        $i = 1;
        while ($this->orderRepository->findByNumber($candidate) !== null) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    private function toDecimal(mixed $value): float
    {
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'order',
            $resourceId,
        );
    }
}