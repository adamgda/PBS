<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRateRepository;
use App\Repository\EmployeeRepository;
use App\Repository\EmployeeVacationRepository;
use App\Repository\OrderRepository;

/**
 * Serwis zarządzania pracownikami — sekcja Pracownicy (Etap 7).
 *
 * Operacje: lista (paginacja + filtry: imię, nazwisko, terminal, sprzęt, status),
 * tworzenie, edycja, usuwanie (anonimizacja RODO), szybkie przypisanie
 * terminala/sprzętu, zarządzanie dokumentami (certyfikaty/uprawnienia)
 * wraz z uploadem plików.
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (wymagane pola, format e-mail).
 * - Unikalność e-maila pracownika (409 przy konflikcie).
 * - Anonimizacja danych osobowych przy usuwaniu (RODO — prawo do bycia zapomnianym).
 * - Audit log dla każdej akcji mutującej.
 * - Detekcja wygaśnięcia dokumentów (30 dni przed = alert).
 * - Pracownicy są zasobem (jak terminale/sprzęt) — nie otrzymują konta użytkownika
 *   ani loginu do aplikacji. Adres e-mail jest polem opcjonalnym (dane kontaktowe).
 */
final class EmployeeService
{
    private const int MAX_NAME_LENGTH = 100;
    private const int MAX_PHONE_LENGTH = 20;
    private const int MAX_EMAIL_LENGTH = 255;
    private const int MAX_DOC_NAME_LENGTH = 255;
    private const int MAX_DOC_NUMBER_LENGTH = 100;
    private const int EXPIRY_WARNING_DAYS = 30;

    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EmployeeDocumentRepository $documentRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly EmployeeRateRepository $rateRepository,
        private readonly EmployeeVacationRepository $vacationRepository,
        private readonly OrderRepository $orderRepository,
    ) {}

    /**
     * Lista pracowników z paginacją i filtrami.
     *
     * @param array{imie?: string, nazwisko?: string, terminal_id?: string, sprzet_id?: string, is_active?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->employeeRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->employeeRepository->countSearch($filters);

        $dtos = array_map(fn (array $row): array => $this->toDto($row), $rows);
        $dtos = $this->enrichWithSettlementColumns($dtos);

        return [
            'data' => $dtos,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Wzbogaca listę DTO pracowników o: aktualną stawkę, sumę godzin w miesiącu,
     * wyliczone wynagrodzenie (godziny × stawka), rolę „dziś" oraz status urlopu.
     *
     * @param array<int, array<string, mixed>> $dtos
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithSettlementColumns(array $dtos): array
    {
        if ($dtos === []) {
            return $dtos;
        }

        $ids = array_map(fn (array $d): int => $this->toInt($d['id'] ?? 0), $dtos);
        $today = date('Y-m-d');
        $month = date('Y-m');

        $rates = $this->rateRepository->findCurrentRatesForEmployees($ids, $today);
        $hours = $this->orderRepository->hoursPerEmployeeInMonth($month, $ids);
        $roles = $this->orderRepository->currentRolesByDate($today);
        $onLeave = $this->vacationRepository->findOnLeaveEmployeeIds($ids, $today);

        $documents = $this->documentRepository->findForEmployeeIds($ids);
        if (!is_array($documents)) {
            $documents = [];
        }
        $uprawnienia = $this->computeUprawnieniaSummary($documents, $today);

        foreach ($dtos as &$dto) {
            $id = $this->toInt($dto['id'] ?? 0);
            $stawka = $rates[$id] ?? 0.0;
            $godziny = $hours[$id] ?? 0.0;
            $dto['stawka_godzinowa'] = $stawka;
            $dto['godziny_mc'] = $godziny;
            $dto['wynagrodzenie'] = round($godziny * $stawka, 2);
            $dto['rola_dzis'] = array_key_exists($id, $roles) ? $roles[$id] : null;
            $dto['on_leave'] = array_key_exists($id, $onLeave);
            $dto['uprawnienie'] = $uprawnienia[$id] ?? ['nazwa' => null, 'data_waznosci' => null, 'dni' => null, 'status' => 'none'];
        }

        return $dtos;
    }

    /**
     * Podsumowanie „najpilniejszego" dokumentu dla każdego pracownika (kolumna Uprawnienia).
     *
     * Priorytet: wygasły > wygasający (≤30 dni) > ważny. Gdy brak dokumentów — status 'none'.
     *
     * @param array<int, array<string, mixed>> $documents
     * @param string $today data w formacie YYYY-MM-DD
     * @return array<int, array{nazwa: string|null, data_waznosci: string|null, dni: int|null, status: string}>
     */
    private function computeUprawnieniaSummary(array $documents, string $today): array
    {
        $now = strtotime($today);

        $grouped = [];
        foreach ($documents as $doc) {
            $empId = $this->toInt($doc['employee_id'] ?? 0);
            $grouped[$empId][] = $doc;
        }

        $result = [];
        foreach ($grouped as $empId => $docs) {
            $expired = [];
            $active = [];
            $hasUnbounded = false;
            $unboundedName = null;

            foreach ($docs as $doc) {
                $expiry = is_string($doc['data_waznosci'] ?? null) && $doc['data_waznosci'] !== '' ? $doc['data_waznosci'] : null;
                $nazwa = is_string($doc['nazwa'] ?? null) && $doc['nazwa'] !== '' ? $doc['nazwa'] : null;

                if ($expiry === null) {
                    $hasUnbounded = true;
                    if ($unboundedName === null) {
                        $unboundedName = $nazwa;
                    }
                    continue;
                }

                $ts = strtotime($expiry);
                if ($ts === false) {
                    continue;
                }

                if ($ts < $now) {
                    $expired[] = ['nazwa' => $nazwa, 'data_waznosci' => $expiry, 'ts' => $ts];
                } else {
                    $active[] = ['nazwa' => $nazwa, 'data_waznosci' => $expiry, 'ts' => $ts];
                }
            }

            if ($expired !== []) {
                // Najświeżej wygasły (największa data ważności) — najistotniejszy komunikat.
                usort($expired, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
                $worst = $expired[0];
                $result[$empId] = [
                    'nazwa' => $worst['nazwa'],
                    'data_waznosci' => $worst['data_waznosci'],
                    'dni' => (int) floor(($worst['ts'] - $now) / 86400),
                    'status' => 'expired',
                ];
            } elseif ($active !== []) {
                // Najbliższa ważność.
                usort($active, static fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);
                $nearest = $active[0];
                $dni = (int) floor(($nearest['ts'] - $now) / 86400);
                $result[$empId] = [
                    'nazwa' => $nearest['nazwa'],
                    'data_waznosci' => $nearest['data_waznosci'],
                    'dni' => $dni,
                    'status' => $dni <= self::EXPIRY_WARNING_DAYS ? 'expiring' : 'ok',
                ];
            } elseif ($hasUnbounded) {
                $result[$empId] = [
                    'nazwa' => $unboundedName,
                    'data_waznosci' => null,
                    'dni' => null,
                    'status' => 'ok',
                ];
            } else {
                $result[$empId] = ['nazwa' => null, 'data_waznosci' => null, 'dni' => null, 'status' => 'none'];
            }
        }

        return $result;
    }

    /**
     * Szczegóły pracownika (z dokumentami).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $employee = $this->employeeRepository->findById($id);
        if ($employee === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $dto = $this->toDto($employee);
        $documents = $this->documentRepository->findByEmployeeId($id);
        $dto['documents'] = array_map(fn (array $d): array => $this->documentToDto($d), $documents);

        return $dto;
    }

    /**
     * Tworzenie pracownika.
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

        $email = $this->nullableString($data['email'] ?? null);
        if ($email !== null && $this->employeeRepository->findByEmail($email) !== null) {
            return ['error' => 'Employee with this email already exists', 'code' => 409];
        }

        $employee = $this->employeeRepository->createEmployee([
            'imie' => is_string($data['imie'] ?? null) ? trim($data['imie']) : '',
            'nazwisko' => is_string($data['nazwisko'] ?? null) ? trim($data['nazwisko']) : '',
            'telefon' => $this->nullableString($data['telefon'] ?? null),
            'email' => $email,
            'current_terminal_id' => $this->nullableInt($data['current_terminal_id'] ?? null),
            'current_sprzet_id' => $this->nullableInt($data['current_sprzet_id'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        $this->auditLog($request, 'employee_created', $this->toInt($employee['id'] ?? 0));

        // Pracownicy są zasobem (jak terminale czy sprzęt) — nie otrzymują konta
        // użytkownika ani loginu do aplikacji. Adres e-mail jest wyłącznie danymi
        // kontaktowymi (pole opcjonalne) i nie generuje konta ani wysyłki hasła.
        return $this->toDto($employee);
    }

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'employee',
            $resourceId,
        );
    }

    /**
     * Edycja pracownika.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $existing = $this->employeeRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $validation = $this->validate($data);
        if ($validation !== null) {
            return $validation;
        }

        $email = $this->nullableString($data['email'] ?? null);
        if ($email !== null) {
            $duplicate = $this->employeeRepository->findByEmail($email);
            if ($duplicate !== null && $this->toInt($duplicate['id'] ?? 0) !== $id) {
                return ['error' => 'Employee with this email already exists', 'code' => 409];
            }
        }

        $updated = $this->employeeRepository->updateEmployee($id, [
            'imie' => is_string($data['imie'] ?? null) ? trim($data['imie']) : '',
            'nazwisko' => is_string($data['nazwisko'] ?? null) ? trim($data['nazwisko']) : '',
            'telefon' => $this->nullableString($data['telefon'] ?? null),
            'email' => $email,
            'current_terminal_id' => $this->nullableInt($data['current_terminal_id'] ?? null),
            'current_sprzet_id' => $this->nullableInt($data['current_sprzet_id'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($existing['is_active'] ?? true),
        ]);

        $this->auditLog($request, 'employee_updated', $id);

        if ($updated === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        return $this->toDto($updated);
    }

    /**
     * Usunięcie pracownika — fizyczne usunięcie z bazy.
     *
     * Powiązania historyczne (order_employees) zostają zachowane dzięki
     * FK `ON DELETE SET NULL` (employee_id = NULL → frontend wyświetla
     * „Pracownik usunięty"). Dokumenty pracownika (employee_documents) usuwają
     * się kaskadowo (ON DELETE CASCADE), a bieżące przypisanie sprzętu
     * zostaje wyczyszczone (equipment.current_employee_id → NULL).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $existing = $this->employeeRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        // Fizyczne usunięcie wiersza pracownika.
        $this->employeeRepository->delete($id);
        $this->auditLog($request, 'employee_deleted', $id);

        return ['success' => true];
    }

    /**
     * Szybkie przypisanie terminala i/lub sprzętu (PATCH /assignment).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function assign(int $id, array $data, Request $request): array
    {
        $existing = $this->employeeRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $update = [];
        if (array_key_exists('current_terminal_id', $data)) {
            $update['current_terminal_id'] = $this->nullableInt($data['current_terminal_id']);
        }
        if (array_key_exists('current_sprzet_id', $data)) {
            $update['current_sprzet_id'] = $this->nullableInt($data['current_sprzet_id']);
        }
        if ($update === []) {
            return ['error' => 'No assignment fields provided', 'code' => 422];
        }

        $updated = $this->employeeRepository->updateAssignment($id, $update);
        $this->auditLog($request, 'employee_assignment_changed', $id);

        if ($updated === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        return $this->toDto($updated);
    }

    // --- Dokumenty pracownika (certyfikaty i uprawnienia) ---

    /**
     * Lista dokumentów pracownika.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function listDocuments(int $employeeId): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }
        $documents = $this->documentRepository->findByEmployeeId($employeeId);

        return ['data' => array_map(fn (array $d): array => $this->documentToDto($d), $documents)];
    }

    /**
     * Dodanie dokumentu pracownika (z opcjonalnym uploadem pliku).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $file element $_FILES lub null
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createDocument(int $employeeId, array $data, ?array $file, Request $request): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $validation = $this->validateDocument($data);
        if ($validation !== null) {
            return $validation;
        }

        $plik = null;
        if ($file !== null) {
            $fileErrors = $this->fileUploadService->validate($file);
            if ($fileErrors !== []) {
                return ['error' => implode(' ', $fileErrors), 'code' => 422];
            }
            try {
                $plik = $this->fileUploadService->store($file);
            } catch (\RuntimeException $e) {
                return ['error' => $e->getMessage(), 'code' => 422];
            }
        }

        $document = $this->documentRepository->createDocument([
            'employee_id' => $employeeId,
            'nazwa' => is_string($data['nazwa'] ?? null) ? trim($data['nazwa']) : '',
            'numer_dokumentu' => $this->nullableString($data['numer_dokumentu'] ?? null),
            'data_wydania' => $this->nullableString($data['data_wydania'] ?? null),
            'data_waznosci' => $this->nullableString($data['data_waznosci'] ?? null),
            'plik' => $plik,
        ]);

        $this->auditLog($request, 'employee_document_created', $this->toInt($document['id'] ?? 0));

        return $this->documentToDto($document);
    }

    /**
     * Edycja dokumentu (PUT /api/v1/documents/{id}).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateDocument(int $documentId, array $data, Request $request): array
    {
        $existing = $this->documentRepository->findById($documentId);
        if ($existing === null) {
            return ['error' => 'Document not found', 'code' => 404];
        }

        $validation = $this->validateDocument($data, true);
        if ($validation !== null) {
            return $validation;
        }

        $update = [];
        if (array_key_exists('nazwa', $data)) {
            $update['nazwa'] = is_string($data['nazwa']) ? trim($data['nazwa']) : '';
        }
        if (array_key_exists('numer_dokumentu', $data)) {
            $update['numer_dokumentu'] = $this->nullableString($data['numer_dokumentu']);
        }
        if (array_key_exists('data_wydania', $data)) {
            $update['data_wydania'] = $this->nullableString($data['data_wydania']);
        }
        if (array_key_exists('data_waznosci', $data)) {
            $update['data_waznosci'] = $this->nullableString($data['data_waznosci']);
        }

        if ($update === []) {
            return ['error' => 'No fields provided', 'code' => 422];
        }

        $updated = $this->documentRepository->updateDocument($documentId, $update);
        $this->auditLog($request, 'employee_document_updated', $documentId);

        if ($updated === null) {
            return ['error' => 'Document not found', 'code' => 404];
        }

        return $this->documentToDto($updated);
    }

    /**
     * Usunięcie dokumentu (DELETE /api/v1/documents/{id}).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function deleteDocument(int $documentId, Request $request): array
    {
        $existing = $this->documentRepository->findById($documentId);
        if ($existing === null) {
            return ['error' => 'Document not found', 'code' => 404];
        }

        $this->documentRepository->delete($documentId);
        $this->auditLog($request, 'employee_document_deleted', $documentId);

        return ['success' => true];
    }
    // --- Stawki godzinowe (Etap 7a) ---

    /**
     * GET /api/v1/employees/{id}/rates — historia stawek pracownika.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function listRates(int $employeeId): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $rates = $this->rateRepository->findByEmployeeId($employeeId);

        return ['data' => array_map(fn (array $r): array => $this->rateToDto($r), $rates)];
    }

    /**
     * POST /api/v1/employees/{id}/rates — nowa stawka z datą wejścia w życie.
     * Zamyka poprzedni rekord (data_do = dzień przed nową data_od).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createRate(int $employeeId, array $data, Request $request): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $stawka = $this->toFloat($data['stawka_godzinowa'] ?? null);
        if ($stawka <= 0) {
            return ['error' => 'Stawka godzinowa must be a positive number', 'code' => 422];
        }

        $dataOd = is_string($data['data_od'] ?? null) ? trim($data['data_od']) : '';
        if ($dataOd === '' || strtotime($dataOd) === false) {
            return ['error' => 'Valid data_od is required', 'code' => 422];
        }

        $this->rateRepository->closePreviousRate($employeeId, $dataOd);

        $created = $this->rateRepository->createRate([
            'employee_id' => $employeeId,
            'stawka_godzinowa' => $stawka,
            'data_od' => $dataOd,
            'data_do' => null,
        ]);

        $this->auditLog($request, 'employee_rate_created', $employeeId);

        return $this->rateToDto($created);
    }

    // --- Urlopy (Etap 7a) ---

    /**
     * GET /api/v1/employees/{id}/vacations — lista urlopów pracownika.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function listVacations(int $employeeId): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $vacations = $this->vacationRepository->findByEmployeeId($employeeId);

        return ['data' => array_map(fn (array $v): array => $this->vacationToDto($v), $vacations)];
    }

    /**
     * POST /api/v1/employees/{id}/vacations — dodanie urlopu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createVacation(int $employeeId, array $data, Request $request): array
    {
        if ($this->employeeRepository->findById($employeeId) === null) {
            return ['error' => 'Employee not found', 'code' => 404];
        }

        $dataOd = is_string($data['data_od'] ?? null) ? trim($data['data_od']) : '';
        $dataDo = is_string($data['data_do'] ?? null) ? trim($data['data_do']) : '';
        if ($dataOd === '' || $dataDo === '' || strtotime($dataOd) === false || strtotime($dataDo) === false) {
            return ['error' => 'Valid data_od and data_do are required', 'code' => 422];
        }
        if (strtotime($dataDo) < strtotime($dataOd)) {
            return ['error' => 'data_do must not be before data_od', 'code' => 422];
        }

        $typ = is_string($data['typ'] ?? null) ? $data['typ'] : 'wypoczynkowy';
        if (!in_array($typ, ['wypoczynkowy', 'na_zadanie', 'L4'], true)) {
            return ['error' => 'Invalid vacation type', 'code' => 422];
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : 'oczekujacy';
        if (!in_array($status, ['oczekujacy', 'zatwierdzony', 'odrzucony', 'zrealizowany'], true)) {
            return ['error' => 'Invalid vacation status', 'code' => 422];
        }

        $created = $this->vacationRepository->createVacation([
            'employee_id' => $employeeId,
            'data_od' => $dataOd,
            'data_do' => $dataDo,
            'typ' => $typ,
            'status' => $status,
        ]);

        $this->auditLog($request, 'employee_vacation_created', $employeeId);

        return $this->vacationToDto($created);
    }

    /**
     * PATCH /api/v1/vacations/{id}/status — zmiana statusu urlopu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updateVacationStatus(int $vacationId, array $data, Request $request): array
    {
        $vacation = $this->vacationRepository->findById($vacationId);
        if ($vacation === null) {
            return ['error' => 'Vacation not found', 'code' => 404];
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : '';
        if (!in_array($status, ['oczekujacy', 'zatwierdzony', 'odrzucony', 'zrealizowany'], true)) {
            return ['error' => 'Invalid vacation status', 'code' => 422];
        }

        $updated = $this->vacationRepository->updateVacation($vacationId, ['status' => $status]);
        $this->auditLog($request, 'employee_vacation_status_updated', $vacationId);

        return $updated !== null ? $this->vacationToDto($updated) : ['success' => true];
    }

    /**
     * DELETE /api/v1/vacations/{id} — usunięcie urlopu.
     *
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function deleteVacation(int $vacationId, Request $request): array
    {
        if ($this->vacationRepository->findById($vacationId) === null) {
            return ['error' => 'Vacation not found', 'code' => 404];
        }

        $this->vacationRepository->delete($vacationId);
        $this->auditLog($request, 'employee_vacation_deleted', $vacationId);

        return ['success' => true];
    }
    // --- Rozliczenia i podsumowania (Etap 7a) ---

    /**
     * GET /api/v1/employees/settlement?month=&period=all|1-15|15-23
     * Rozliczenie per pracownik (godziny × stawka po dacie zlecenia).
     *
     * @return array<string, mixed>
     */
    public function settlement(string $month, string $period): array
    {
        $month = $this->normalizeMonth($month);
        $period = $this->normalizePeriod($period);

        $detail = $this->orderRepository->settlementDetail($month, $period);
        $employeeIds = array_values(array_unique(array_map(fn (array $r): int => $this->toInt($r['employee_id'] ?? 0), $detail)));
        $allRates = $this->rateRepository->findAllByEmployeeIds($employeeIds);

        $per = [];
        foreach ($detail as $row) {
            $empId = $this->toInt($row['employee_id'] ?? 0);
            $date = is_string($row['data_zlecenia'] ?? null) ? $row['data_zlecenia'] : $month . '-01';
            $godziny = $this->toFloat($row['godziny'] ?? null);
            $day = (int) substr($date, 8, 2);

            if (!array_key_exists($empId, $per)) {
                $per[$empId] = [
                    'employee_id' => $empId,
                    'imie' => null,
                    'nazwisko' => null,
                    'godziny_1_15' => 0.0,
                    'godziny_15_23' => 0.0,
                    'godziny_total' => 0.0,
                    'wynagrodzenie' => 0.0,
                ];
            }

            $stawka = $this->rateAt($allRates[$empId] ?? [], $date);
            $wage = round($godziny * $stawka, 2);

            if ($day < 15) {
                $per[$empId]['godziny_1_15'] += $godziny;
            } else {
                $per[$empId]['godziny_15_23'] += $godziny;
            }
            $per[$empId]['godziny_total'] += $godziny;
            $per[$empId]['wynagrodzenie'] += $wage;
        }

        $ids = array_keys($per);
        foreach ($ids as $id) {
            $emp = $this->employeeRepository->findById($id);
            if ($emp !== null) {
                $per[$id]['imie'] = is_string($emp['imie'] ?? null) ? $emp['imie'] : null;
                $per[$id]['nazwisko'] = is_string($emp['nazwisko'] ?? null) ? $emp['nazwisko'] : null;
            }
            $per[$id]['wynagrodzenie'] = round($per[$id]['wynagrodzenie'], 2);
        }

        $rows = array_values($per);

        return [
            'month' => $month,
            'period' => $period,
            'data' => $rows,
            'total_godziny' => round(array_sum(array_map(fn (array $r): float => (float) $r['godziny_total'], $rows)), 2),
            'total_wynagrodzenie' => round(array_sum(array_map(fn (array $r): float => (float) $r['wynagrodzenie'], $rows)), 2),
        ];
    }

    /**
     * GET /api/v1/employees/settlement/by-port?month=&period=
     * Suma godzin i wynagrodzeń per port/terminal + wiersz „Razem".
     *
     * @return array<string, mixed>
     */
    public function settlementByPort(string $month, string $period): array
    {
        $month = $this->normalizeMonth($month);
        $period = $this->normalizePeriod($period);

        $detail = $this->orderRepository->settlementDetail($month, $period);
        $employeeIds = array_values(array_unique(array_map(fn (array $r): int => $this->toInt($r['employee_id'] ?? 0), $detail)));
        $allRates = $this->rateRepository->findAllByEmployeeIds($employeeIds);

        $ports = [];
        $portEmployees = [];
        $totalGodziny = 0.0;
        $totalWynagrodzenie = 0.0;

        foreach ($detail as $row) {
            $termId = $this->toInt($row['terminal_id'] ?? 0);
            $empId = $this->toInt($row['employee_id'] ?? 0);
            $date = is_string($row['data_zlecenia'] ?? null) ? $row['data_zlecenia'] : $month . '-01';
            $godziny = $this->toFloat($row['godziny'] ?? null);
            $stawka = $this->rateAt($allRates[$empId] ?? [], $date);
            $wage = round($godziny * $stawka, 2);

            if (!array_key_exists($termId, $ports)) {
                $ports[$termId] = [
                    'terminal_id' => $termId,
                    'terminal_nazwa' => $this->nullableString($row['terminal_nazwa'] ?? null),
                    'liczba_pracownikow' => 0,
                    'suma_godzin' => 0.0,
                    'suma_wynagrodzen' => 0.0,
                ];
                $portEmployees[$termId] = [];
            }
            $ports[$termId]['suma_godzin'] += $godziny;
            $ports[$termId]['suma_wynagrodzen'] += $wage;
            $portEmployees[$termId][$empId] = true;

            $totalGodziny += $godziny;
            $totalWynagrodzenie += $wage;
        }

        foreach (array_keys($ports) as $termId) {
            $ports[$termId]['liczba_pracownikow'] = count($portEmployees[$termId] ?? []);
            $ports[$termId]['suma_godzin'] = round($ports[$termId]['suma_godzin'], 2);
            $ports[$termId]['suma_wynagrodzen'] = round($ports[$termId]['suma_wynagrodzen'], 2);
        }

        $rows = array_values($ports);
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['terminal_nazwa'], (string) $b['terminal_nazwa']));

        $rows[] = [
            'terminal_id' => null,
            'terminal_nazwa' => 'Razem (wszystkie porty)',
            'liczba_pracownikow' => count($employeeIds),
            'suma_godzin' => round($totalGodziny, 2),
            'suma_wynagrodzen' => round($totalWynagrodzenie, 2),
        ];

        return [
            'month' => $month,
            'period' => $period,
            'data' => $rows,
        ];
    }

    /**
     * GET /api/v1/employees/summary?month=
     * Suma godzin (mc), suma wynagrodzeń z podziałem 1–15 / 15–23, licznik na urlopie.
     *
     * @return array<string, mixed>
     */
    public function summary(string $month): array
    {
        $month = $this->normalizeMonth($month);
        $today = date('Y-m-d');

        $allEmployees = $this->employeeRepository->findAll([], 1000, 0);
        $allIds = array_map(fn (array $e): int => $this->toInt($e['id'] ?? 0), $allEmployees);
        $onLeaveCount = $this->vacationRepository->countOnLeave($allIds, $today);

        // Agregaty sumujemy z jednego przebiegu settlementDetail + stawki.
        // Wcześniej wywoływano tu $this->settlement() (który odpytuje settlementDetail,
        // pobiera stawki i robi N+1 findById) ORAZ osobno ponownie settlementDetail —
        // przy zdalnej bazie podwajało to czas wykonania i powodowało timeout (abort)
        // żądania po stronie frontendu (interceptor httpTimeout). Ten sam wynik, 1 przebieg.
        $godzinyTotal = 0.0;
        $wynagrodzenieTotal = 0.0;
        $godziny1_15 = 0.0;
        $godziny15_23 = 0.0;
        $wynagrodzenie1_15 = 0.0;
        $wynagrodzenie15_23 = 0.0;

        $detail = $this->orderRepository->settlementDetail($month, 'all');
        $employeeIds = array_values(array_unique(array_map(fn (array $r): int => $this->toInt($r['employee_id'] ?? 0), $detail)));
        $allRates = $this->rateRepository->findAllByEmployeeIds($employeeIds);

        foreach ($detail as $row) {
            $date = is_string($row['data_zlecenia'] ?? null) ? $row['data_zlecenia'] : $month . '-01';
            $day = $this->toInt(substr($date, 8, 2));
            $godziny = $this->toFloat($row['godziny'] ?? null);
            $empId = $this->toInt($row['employee_id'] ?? 0);
            $stawka = $this->rateAt($allRates[$empId] ?? [], $date);
            $wage = round($godziny * $stawka, 2);

            $godzinyTotal += $godziny;
            $wynagrodzenieTotal += $wage;

            if ($day < 15) {
                $godziny1_15 += $godziny;
                $wynagrodzenie1_15 += $wage;
            } else {
                $godziny15_23 += $godziny;
                $wynagrodzenie15_23 += $wage;
            }
        }

        return [
            'month' => $month,
            'godziny_total' => round($godzinyTotal, 2),
            'wynagrodzenie_total' => round($wynagrodzenieTotal, 2),
            'godziny_1_15' => round($godziny1_15, 2),
            'godziny_15_23' => round($godziny15_23, 2),
            'wynagrodzenie_1_15' => round($wynagrodzenie1_15, 2),
            'wynagrodzenie_15_23' => round($wynagrodzenie15_23, 2),
            'na_urlopie' => $onLeaveCount,
        ];
    }

    // --- Pomocnicze rozliczeń ---

    /**
     * Wybiera stawkę obowiązującą w danej dacie z listy (posortowanej data_od DESC).
     *
     * @param array<int, array<string, mixed>> $rates
     */
    private function rateAt(array $rates, string $date): float
    {
        foreach ($rates as $rate) {
            $dataOd = is_string($rate['data_od'] ?? null) ? $rate['data_od'] : null;
            if ($dataOd !== null && $dataOd <= $date) {
                return $this->toFloat($rate['stawka_godzinowa'] ?? null);
            }
        }

        return 0.0;
    }

    private function normalizeMonth(string $month): string
    {
        $trimmed = trim($month);
        if ($trimmed !== '' && strtotime($trimmed . '-01') !== false) {
            return $trimmed;
        }

        return date('Y-m');
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['all', '1-15', '15-23'], true) ? $period : 'all';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rateToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'employee_id' => $this->toInt($row['employee_id'] ?? 0),
            'stawka_godzinowa' => $this->toFloat($row['stawka_godzinowa'] ?? null),
            'data_od' => is_string($row['data_od'] ?? null) ? $row['data_od'] : null,
            'data_do' => is_string($row['data_do'] ?? null) ? $row['data_do'] : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function vacationToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'employee_id' => $this->toInt($row['employee_id'] ?? 0),
            'data_od' => is_string($row['data_od'] ?? null) ? $row['data_od'] : null,
            'data_do' => is_string($row['data_do'] ?? null) ? $row['data_do'] : null,
            'typ' => is_string($row['typ'] ?? null) ? $row['typ'] : 'wypoczynkowy',
            'status' => is_string($row['status'] ?? null) ? $row['status'] : 'oczekujacy',
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
    // --- Walidacja ---

    /**
     * Walidacja danych pracownika.
     *
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validate(array $data): ?array
    {
        $imie = is_string($data['imie'] ?? null) ? trim($data['imie']) : '';
        if ($imie === '') {
            return ['error' => 'First name is required', 'code' => 422];
        }
        if (mb_strlen($imie) > self::MAX_NAME_LENGTH) {
            return ['error' => 'First name is too long', 'code' => 422];
        }

        $nazwisko = is_string($data['nazwisko'] ?? null) ? trim($data['nazwisko']) : '';
        if ($nazwisko === '') {
            return ['error' => 'Last name is required', 'code' => 422];
        }
        if (mb_strlen($nazwisko) > self::MAX_NAME_LENGTH) {
            return ['error' => 'Last name is too long', 'code' => 422];
        }

        $email = $data['email'] ?? null;
        if ($email !== null && !is_string($email)) {
            return ['error' => 'Invalid email', 'code' => 422];
        }
        if (is_string($email) && trim($email) !== '' && !$this->isValidEmail($email)) {
            return ['error' => 'Invalid email', 'code' => 422];
        }
        if (is_string($email) && mb_strlen($email) > self::MAX_EMAIL_LENGTH) {
            return ['error' => 'Email is too long', 'code' => 422];
        }

        $phone = $data['telefon'] ?? null;
        if ($phone !== null && !is_string($phone)) {
            return ['error' => 'Invalid phone', 'code' => 422];
        }
        if (is_string($phone) && mb_strlen($phone) > self::MAX_PHONE_LENGTH) {
            return ['error' => 'Phone is too long', 'code' => 422];
        }

        return null;
    }

    /**
     * Walidacja danych dokumentu.
     *
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateDocument(array $data, bool $partial = false): ?array
    {
        $nazwa = $data['nazwa'] ?? null;
        if (!$partial || array_key_exists('nazwa', $data)) {
            if (!is_string($nazwa) || trim($nazwa) === '') {
                return ['error' => 'Document name is required', 'code' => 422];
            }
            if (mb_strlen($nazwa) > self::MAX_DOC_NAME_LENGTH) {
                return ['error' => 'Document name is too long', 'code' => 422];
            }
        }

        $numer = $data['numer_dokumentu'] ?? null;
        if ($numer !== null && !is_string($numer)) {
            return ['error' => 'Invalid document number', 'code' => 422];
        }
        if (is_string($numer) && mb_strlen($numer) > self::MAX_DOC_NUMBER_LENGTH) {
            return ['error' => 'Document number is too long', 'code' => 422];
        }

        foreach (['data_wydania', 'data_waznosci'] as $dateField) {
            $value = $data[$dateField] ?? null;
            if (is_string($value) && $value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return ['error' => 'Invalid date format (YYYY-MM-DD required)', 'code' => 422];
            }
        }

        return null;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

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
        if ($value === null) {
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

    /**
     * Mapuje wiersz z DB na DTO pracownika.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'imie' => is_string($row['imie'] ?? null) ? $row['imie'] : '',
            'nazwisko' => is_string($row['nazwisko'] ?? null) ? $row['nazwisko'] : '',
            'telefon' => is_string($row['telefon'] ?? null) ? $row['telefon'] : null,
            'email' => is_string($row['email'] ?? null) ? $row['email'] : null,
            'current_terminal_id' => $this->nullableInt($row['current_terminal_id'] ?? null),
            'terminal_nazwa' => is_string($row['terminal_nazwa'] ?? null) ? $row['terminal_nazwa'] : null,
            'current_sprzet_id' => $this->nullableInt($row['current_sprzet_id'] ?? null),
            'sprzet_nazwa' => is_string($row['sprzet_nazwa'] ?? null) ? $row['sprzet_nazwa'] : null,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * Mapuje wiersz z DB na DTO dokumentu z detekcją wygaśnięcia.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function documentToDto(array $row): array
    {
        $expiry = is_string($row['data_waznosci'] ?? null) && $row['data_waznosci'] !== '' ? $row['data_waznosci'] : null;

        $isExpired = false;
        $isExpiringSoon = false;
        if ($expiry !== null) {
            $expiryDate = strtotime($expiry);
            if ($expiryDate !== false) {
                $now = time();
                if ($expiryDate < $now) {
                    $isExpired = true;
                } elseif ($expiryDate < $now + self::EXPIRY_WARNING_DAYS * 86400) {
                    $isExpiringSoon = true;
                }
            }
        }

        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'employee_id' => $this->toInt($row['employee_id'] ?? 0),
            'nazwa' => is_string($row['nazwa'] ?? null) ? $row['nazwa'] : '',
            'numer_dokumentu' => is_string($row['numer_dokumentu'] ?? null) ? $row['numer_dokumentu'] : null,
            'data_wydania' => is_string($row['data_wydania'] ?? null) && $row['data_wydania'] !== '' ? $row['data_wydania'] : null,
            'data_waznosci' => $expiry,
            'plik' => is_string($row['plik'] ?? null) && $row['plik'] !== '' ? $row['plik'] : null,
            'is_expired' => $isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }
}