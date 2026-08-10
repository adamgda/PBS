<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentHistoryRepository;
use App\Repository\EquipmentRepository;
use App\Repository\ServicePlanRepository;
use App\Repository\VehicleDetailsRepository;

/**
 * Serwis zarządzania sprzętem — sekcja Sprzęt (Etap 8).
 *
 * Operacje: lista (paginacja + filtry: nazwa, kategoria, pracownik, terminal, status),
 * tworzenie, edycja, usuwanie, szybkie przypisanie pracownika/terminala,
 * zarządzanie planami przeglądów pojazdu, oś czasu sprzętu (historia).
 *
 * Pojazdy (kategoria „pojazd") posiadają dodatkowe dane w `vehicle_details`
 * (przebieg, serwis olejowy, awaria, OC).
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (wymagane pola, enum kategorii).
 * - Unikalność nazwy sprzętu (409 przy konflikcie).
 * - Audit log dla każdej akcji mutującej.
 * - Historia sprzętu (append-only) dla każdej akcji mutującej.
 */
final class EquipmentService
{
    private const int MAX_NAME_LENGTH = 255;
    private const int MAX_SERIAL_LENGTH = 100;
    private const int MAX_INSPECTION_TYPE_LENGTH = 255;
    private const array VALID_CATEGORIES = ['pojazd', 'inne'];

    public function __construct(
        private readonly EquipmentRepository $equipmentRepository,
        private readonly VehicleDetailsRepository $vehicleDetailsRepository,
        private readonly ServicePlanRepository $servicePlanRepository,
        private readonly EquipmentHistoryRepository $historyRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista sprzętu z paginacją i filtrami.
     *
     * @param array{nazwa?: string, kategoria?: string, numer_seryjny?: string, ostatni_przebieg?: string, employee_id?: string, terminal_id?: string, is_active?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->equipmentRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->equipmentRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły sprzętu (z danymi pojazdu, planami przeglądów i osią czasu).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $equipment = $this->equipmentRepository->findById($id);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $dto = $this->toDto($equipment);

        if ($dto['kategoria'] === 'pojazd') {
            $details = $this->vehicleDetailsRepository->findByEquipmentId($id);
            $dto['vehicle_details'] = $details !== null ? $this->vehicleDetailsToDto($details) : null;
        }

        $plans = $this->servicePlanRepository->findByEquipmentId($id);
        $dto['service_plans'] = array_map(fn (array $p): array => $this->servicePlanToDto($p), $plans);

        $history = $this->historyRepository->findByEquipmentId($id);
        $dto['timeline'] = array_map(fn (array $h): array => $this->historyToDto($h), $history);

        return $dto;
    }

    /**
     * Tworzenie sprzętu (z opcjonalnymi danymi pojazdu).
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

        $nazwa = is_string($data['nazwa'] ?? null) ? $data['nazwa'] : '';
        if ($this->equipmentRepository->findByName($nazwa) !== null) {
            return ['error' => 'Equipment with this name already exists', 'code' => 409];
        }

        $kategoria = is_string($data['kategoria'] ?? null) ? $data['kategoria'] : 'inne';
        $serial = $this->nullableString($data['numer_seryjny'] ?? null);

        $equipment = $this->equipmentRepository->createEquipment([
            'kategoria' => $kategoria,
            'nazwa' => $nazwa,
            'numer_seryjny' => $serial,
            'current_employee_id' => $this->nullableInt($data['current_employee_id'] ?? null),
            'current_terminal_id' => $this->nullableInt($data['current_terminal_id'] ?? null),
            'is_active' => $this->toBool($data['is_active'] ?? true, true),
        ]);

        $id = $this->toInt($equipment['id'] ?? 0);

        $vehicleDetails = null;
        if ($kategoria === 'pojazd') {
            $vehicleDetails = $this->createVehicleDetails($id, $data);
        }

        $this->historyRepository->add($id, 'inne', 'Utworzono sprzęt', $this->userId($request));
        $this->auditLog($request, 'equipment.create', $id);

        $dto = $this->toDto($equipment);
        $dto['vehicle_details'] = $vehicleDetails;

        return $dto;
    }
    /**
     * Edycja sprzętu (z opcjonalną aktualizacją danych pojazdu).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $equipment = $this->equipmentRepository->findById($id);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $validation = $this->validate($data);
        if ($validation !== null) {
            return $validation;
        }

        $nazwa = is_string($data['nazwa'] ?? null) ? $data['nazwa'] : '';
        $existing = $this->equipmentRepository->findByName($nazwa);
        if ($existing !== null && $this->toInt($existing['id']) !== $id) {
            return ['error' => 'Equipment with this name already exists', 'code' => 409];
        }

        $kategoria = is_string($data['kategoria'] ?? null) ? $data['kategoria'] : 'inne';
        $oldKategoria = is_string($equipment['kategoria'] ?? null) ? $equipment['kategoria'] : 'inne';

        $updated = $this->equipmentRepository->updateEquipment($id, [
            'kategoria' => $kategoria,
            'nazwa' => $nazwa,
            'numer_seryjny' => $this->nullableString($data['numer_seryjny'] ?? null),
            'current_employee_id' => $this->nullableInt($data['current_employee_id'] ?? null),
            'current_terminal_id' => $this->nullableInt($data['current_terminal_id'] ?? null),
            'is_active' => $this->toBool($data['is_active'] ?? true, true),
        ]);

        $vehicleDetails = null;
        if ($kategoria === 'pojazd') {
            $vehicleDetails = $this->upsertVehicleDetails($id, $data);
        } elseif ($oldKategoria === 'pojazd') {
            $this->vehicleDetailsRepository->deleteDetails($id);
        }

        $this->historyRepository->add($id, 'inne', 'Zaktualizowano sprzęt', $this->userId($request));
        $this->auditLog($request, 'equipment.update', $id);

        $dto = $this->toDto($updated ?? $equipment);
        $dto['vehicle_details'] = $vehicleDetails;

        return $dto;
    }

    /**
     * Usunięcie sprzętu (kaskadowo: vehicle_details, service_plans, history).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $equipment = $this->equipmentRepository->findById($id);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $this->equipmentRepository->delete($id);
        $this->auditLog($request, 'equipment.delete', $id);

        return ['success' => true];
    }

    /**
     * Szybkie przypisanie pracownika i/lub terminala (PATCH /assignment).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function assign(int $id, array $data, Request $request): array
    {
        $equipment = $this->equipmentRepository->findById($id);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $updateData = [];
        $descriptionParts = [];

        if (array_key_exists('current_employee_id', $data)) {
            $updateData['current_employee_id'] = $this->nullableInt($data['current_employee_id']);
            $descriptionParts[] = 'pracownik';
        }
        if (array_key_exists('current_terminal_id', $data)) {
            $updateData['current_terminal_id'] = $this->nullableInt($data['current_terminal_id']);
            $descriptionParts[] = 'terminal';
        }

        if ($updateData === []) {
            return ['error' => 'No assignment fields provided', 'code' => 422];
        }

        $updated = $this->equipmentRepository->updateAssignment($id, $updateData);
        $this->historyRepository->add($id, 'przypisanie', 'Przypisanie: ' . implode(', ', $descriptionParts), $this->userId($request));
        $this->auditLog($request, 'equipment.assign', $id);

        return $this->toDto($updated ?? $equipment);
    }
    /**
     * Lista planów przeglądów dla sprzętu.
     *
     * @return array<int, array<string, mixed>>|array{error: string, code: int}
     */
    public function listServicePlans(int $equipmentId): array
    {
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $plans = $this->servicePlanRepository->findByEquipmentId($equipmentId);

        return array_map(fn (array $p): array => $this->servicePlanToDto($p), $plans);
    }

    /**
     * Dodanie planu przeglądu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createServicePlan(int $equipmentId, array $data, Request $request): array
    {
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $validation = $this->validateServicePlan($data);
        if ($validation !== null) {
            return $validation;
        }

        $interwalKm = $this->nullableInt($data['interwal_km'] ?? null);
        $interwalDni = $this->nullableInt($data['interwal_dni'] ?? null);
        $dataOstatniego = $this->nullableString($data['data_ostatniego_wykonania'] ?? null);
        $dataNastepnego = $this->computeNextPlanned($dataOstatniego, $interwalDni, $this->nullableString($data['data_nastepnego_planowanego'] ?? null));

        $plan = $this->servicePlanRepository->createPlan([
            'equipment_id' => $equipmentId,
            'typ_przegladu' => is_string($data['typ_przegladu'] ?? null) ? $data['typ_przegladu'] : '',
            'interwal_km' => $interwalKm,
            'interwal_dni' => $interwalDni,
            'data_ostatniego_wykonania' => $dataOstatniego,
            'data_nastepnego_planowanego' => $dataNastepnego,
            'is_active' => $this->toBool($data['is_active'] ?? true, true),
        ]);

        $this->historyRepository->add($equipmentId, 'serwis', 'Dodano plan przeglądu', $this->userId($request));
        $this->auditLog($request, 'service_plan.create', $equipmentId);

        return $this->servicePlanToDto($plan);
    }

    /**
     * Edycja planu przeglądu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateServicePlan(int $planId, array $data, Request $request): array
    {
        $plan = $this->servicePlanRepository->findById($planId);
        if ($plan === null) {
            return ['error' => 'Service plan not found', 'code' => 404];
        }

        $validation = $this->validateServicePlan($data);
        if ($validation !== null) {
            return $validation;
        }

        $interwalKm = $this->nullableInt($data['interwal_km'] ?? null);
        $interwalDni = $this->nullableInt($data['interwal_dni'] ?? null);
        $dataOstatniego = $this->nullableString($data['data_ostatniego_wykonania'] ?? null);
        $dataNastepnego = $this->computeNextPlanned($dataOstatniego, $interwalDni, $this->nullableString($data['data_nastepnego_planowanego'] ?? null));

        $updated = $this->servicePlanRepository->updatePlan($planId, [
            'typ_przegladu' => is_string($data['typ_przegladu'] ?? null) ? $data['typ_przegladu'] : '',
            'interwal_km' => $interwalKm,
            'interwal_dni' => $interwalDni,
            'data_ostatniego_wykonania' => $dataOstatniego,
            'data_nastepnego_planowanego' => $dataNastepnego,
            'is_active' => $this->toBool($data['is_active'] ?? true, true),
        ]);

        $this->auditLog($request, 'service_plan.update', $planId);

        return $this->servicePlanToDto($updated ?? $plan);
    }

    /**
     * Usunięcie planu przeglądu.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function deleteServicePlan(int $planId, Request $request): array
    {
        $plan = $this->servicePlanRepository->findById($planId);
        if ($plan === null) {
            return ['error' => 'Service plan not found', 'code' => 404];
        }

        $this->servicePlanRepository->deletePlan($planId);
        $this->auditLog($request, 'service_plan.delete', $planId);

        return ['success' => true];
    }

    /**
     * Oś czasu sprzętu (historia zdarzeń).
     *
     * @return array<int, array<string, mixed>>|array{error: string, code: int}
     */
    public function timeline(int $equipmentId): array
    {
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        $history = $this->historyRepository->findByEquipmentId($equipmentId);

        return array_map(fn (array $h): array => $this->historyToDto($h), $history);
    }
    // --- Walidacja ---

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validate(array $data): ?array
    {
        $nazwa = is_string($data['nazwa'] ?? null) ? trim($data['nazwa']) : '';
        if ($nazwa === '') {
            return ['error' => 'Name is required', 'code' => 422];
        }
        if (mb_strlen($nazwa) > self::MAX_NAME_LENGTH) {
            return ['error' => 'Name is too long', 'code' => 422];
        }

        $kategoria = is_string($data['kategoria'] ?? null) ? $data['kategoria'] : '';
        if (!in_array($kategoria, self::VALID_CATEGORIES, true)) {
            return ['error' => 'Invalid category', 'code' => 422];
        }

        $serial = $data['numer_seryjny'] ?? null;
        if ($serial !== null && !is_string($serial)) {
            return ['error' => 'Invalid serial number', 'code' => 422];
        }
        if (is_string($serial) && mb_strlen($serial) > self::MAX_SERIAL_LENGTH) {
            return ['error' => 'Serial number is too long', 'code' => 422];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateServicePlan(array $data): ?array
    {
        $typ = is_string($data['typ_przegladu'] ?? null) ? trim($data['typ_przegladu']) : '';
        if ($typ === '') {
            return ['error' => 'Inspection type is required', 'code' => 422];
        }
        if (mb_strlen($typ) > self::MAX_INSPECTION_TYPE_LENGTH) {
            return ['error' => 'Inspection type is too long', 'code' => 422];
        }

        $interwalKm = $data['interwal_km'] ?? null;
        if ($interwalKm !== null && $interwalKm !== '' && !is_numeric($interwalKm)) {
            return ['error' => 'Invalid km interval', 'code' => 422];
        }

        $interwalDni = $data['interwal_dni'] ?? null;
        if ($interwalDni !== null && $interwalDni !== '' && !is_numeric($interwalDni)) {
            return ['error' => 'Invalid days interval', 'code' => 422];
        }

        return null;
    }

    // --- Dane pojazdu ---

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function createVehicleDetails(int $equipmentId, array $data): array
    {
        $details = $this->vehicleDetailsRepository->createDetails([
            'equipment_id' => $equipmentId,
            'ostatni_przebieg' => $this->toNonNegInt($data['ostatni_przebieg'] ?? 0, 0),
            'ostatni_serwis_olejowy' => $this->nullableString($data['ostatni_serwis_olejowy'] ?? null),
            'ostatnia_awaria' => $this->nullableString($data['ostatnia_awaria'] ?? null),
            'data_ostatniej_oc' => $this->nullableString($data['data_ostatniej_oc'] ?? null),
            'wynik_ostatniej_oc' => $this->nullableString($data['wynik_ostatniej_oc'] ?? null),
        ]);

        return $this->vehicleDetailsToDto($details);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function upsertVehicleDetails(int $equipmentId, array $data): ?array
    {
        $existing = $this->vehicleDetailsRepository->findByEquipmentId($equipmentId);
        $payload = [
            'ostatni_przebieg' => $this->toNonNegInt($data['ostatni_przebieg'] ?? 0, 0),
            'ostatni_serwis_olejowy' => $this->nullableString($data['ostatni_serwis_olejowy'] ?? null),
            'ostatnia_awaria' => $this->nullableString($data['ostatnia_awaria'] ?? null),
            'data_ostatniej_oc' => $this->nullableString($data['data_ostatniej_oc'] ?? null),
            'wynik_ostatniej_oc' => $this->nullableString($data['wynik_ostatniej_oc'] ?? null),
        ];

        if ($existing === null) {
            $payload['equipment_id'] = $equipmentId;
            $created = $this->vehicleDetailsRepository->createDetails($payload);

            return $this->vehicleDetailsToDto($created);
        }

        $updated = $this->vehicleDetailsRepository->updateDetails($equipmentId, $payload);

        return $updated !== null ? $this->vehicleDetailsToDto($updated) : null;
    }

    /**
     * Wylicza datę następnego planowanego przeglądu, jeśli nie podana jawno.
     */
    private function computeNextPlanned(?string $lastDone, ?int $intervalDays, ?string $explicitNext): ?string
    {
        if ($explicitNext !== null && $explicitNext !== '') {
            return $explicitNext;
        }
        if ($lastDone === null || $lastDone === '' || $intervalDays === null || $intervalDays <= 0) {
            return null;
        }
        $ts = strtotime($lastDone);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts + $intervalDays * 86400);
    }
    // --- DTO ---

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        $employeeId = $this->nullableInt($row['current_employee_id'] ?? null);
        $employeeName = null;
        if (is_string($row['employee_imie'] ?? null) && is_string($row['employee_nazwisko'] ?? null)) {
            $employeeName = trim($row['employee_imie'] . ' ' . $row['employee_nazwisko']);
        }

        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'kategoria' => is_string($row['kategoria'] ?? null) ? $row['kategoria'] : 'inne',
            'nazwa' => is_string($row['nazwa'] ?? null) ? $row['nazwa'] : '',
            'numer_seryjny' => is_string($row['numer_seryjny'] ?? null) && $row['numer_seryjny'] !== '' ? $row['numer_seryjny'] : null,
            'current_employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'current_terminal_id' => $this->nullableInt($row['current_terminal_id'] ?? null),
            'terminal_nazwa' => is_string($row['terminal_nazwa'] ?? null) && $row['terminal_nazwa'] !== '' ? $row['terminal_nazwa'] : null,
            'ostatni_przebieg' => array_key_exists('ostatni_przebieg', $row) ? $this->nullableInt($row['ostatni_przebieg']) : null,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function vehicleDetailsToDto(array $row): array
    {
        return [
            'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
            'ostatni_przebieg' => $this->toInt($row['ostatni_przebieg'] ?? 0),
            'ostatni_serwis_olejowy' => is_string($row['ostatni_serwis_olejowy'] ?? null) && $row['ostatni_serwis_olejowy'] !== '' ? $row['ostatni_serwis_olejowy'] : null,
            'ostatnia_awaria' => is_string($row['ostatnia_awaria'] ?? null) && $row['ostatnia_awaria'] !== '' ? $row['ostatnia_awaria'] : null,
            'data_ostatniej_oc' => is_string($row['data_ostatniej_oc'] ?? null) && $row['data_ostatniej_oc'] !== '' ? $row['data_ostatniej_oc'] : null,
            'wynik_ostatniej_oc' => is_string($row['wynik_ostatniej_oc'] ?? null) && $row['wynik_ostatniej_oc'] !== '' ? $row['wynik_ostatniej_oc'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function servicePlanToDto(array $row): array
    {
        $nextPlanned = is_string($row['data_nastepnego_planowanego'] ?? null) && $row['data_nastepnego_planowanego'] !== '' ? $row['data_nastepnego_planowanego'] : null;

        $needsService = false;
        if ($nextPlanned !== null) {
            $ts = strtotime($nextPlanned);
            if ($ts !== false && $ts <= time()) {
                $needsService = true;
            }
        }

        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
            'typ_przegladu' => is_string($row['typ_przegladu'] ?? null) ? $row['typ_przegladu'] : '',
            'interwal_km' => $this->nullableInt($row['interwal_km'] ?? null),
            'interwal_dni' => $this->nullableInt($row['interwal_dni'] ?? null),
            'data_ostatniego_wykonania' => is_string($row['data_ostatniego_wykonania'] ?? null) && $row['data_ostatniego_wykonania'] !== '' ? $row['data_ostatniego_wykonania'] : null,
            'data_nastepnego_planowanego' => $nextPlanned,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'needs_service' => $needsService,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function historyToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
            'typ' => is_string($row['typ'] ?? null) ? $row['typ'] : 'inne',
            'opis' => is_string($row['opis'] ?? null) ? $row['opis'] : '',
            'data' => is_string($row['data'] ?? null) ? $row['data'] : null,
            'created_by' => $this->nullableInt($row['created_by'] ?? null),
        ];
    }
    // --- Pomocnicze ---

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    private function toNonNegInt(mixed $value, int $default): int
    {
        $int = $this->toInt($value);
        if ($int < 0) {
            return $default;
        }

        return $int;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value === '1' || strtolower($value) === 'true';
        }
        if (is_int($value)) {
            return $value === 1;
        }

        return $default;
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
            'equipment',
            $resourceId,
        );
    }
}