<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium terminali — dostęp do tabeli `terminals`.
 *
 * Etap 6 — Sekcja: Terminale.
 */
class TerminalRepository extends BaseRepository
{
    protected string $table = 'terminals';
    protected string $primaryKey = 'id';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * Wyszukiwanie terminali z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{nazwa?: string, operator?: string, is_active?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'nazwa', 'operator', 'is_active', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $nazwa = is_string($filters['nazwa'] ?? null) ? trim($filters['nazwa']) : '';
        if ($nazwa !== '') {
            $where[] = '`nazwa` LIKE :nazwa';
            $params[':nazwa'] = '%' . $nazwa . '%';
        }

        $operator = is_string($filters['operator'] ?? null) ? trim($filters['operator']) : '';
        if ($operator !== '') {
            $where[] = '`operator` LIKE :operator';
            $params[':operator'] = '%' . $operator . '%';
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT `id`, `nazwa`, `adres`, `operator`, `telefon_operatora`, `email_operatora`, `is_active`, `created_at`, `updated_at` FROM `terminals`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{nazwa?: string, operator?: string, is_active?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $nazwa = is_string($filters['nazwa'] ?? null) ? trim($filters['nazwa']) : '';
        if ($nazwa !== '') {
            $where[] = '`nazwa` LIKE :nazwa';
            $params[':nazwa'] = '%' . $nazwa . '%';
        }

        $operator = is_string($filters['operator'] ?? null) ? trim($filters['operator']) : '';
        if ($operator !== '') {
            $where[] = '`operator` LIKE :operator';
            $params[':operator'] = '%' . $operator . '%';
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT COUNT(*) FROM `terminals`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $nazwa): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `nazwa` = :nazwa LIMIT 1";
        $stmt = $this->executeQuery($sql, [':nazwa' => $nazwa]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createTerminal(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateTerminal(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }
}