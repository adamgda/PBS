<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\TerminalRepository;

/**
 * Serwis zarządzania terminalami — sekcja Terminale (Etap 6).
 *
 * Operacje: lista (paginacja + filtry: nazwa, operator, status),
 * tworzenie, edycja, usuwanie.
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (wymagane pola, format e-mail/telefon).
 * - Unikalność nazwy terminala (409 przy konflikcie).
 * - Audit log dla każdej akcji mutującej.
 */
final class TerminalService
{
    private const int MAX_NAME_LENGTH = 255;
    private const int MAX_OPERATOR_LENGTH = 255;
    private const int MAX_PHONE_LENGTH = 20;
    private const int MAX_EMAIL_LENGTH = 255;
    private const int MAX_ADDRESS_LENGTH = 1000;

    public function __construct(
        private readonly TerminalRepository $terminalRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista terminali z paginacją i filtrami.
     *
     * @param array{nazwa?: string, operator?: string, is_active?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->terminalRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->terminalRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły terminala.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $terminal = $this->terminalRepository->findById($id);
        if ($terminal === null) {
            return ['error' => 'Terminal not found', 'code' => 404];
        }

        return $this->toDto($terminal);
    }

    /**
     * Tworzenie terminala.
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
        if ($this->terminalRepository->findByName($nazwa) !== null) {
            return ['error' => 'Terminal with this name already exists', 'code' => 409];
        }

        $adres = is_string($data['adres'] ?? null) ? $data['adres'] : '';
        $operator = is_string($data['operator'] ?? null) ? $data['operator'] : '';

        $terminal = $this->terminalRepository->createTerminal([
            'nazwa' => $nazwa,
            'adres' => $adres,
            'operator' => $operator,
            'telefon_operatora' => $this->nullableString($data['telefon_operatora'] ?? null),
            'email_operatora' => $this->nullableString($data['email_operatora'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        $this->auditLog($request, 'terminal_created', $this->toInt($terminal['id'] ?? 0));

        return $this->toDto($terminal);
    }

    /**
     * Edycja terminala.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $existing = $this->terminalRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Terminal not found', 'code' => 404];
        }

        $validation = $this->validate($data);
        if ($validation !== null) {
            return $validation;
        }

        $nazwa = is_string($data['nazwa'] ?? null) ? $data['nazwa'] : '';
        $duplicate = $this->terminalRepository->findByName($nazwa);
        if ($duplicate !== null && $this->toInt($duplicate['id'] ?? 0) !== $id) {
            return ['error' => 'Terminal with this name already exists', 'code' => 409];
        }

        $adres = is_string($data['adres'] ?? null) ? $data['adres'] : '';
        $operator = is_string($data['operator'] ?? null) ? $data['operator'] : '';

        $updated = $this->terminalRepository->updateTerminal($id, [
            'nazwa' => $nazwa,
            'adres' => $adres,
            'operator' => $operator,
            'telefon_operatora' => $this->nullableString($data['telefon_operatora'] ?? null),
            'email_operatora' => $this->nullableString($data['email_operatora'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($existing['is_active'] ?? true),
        ]);

        $this->auditLog($request, 'terminal_updated', $id);

        if ($updated === null) {
            return ['error' => 'Terminal not found', 'code' => 404];
        }

        return $this->toDto($updated);
    }

    /**
     * Usunięcie terminala.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $existing = $this->terminalRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Terminal not found', 'code' => 404];
        }

        $this->terminalRepository->delete($id);
        $this->auditLog($request, 'terminal_deleted', $id);

        return ['success' => true];
    }

    /**
     * Walidacja danych terminala.
     *
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

        $adres = is_string($data['adres'] ?? null) ? trim($data['adres']) : '';
        if ($adres === '') {
            return ['error' => 'Address is required', 'code' => 422];
        }
        if (mb_strlen($adres) > self::MAX_ADDRESS_LENGTH) {
            return ['error' => 'Address is too long', 'code' => 422];
        }

        $operator = is_string($data['operator'] ?? null) ? trim($data['operator']) : '';
        if ($operator === '') {
            return ['error' => 'Operator is required', 'code' => 422];
        }
        if (mb_strlen($operator) > self::MAX_OPERATOR_LENGTH) {
            return ['error' => 'Operator is too long', 'code' => 422];
        }

        $email = $data['email_operatora'] ?? null;
        if ($email !== null && !is_string($email)) {
            return ['error' => 'Invalid operator email', 'code' => 422];
        }
        if (is_string($email) && trim($email) !== '' && !$this->isValidEmail($email)) {
            return ['error' => 'Invalid operator email', 'code' => 422];
        }
        if (is_string($email) && mb_strlen($email) > self::MAX_EMAIL_LENGTH) {
            return ['error' => 'Operator email is too long', 'code' => 422];
        }

        $phone = $data['telefon_operatora'] ?? null;
        if ($phone !== null && !is_string($phone)) {
            return ['error' => 'Invalid operator phone', 'code' => 422];
        }
        if (is_string($phone) && mb_strlen($phone) > self::MAX_PHONE_LENGTH) {
            return ['error' => 'Operator phone is too long', 'code' => 422];
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

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'terminal',
            $resourceId,
        );
    }

    /**
     * Mapuje wiersz z DB na DTO.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'nazwa' => is_string($row['nazwa'] ?? null) ? $row['nazwa'] : '',
            'adres' => is_string($row['adres'] ?? null) ? $row['adres'] : '',
            'operator' => is_string($row['operator'] ?? null) ? $row['operator'] : '',
            'telefon_operatora' => is_string($row['telefon_operatora'] ?? null) ? $row['telefon_operatora'] : null,
            'email_operatora' => is_string($row['email_operatora'] ?? null) ? $row['email_operatora'] : null,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
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
}