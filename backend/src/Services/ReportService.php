<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\DailyTerminalReportRepository;
use App\Repository\DailyVehicleReportRepository;
use App\Repository\EquipmentRepository;
use App\Repository\OrderRepository;
use App\Repository\TerminalRepository;

/**
 * Serwis raportowania — sekcja Raportowanie (Etap 11).
 *
 * Operacje: lista (paginacja + filtry), szczegóły, tworzenie i edycja raportów
 * terminalowych (`daily_terminal_reports`) oraz pojazdowych (`daily_vehicle_reports`).
 *
 * Raport terminalowy zawiera auto-dane z harmonogramu (pracownicy, sprzęt i zlecenia
 * obecne w terminalu danego dnia) — pobierane z `orders` + przypisań (anti-N+1).
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (wymagane pola, format daty, długości, unikalność).
 * - Walidacja istnienia terminala / sprzętu (FK).
 * - `utworzony_przez` pobierany z JWT (ID zalogowanego usera).
 * - Audit log dla każdej akcji mutującej.
 */
final class ReportService
{
    private const int MAX_TEXT_LENGTH = 5000;
    private const int MAX_OC_LENGTH = 5000;

    public function __construct(
        private readonly DailyTerminalReportRepository $terminalReportRepository,
        private readonly DailyVehicleReportRepository $vehicleReportRepository,
        private readonly TerminalRepository $terminalRepository,
        private readonly EquipmentRepository $equipmentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    // --- Raporty terminalowe ---

    /**
     * Lista raportów terminalowych z paginacją i filtrami.
     *
     * @param array{terminal_id?: string, date_from?: string, date_to?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listTerminalReports(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->terminalReportRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->terminalReportRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->terminalToDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły raportu terminalowego (z auto-danymi z harmonogramu).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function getTerminalReport(int $id): array
    {
        $report = $this->terminalReportRepository->findById($id);
        if ($report === null) {
            return ['error' => 'Terminal report not found', 'code' => 404];
        }

        $dto = $this->terminalToDto($report);
        $dto['auto_data'] = $this->terminalAutoData(
            $this->toInt($report['terminal_id'] ?? 0),
            is_string($report['data_raportu'] ?? null) ? $report['data_raportu'] : '',
        );

        return $dto;
    }

    /**
     * Tworzenie raportu terminalowego.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createTerminalReport(array $data, Request $request): array
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $validation = $this->validateTerminal($data);
        if ($validation !== null) {
            return $validation;
        }

        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($this->terminalRepository->findById($terminalId) === null) {
            return ['error' => 'Terminal not found', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($this->terminalReportRepository->existsForTerminalAndDate($terminalId, $date)) {
            return ['error' => 'Terminal report for this date already exists', 'code' => 409];
        }

        $report = $this->terminalReportRepository->create([
            'terminal_id' => $terminalId,
            'data_raportu' => $date,
            'opis' => is_string($data['opis'] ?? null) ? trim($data['opis']) : '',
            'uwagi' => $this->nullableText($data['uwagi'] ?? null),
            'utworzony_przez' => $userId,
        ]);

        $id = $this->toInt($report['id'] ?? 0);
        $this->auditLog($request, 'report.terminal.create', $id);

        $dto = $this->terminalToDto($report);
        $dto['auto_data'] = $this->terminalAutoData($terminalId, $date);

        return $dto;
    }

    /**
     * Edycja raportu terminalowego.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateTerminalReport(int $id, array $data, Request $request): array
    {
        $report = $this->terminalReportRepository->findById($id);
        if ($report === null) {
            return ['error' => 'Terminal report not found', 'code' => 404];
        }

        $validation = $this->validateTerminal($data);
        if ($validation !== null) {
            return $validation;
        }

        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($this->terminalRepository->findById($terminalId) === null) {
            return ['error' => 'Terminal not found', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($this->terminalReportRepository->existsForTerminalAndDate($terminalId, $date, $id)) {
            return ['error' => 'Terminal report for this date already exists', 'code' => 409];
        }

        $updated = $this->terminalReportRepository->update($id, [
            'terminal_id' => $terminalId,
            'data_raportu' => $date,
            'opis' => is_string($data['opis'] ?? null) ? trim($data['opis']) : '',
            'uwagi' => $this->nullableText($data['uwagi'] ?? null),
        ]);

        $this->auditLog($request, 'report.terminal.update', $id);

        $dto = $this->terminalToDto($updated ?? $report);
        $dto['auto_data'] = $this->terminalAutoData($terminalId, $date);

        return $dto;
    }


    // --- Raporty pojazdowe ---

    /**
     * Lista raportów pojazdowych z paginacją i filtrami.
     *
     * @param array{equipment_id?: string, date_from?: string, date_to?: string, zrodlo?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listVehicleReports(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->vehicleReportRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->vehicleReportRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->vehicleToDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły raportu pojazdowego.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function getVehicleReport(int $id): array
    {
        $report = $this->vehicleReportRepository->findById($id);
        if ($report === null) {
            return ['error' => 'Vehicle report not found', 'code' => 404];
        }

        return $this->vehicleToDto($report);
    }

    /**
     * Tworzenie raportu pojazdowego.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createVehicleReport(array $data, Request $request): array
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $validation = $this->validateVehicle($data);
        if ($validation !== null) {
            return $validation;
        }

        $equipmentId = $this->toInt($data['equipment_id'] ?? 0);
        if ($this->equipmentRepository->findById($equipmentId) === null) {
            return ['error' => 'Equipment not found', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($this->vehicleReportRepository->existsForEquipmentAndDate($equipmentId, $date)) {
            return ['error' => 'Vehicle report for this date already exists', 'code' => 409];
        }

        $report = $this->vehicleReportRepository->create([
            'equipment_id' => $equipmentId,
            'data_raportu' => $date,
            'aktualny_przebieg' => $this->toInt($data['aktualny_przebieg'] ?? 0),
            'przebieg_oc' => is_string($data['przebieg_oc'] ?? null) ? trim($data['przebieg_oc']) : '',
            'uwagi' => $this->nullableText($data['uwagi'] ?? null),
            'utworzony_przez' => $userId,
        ]);

        $id = $this->toInt($report['id'] ?? 0);
        $this->auditLog($request, 'report.vehicle.create', $id);

        return $this->vehicleToDto($report);
    }

    /**
     * Edycja raportu pojazdowego.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateVehicleReport(int $id, array $data, Request $request): array
    {
        $report = $this->vehicleReportRepository->findById($id);
        if ($report === null) {
            return ['error' => 'Vehicle report not found', 'code' => 404];
        }

        $validation = $this->validateVehicle($data);
        if ($validation !== null) {
            return $validation;
        }

        $equipmentId = $this->toInt($data['equipment_id'] ?? 0);
        if ($this->equipmentRepository->findById($equipmentId) === null) {
            return ['error' => 'Equipment not found', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($this->vehicleReportRepository->existsForEquipmentAndDate($equipmentId, $date, $id)) {
            return ['error' => 'Vehicle report for this date already exists', 'code' => 409];
        }

        $updated = $this->vehicleReportRepository->update($id, [
            'equipment_id' => $equipmentId,
            'data_raportu' => $date,
            'aktualny_przebieg' => $this->toInt($data['aktualny_przebieg'] ?? 0),
            'przebieg_oc' => is_string($data['przebieg_oc'] ?? null) ? trim($data['przebieg_oc']) : '',
            'uwagi' => $this->nullableText($data['uwagi'] ?? null),
        ]);

        $this->auditLog($request, 'report.vehicle.update', $id);

        return $this->vehicleToDto($updated ?? $report);
    }


    // --- Auto-dane z harmonogramu (raport terminalowy) ---

    /**
     * Pracownicy, sprzęt i zlecenia obecne w terminalu danego dnia (z harmonogramu).
     *
     * @return array<string, mixed>
     */
    private function terminalAutoData(int $terminalId, string $date): array
    {
        $orders = $this->orderRepository->findOrdersForTerminalOnDate($terminalId, $date);

        $orderDtos = [];
        $employees = [];
        $equipment = [];
        $totalHours = 0.0;
        $totalWages = 0.0;

        foreach ($orders as $order) {
            $orderId = $this->toInt($order['id'] ?? 0);
            $orderDtos[] = [
                'id' => $orderId,
                'numer_zlecenia' => is_string($order['numer_zlecenia'] ?? null) ? $order['numer_zlecenia'] : '',
                'klient_nazwa' => is_string($order['klient_nazwa'] ?? null) ? $order['klient_nazwa'] : '',
                'data_rozpoczecia' => is_string($order['data_rozpoczecia'] ?? null) ? $order['data_rozpoczecia'] : null,
                'data_zakonczenia' => is_string($order['data_zakonczenia'] ?? null) ? $order['data_zakonczenia'] : null,
                'status' => is_string($order['status'] ?? null) ? $order['status'] : 'nowe',
            ];

            foreach ($this->orderRepository->findAssignedEmployees($orderId) as $emp) {
                $empId = $this->toInt($emp['employee_id'] ?? 0);
                $godziny = $this->nullableDecimal($emp['godziny'] ?? null);
                $stawka = $this->nullableDecimal($emp['stawka_godzinowa'] ?? null);
                $wynagrodzenie = 0.0;
                if ($godziny !== null && $stawka !== null) {
                    $wynagrodzenie = round($godziny * $stawka, 2);
                }
                if ($godziny !== null) {
                    $totalHours += $godziny;
                }
                $totalWages += $wynagrodzenie;

                if ($empId > 0 && !array_key_exists($empId, $employees)) {
                    $imie = is_string($emp['imie'] ?? null) ? $emp['imie'] : null;
                    $nazwisko = is_string($emp['nazwisko'] ?? null) ? $emp['nazwisko'] : null;
                    $employees[$empId] = [
                        'employee_id' => $empId,
                        'employee_name' => ($imie === null && $nazwisko === null) ? null : trim(((string) $imie . ' ' . (string) $nazwisko)),
                        'rola' => is_string($emp['rola'] ?? null) && $emp['rola'] !== '' ? $emp['rola'] : null,
                        'godziny' => $godziny,
                        'stawka_godzinowa' => $stawka,
                        'wynagrodzenie' => $wynagrodzenie,
                        'order_id' => $orderId,
                        'order_number' => is_string($order['numer_zlecenia'] ?? null) ? $order['numer_zlecenia'] : '',
                    ];
                }
            }

            foreach ($this->orderRepository->findAssignedEquipment($orderId) as $eq) {
                $eqId = $this->toInt($eq['equipment_id'] ?? 0);
                if ($eqId > 0 && !array_key_exists($eqId, $equipment)) {
                    $equipment[$eqId] = [
                        'equipment_id' => $eqId,
                        'equipment_nazwa' => is_string($eq['nazwa'] ?? null) ? $eq['nazwa'] : null,
                        'equipment_numer_seryjny' => is_string($eq['numer_seryjny'] ?? null) ? $eq['numer_seryjny'] : null,
                        'equipment_kategoria' => is_string($eq['kategoria'] ?? null) ? $eq['kategoria'] : null,
                    ];
                }
            }
        }

        return [
            'orders' => $orderDtos,
            'employees' => array_values($employees),
            'equipment' => array_values($equipment),
            'total_hours' => round($totalHours, 2),
            'total_wages' => round($totalWages, 2),
        ];
    }


    // --- DTO ---

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function terminalToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'terminal_id' => $this->toInt($row['terminal_id'] ?? 0),
            'terminal_nazwa' => is_string($row['terminal_nazwa'] ?? null) ? $row['terminal_nazwa'] : null,
            'data_raportu' => is_string($row['data_raportu'] ?? null) ? $row['data_raportu'] : null,
            'opis' => is_string($row['opis'] ?? null) ? $row['opis'] : '',
            'uwagi' => is_string($row['uwagi'] ?? null) ? $row['uwagi'] : null,
            'utworzony_przez' => $this->toInt($row['utworzony_przez'] ?? 0),
            'utworzony_przez_email' => is_string($row['utworzony_przez_email'] ?? null) ? $row['utworzony_przez_email'] : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function vehicleToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
            'equipment_nazwa' => is_string($row['equipment_nazwa'] ?? null) ? $row['equipment_nazwa'] : null,
            'equipment_numer_seryjny' => is_string($row['equipment_numer_seryjny'] ?? null) ? $row['equipment_numer_seryjny'] : null,
            'equipment_kategoria' => is_string($row['equipment_kategoria'] ?? null) ? $row['equipment_kategoria'] : null,
            'data_raportu' => is_string($row['data_raportu'] ?? null) ? $row['data_raportu'] : null,
            'aktualny_przebieg' => $this->toInt($row['aktualny_przebieg'] ?? 0),
            'przebieg_oc' => is_string($row['przebieg_oc'] ?? null) ? $row['przebieg_oc'] : '',
            'uwagi' => is_string($row['uwagi'] ?? null) ? $row['uwagi'] : null,
            'utworzony_przez' => $this->nullableUserId($row['utworzony_przez'] ?? null),
            'utworzony_przez_email' => is_string($row['utworzony_przez_email'] ?? null) ? $row['utworzony_przez_email'] : null,
            'zrodlo' => is_string($row['zrodlo'] ?? null) ? $row['zrodlo'] : 'panel',
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }


    // --- Walidacja ---

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateTerminal(array $data): ?array
    {
        $terminalId = $this->toInt($data['terminal_id'] ?? 0);
        if ($terminalId <= 0) {
            return ['error' => 'Terminal is required', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($date === '' || strtotime($date) === false) {
            return ['error' => 'Invalid data_raportu', 'code' => 422];
        }

        $opis = is_string($data['opis'] ?? null) ? trim($data['opis']) : '';
        if ($opis === '') {
            return ['error' => 'Opis is required', 'code' => 422];
        }
        if (mb_strlen($opis) > self::MAX_TEXT_LENGTH) {
            return ['error' => 'Opis is too long', 'code' => 422];
        }

        $uwagi = $data['uwagi'] ?? null;
        if ($uwagi !== null && (!is_string($uwagi) || mb_strlen(trim($uwagi)) > self::MAX_TEXT_LENGTH)) {
            return ['error' => 'Uwagi is too long', 'code' => 422];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateVehicle(array $data): ?array
    {
        $equipmentId = $this->toInt($data['equipment_id'] ?? 0);
        if ($equipmentId <= 0) {
            return ['error' => 'Equipment is required', 'code' => 422];
        }

        $date = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : '';
        if ($date === '' || strtotime($date) === false) {
            return ['error' => 'Invalid data_raportu', 'code' => 422];
        }

        $przebieg = $data['aktualny_przebieg'] ?? null;
        if (!is_numeric($przebieg) || (int) $przebieg < 0) {
            return ['error' => 'Invalid aktualny_przebieg', 'code' => 422];
        }

        $oc = is_string($data['przebieg_oc'] ?? null) ? trim($data['przebieg_oc']) : '';
        if ($oc === '') {
            return ['error' => 'Przebieg_oc is required', 'code' => 422];
        }
        if (mb_strlen($oc) > self::MAX_OC_LENGTH) {
            return ['error' => 'Przebieg_oc is too long', 'code' => 422];
        }

        $uwagi = $data['uwagi'] ?? null;
        if ($uwagi !== null && (!is_string($uwagi) || mb_strlen(trim($uwagi)) > self::MAX_TEXT_LENGTH)) {
            return ['error' => 'Uwagi is too long', 'code' => 422];
        }

        return null;
    }

    // --- Pomocnicze ---

    private function nullableText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
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

    private function nullableUserId(mixed $value): ?int
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

    private function userId(Request $request): ?int
    {
        $userId = $request->attribute('user_id');

        return is_int($userId) ? $userId : null;
    }

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'report',
            $resourceId,
        );
    }
}

