<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium sprzętu — dostęp do tabeli `equipment`.
 *
 * Etap 8 — Sekcja: Sprzęt.
 *
 * Lista zawiera JOIN z `employees` i `terminals` w celu dostarczenia nazwy
 * pracownika i terminala w jednym zapytaniu (minimalizacja round-trips — anti-N+1).
 */
class EquipmentRepository extends BaseRepository
{
    protected string $table = 'equipment';
    protected string $primaryKey = 'id';

    /**
     * Szczegóły sprzętu z powiązaniami (pracownik, terminal).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT e.*, emp.`imie` AS employee_imie, emp.`nazwisko` AS employee_nazwisko,
                       t.`nazwa` AS terminal_nazwa
                FROM `equipment` e
                LEFT JOIN `employees` emp ON emp.`id` = e.`current_employee_id`
                LEFT JOIN `terminals` t ON t.`id` = e.`current_terminal_id`
                WHERE e.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie sprzętu z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{nazwa?: string, kategoria?: string, numer_seryjny?: string, ostatni_przebieg?: string, employee_id?: string, terminal_id?: string, is_active?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'nazwa', 'kategoria', 'is_active', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $nazwa = is_string($filters['nazwa'] ?? null) ? trim($filters['nazwa']) : '';
        if ($nazwa !== '') {
            $where[] = 'e.`nazwa` LIKE :nazwa';
            $params[':nazwa'] = '%' . $nazwa . '%';
        }

        $kategoria = is_string($filters['kategoria'] ?? null) ? $filters['kategoria'] : '';
        if ($kategoria !== '') {
            $where[] = 'e.`kategoria` = :kategoria';
            $params[':kategoria'] = $kategoria;
        }

        $numerSeryjny = is_string($filters['numer_seryjny'] ?? null) ? trim($filters['numer_seryjny']) : '';
        if ($numerSeryjny !== '') {
            $where[] = 'e.`numer_seryjny` LIKE :numer_seryjny';
            $params[':numer_seryjny'] = '%' . $numerSeryjny . '%';
        }

        $ostatniPrzebieg = is_string($filters['ostatni_przebieg'] ?? null) ? trim($filters['ostatni_przebieg']) : '';
        if ($ostatniPrzebieg !== '') {
            $where[] = 'vd.`ostatni_przebieg` = :ostatni_przebieg';
            $params[':ostatni_przebieg'] = (int) $ostatniPrzebieg;
        }

        $employeeId = is_string($filters['employee_id'] ?? null) ? $filters['employee_id'] : '';
        if ($employeeId !== '') {
            $where[] = 'e.`current_employee_id` = :employee_id';
            $params[':employee_id'] = (int) $employeeId;
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = 'e.`current_terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = 'e.`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT e.*, emp.`imie` AS employee_imie, emp.`nazwisko` AS employee_nazwisko,
                       t.`nazwa` AS terminal_nazwa, vd.`ostatni_przebieg` AS ostatni_przebieg
                FROM `equipment` e
                LEFT JOIN `employees` emp ON emp.`id` = e.`current_employee_id`
                LEFT JOIN `terminals` t ON t.`id` = e.`current_terminal_id`
                LEFT JOIN `vehicle_details` vd ON vd.`equipment_id` = e.`id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY e.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{nazwa?: string, kategoria?: string, numer_seryjny?: string, ostatni_przebieg?: string, employee_id?: string, terminal_id?: string, is_active?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $nazwa = is_string($filters['nazwa'] ?? null) ? trim($filters['nazwa']) : '';
        if ($nazwa !== '') {
            $where[] = 'e.`nazwa` LIKE :nazwa';
            $params[':nazwa'] = '%' . $nazwa . '%';
        }

        $kategoria = is_string($filters['kategoria'] ?? null) ? $filters['kategoria'] : '';
        if ($kategoria !== '') {
            $where[] = 'e.`kategoria` = :kategoria';
            $params[':kategoria'] = $kategoria;
        }

        $numerSeryjny = is_string($filters['numer_seryjny'] ?? null) ? trim($filters['numer_seryjny']) : '';
        if ($numerSeryjny !== '') {
            $where[] = 'e.`numer_seryjny` LIKE :numer_seryjny';
            $params[':numer_seryjny'] = '%' . $numerSeryjny . '%';
        }

        $ostatniPrzebieg = is_string($filters['ostatni_przebieg'] ?? null) ? trim($filters['ostatni_przebieg']) : '';
        if ($ostatniPrzebieg !== '') {
            $where[] = 'vd.`ostatni_przebieg` = :ostatni_przebieg';
            $params[':ostatni_przebieg'] = (int) $ostatniPrzebieg;
        }

        $employeeId = is_string($filters['employee_id'] ?? null) ? $filters['employee_id'] : '';
        if ($employeeId !== '') {
            $where[] = 'e.`current_employee_id` = :employee_id';
            $params[':employee_id'] = (int) $employeeId;
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = 'e.`current_terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $isActive = is_string($filters['is_active'] ?? null) ? $filters['is_active'] : '';
        if ($isActive !== '') {
            $where[] = 'e.`is_active` = :is_active';
            $params[':is_active'] = $isActive === '1';
        }

        $sql = 'SELECT COUNT(*) FROM `equipment` e LEFT JOIN `vehicle_details` vd ON vd.`equipment_id` = e.`id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Wyszukiwanie po nazwie (do walidacji unikalności).
     *
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
    public function createEquipment(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateEquipment(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Szybkie przypisanie pracownika i/lub terminala (PATCH /assignment).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateAssignment(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Publiczny podgląd maszyny po tokenie QR (Etap 20).
     * Zwraca wyłącznie bezpieczne pola — bez danych osobowych (RODO).
     *
     * @return array<string, mixed>|null
     */
    public function findPublicByQrToken(string $token): ?array
    {
        $sql = 'SELECT e.`id`, e.`kategoria`, e.`nazwa`, e.`numer_seryjny`, e.`is_active`
                FROM `equipment` e
                WHERE e.`qr_token` = :token LIMIT 1';
        $stmt = $this->executeQuery($sql, [':token' => $token]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Ustawia / unieważnia token QR maszyny.
     *
     * @return array<string, mixed>|null
     */
    public function setQrToken(int $id, ?string $token): ?array
    {
        $sql = 'UPDATE `equipment` SET `qr_token` = :token WHERE `id` = :id';
        $this->executeQuery($sql, [':token' => $token, ':id' => $id]);

        return $this->findById($id);
    }

}