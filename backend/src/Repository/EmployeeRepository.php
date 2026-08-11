<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium pracowników — dostęp do tabeli `employees`.
 *
 * Etap 7 — Sekcja: Pracownicy.
 *
 * Lista zawiera JOIN z `terminals` i `equipment` w celu dostarczenia
 * nazwy terminala i nazwy sprzętu w jednym zapytaniu (minimalizacja round-trips).
 */
class EmployeeRepository extends BaseRepository
{
    protected string $table = 'employees';
    protected string $primaryKey = 'id';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT e.*, t.`nazwa` AS terminal_nazwa, eq.`nazwa` AS sprzet_nazwa
                FROM `employees` e
                LEFT JOIN `terminals` t ON t.`id` = e.`current_terminal_id`
                LEFT JOIN `equipment` eq ON eq.`id` = e.`current_sprzet_id`
                WHERE e.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie pracowników z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{q?: string, imie?: string, nazwisko?: string, terminal_id?: string, sprzet_id?: string, is_active?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'imie', 'nazwisko', 'is_active', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($q !== '') {
            $where[] = '(e.`imie` LIKE :q OR e.`nazwisko` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $imie = is_string($filters['imie'] ?? null) ? trim($filters['imie']) : '';
        if ($imie !== '') {
            $where[] = 'e.`imie` LIKE :imie';
            $params[':imie'] = '%' . $imie . '%';
        }

        $nazwisko = is_string($filters['nazwisko'] ?? null) ? trim($filters['nazwisko']) : '';
        if ($nazwisko !== '') {
            $where[] = 'e.`nazwisko` LIKE :nazwisko';
            $params[':nazwisko'] = '%' . $nazwisko . '%';
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = 'e.`current_terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $sprzetId = is_string($filters['sprzet_id'] ?? null) ? $filters['sprzet_id'] : '';
        if ($sprzetId !== '') {
            $where[] = 'e.`current_sprzet_id` = :sprzet_id';
            $params[':sprzet_id'] = (int) $sprzetId;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = 'e.`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT e.*, t.`nazwa` AS terminal_nazwa, eq.`nazwa` AS sprzet_nazwa
                FROM `employees` e
                LEFT JOIN `terminals` t ON t.`id` = e.`current_terminal_id`
                LEFT JOIN `equipment` eq ON eq.`id` = e.`current_sprzet_id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY e.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{q?: string, imie?: string, nazwisko?: string, terminal_id?: string, sprzet_id?: string, is_active?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($q !== '') {
            $where[] = '(`imie` LIKE :q OR `nazwisko` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $imie = is_string($filters['imie'] ?? null) ? trim($filters['imie']) : '';
        if ($imie !== '') {
            $where[] = '`imie` LIKE :imie';
            $params[':imie'] = '%' . $imie . '%';
        }

        $nazwisko = is_string($filters['nazwisko'] ?? null) ? trim($filters['nazwisko']) : '';
        if ($nazwisko !== '') {
            $where[] = '`nazwisko` LIKE :nazwisko';
            $params[':nazwisko'] = '%' . $nazwisko . '%';
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = '`current_terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $sprzetId = is_string($filters['sprzet_id'] ?? null) ? $filters['sprzet_id'] : '';
        if ($sprzetId !== '') {
            $where[] = '`current_sprzet_id` = :sprzet_id';
            $params[':sprzet_id'] = (int) $sprzetId;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT COUNT(*) FROM `employees`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Wyszukiwanie po e-mailu (do walidacji unikalności).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `email` = :email LIMIT 1";
        $stmt = $this->executeQuery($sql, [':email' => $email]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createEmployee(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateEmployee(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Szybkie przypisanie terminala i/lub sprzętu (PATCH /assignment).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateAssignment(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Anonimizacja danych pracownika zgodnie z RODO (prawo do bycia zapomnianym).
     * Nadpisuje dane osobowe wartościami `[deleted]` i deaktywuje konto.
     */
    public function anonymize(int $id): bool
    {
        $sql = "UPDATE `{$this->table}` SET
                    `imie` = '[deleted]',
                    `nazwisko` = '[deleted]',
                    `telefon` = NULL,
                    `email` = NULL,
                    `current_terminal_id` = NULL,
                    `current_sprzet_id` = NULL,
                    `is_active` = 0
                WHERE `{$this->primaryKey}` = :id";
        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, [':id' => $id]);

        return $stmt->execute();
    }
}