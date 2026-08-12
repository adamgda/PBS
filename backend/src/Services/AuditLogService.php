<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\AuditLogRepository;

/**
 * Serwis logów audytowych (sekcja Dashboard → Logi audytowe, wyłącznie dla super_admin).
 *
 * Operacje: lista z paginacją/filtrami oraz czyszczenie całego logu.
 * Odczyt wymaga uprawnienia super_admin (wymuszane przez guard w routingu).
 */
final class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista logów z paginacją i filtrami.
     *
     * @param array{action?: string, user_email?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'desc';

        $rows = $this->auditLogRepository->paginate($filters, $perPage, $offset, $sort, $direction);
        $total = $this->auditLogRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Czyści cały log audytowy.
     */
    public function clear(): int
    {
        return $this->auditLogRepository->clear();
    }

    /**
     * Mapuje wiersz z DB na bezpieczny DTO (details jako tablica, user_email z join).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'user_id' => $this->nullableInt($row['user_id'] ?? null),
            'user_email' => $this->nullableString($row['user_email'] ?? null),
            'action' => is_string($row['action'] ?? null) ? $row['action'] : '',
            'resource_type' => $this->nullableString($row['resource_type'] ?? null),
            'resource_id' => $this->nullableInt($row['resource_id'] ?? null),
            'ip_address' => $this->nullableString($row['ip_address'] ?? null),
            'user_agent' => $this->nullableString($row['user_agent'] ?? null),
            'details' => $this->decodeDetails($row['details'] ?? null),
            'created_at' => $this->nullableString($row['created_at'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeDetails(mixed $details): ?array
    {
        if (is_string($details) && $details !== '') {
            $decoded = json_decode($details, true);
            return is_array($decoded) ? $decoded : null;
        }

        return is_array($details) ? $details : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
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
