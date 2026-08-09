<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRepository;

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

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
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