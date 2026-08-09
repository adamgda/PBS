<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium zleceń — dostęp do tabel `orders`, `order_employees`, `order_equipment`.
 *
 * Etap 9 — Sekcja: Harmonogram / Zlecenia.
 *
 * Lista zawiera JOIN z `terminals` w celu dostarczenia nazwy terminala w jednym
 * zapytaniu (minimalizacja round-trips — anti-N+1). Przypisani pracownicy i sprzęt
 * pobierani są osobnymi zapytaniami (relacja N:M).
 */
class OrderRepository extends BaseRepository
{
    protected string $table = 'orders';
    protected string $primaryKey = 'id';

    /**
     * Szczegóły zlecenia z powiązaniem (terminal).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT o.*, t.`nazwa` AS terminal_nazwa
                FROM `orders` o
                LEFT JOIN `terminals` t ON t.`id` = o.`terminal_id`
                WHERE o.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie zleceń z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{numer?: string, klient?: string, terminal_id?: string, status?: string, date_from?: string, date_to?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'numer_zlecenia', 'klient_nazwa', 'data_rozpoczecia', 'data_zakonczenia', 'wartosc_pln', 'status', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $numer = is_string($filters['numer'] ?? null) ? trim($filters['numer']) : '';
        if ($numer !== '') {
            $where[] = 'o.`numer_zlecenia` LIKE :numer';
            $params[':numer'] = '%' . $numer . '%';
        }

        $klient = is_string($filters['klient'] ?? null) ? trim($filters['klient']) : '';
        if ($klient !== '') {
            $where[] = 'o.`klient_nazwa` LIKE :klient';
            $params[':klient'] = '%' . $klient . '%';
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = 'o.`terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($status !== '') {
            $where[] = 'o.`status` = :status';
            $params[':status'] = $status;
        }

        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = 'o.`data_rozpoczecia` >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = 'o.`data_zakonczenia` <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $sql = 'SELECT o.*, t.`nazwa` AS terminal_nazwa
                FROM `orders` o
                LEFT JOIN `terminals` t ON t.`id` = o.`terminal_id`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY o.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{numer?: string, klient?: string, terminal_id?: string, status?: string, date_from?: string, date_to?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $numer = is_string($filters['numer'] ?? null) ? trim($filters['numer']) : '';
        if ($numer !== '') {
            $where[] = '`numer_zlecenia` LIKE :numer';
            $params[':numer'] = '%' . $numer . '%';
        }

        $klient = is_string($filters['klient'] ?? null) ? trim($filters['klient']) : '';
        if ($klient !== '') {
            $where[] = '`klient_nazwa` LIKE :klient';
            $params[':klient'] = '%' . $klient . '%';
        }

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = '`terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($status !== '') {
            $where[] = '`status` = :status';
            $params[':status'] = $status;
        }

        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = '`data_rozpoczecia` >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = '`data_zakonczenia` <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $sql = 'SELECT COUNT(*) FROM `orders`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Wyszukiwanie po numerze (do walidacji unikalności).
     *
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $numer): ?array
    {
        $sql = 'SELECT * FROM `orders` WHERE `numer_zlecenia` = :numer LIMIT 1';
        $stmt = $this->executeQuery($sql, [':numer' => $numer]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Zwraca zlecenia przypadające w przedziale dat [od, do] (do kopiowania tygodnia).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBetweenDates(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT o.*, t.`nazwa` AS terminal_nazwa
                FROM `orders` o
                LEFT JOIN `terminals` t ON t.`id` = o.`terminal_id`
                WHERE o.`data_rozpoczecia` >= :date_from AND o.`data_rozpoczecia` <= :date_to
                ORDER BY o.`data_rozpoczecia` ASC';
        $stmt = $this->executeQuery($sql, [
            ':date_from' => $dateFrom . ' 00:00:00',
            ':date_to' => $dateTo . ' 23:59:59',
        ]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createOrder(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateOrder(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    public function deleteOrder(int $id): bool
    {
        return $this->delete($id);
    }
    // --- Przypisania pracowników i sprzętu ---

    /**
     * Lista przypisanych pracowników (z danymi pracownika; NULL = pracownik usunięty).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAssignedEmployees(int $orderId): array
    {
        $sql = 'SELECT oe.`id`, oe.`order_id`, oe.`employee_id`,
                       emp.`imie`, emp.`nazwisko`, emp.`email`
                FROM `order_employees` oe
                LEFT JOIN `employees` emp ON emp.`id` = oe.`employee_id`
                WHERE oe.`order_id` = :order_id
                ORDER BY oe.`id` ASC';
        $stmt = $this->executeQuery($sql, [':order_id' => $orderId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Lista przypisanego sprzętu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAssignedEquipment(int $orderId): array
    {
        $sql = 'SELECT oeq.`id`, oeq.`order_id`, oeq.`equipment_id`,
                       eq.`nazwa`, eq.`numer_seryjny`, eq.`kategoria`
                FROM `order_equipment` oeq
                LEFT JOIN `equipment` eq ON eq.`id` = oeq.`equipment_id`
                WHERE oeq.`order_id` = :order_id
                ORDER BY oeq.`id` ASC';
        $stmt = $this->executeQuery($sql, [':order_id' => $orderId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function isEmployeeAssigned(int $orderId, int $employeeId): bool
    {
        $sql = 'SELECT COUNT(*) FROM `order_employees` WHERE `order_id` = :order_id AND `employee_id` = :employee_id';
        $stmt = $this->executeQuery($sql, [':order_id' => $orderId, ':employee_id' => $employeeId]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }

    public function isEquipmentAssigned(int $orderId, int $equipmentId): bool
    {
        $sql = 'SELECT COUNT(*) FROM `order_equipment` WHERE `order_id` = :order_id AND `equipment_id` = :equipment_id';
        $stmt = $this->executeQuery($sql, [':order_id' => $orderId, ':equipment_id' => $equipmentId]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }

    public function attachEmployee(int $orderId, int $employeeId): bool
    {
        $sql = 'INSERT IGNORE INTO `order_employees` (`order_id`, `employee_id`) VALUES (:order_id, :employee_id)';
        $this->executeQuery($sql, [':order_id' => $orderId, ':employee_id' => $employeeId]);

        return true;
    }

    public function detachEmployee(int $orderId, int $employeeId): bool
    {
        $sql = 'DELETE FROM `order_employees` WHERE `order_id` = :order_id AND `employee_id` = :employee_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function attachEquipment(int $orderId, int $equipmentId): bool
    {
        $sql = 'INSERT IGNORE INTO `order_equipment` (`order_id`, `equipment_id`) VALUES (:order_id, :equipment_id)';
        $this->executeQuery($sql, [':order_id' => $orderId, ':equipment_id' => $equipmentId]);

        return true;
    }

    public function detachEquipment(int $orderId, int $equipmentId): bool
    {
        $sql = 'DELETE FROM `order_equipment` WHERE `order_id` = :order_id AND `equipment_id` = :equipment_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, \PDO::PARAM_INT);
        $stmt->bindValue(':equipment_id', $equipmentId, \PDO::PARAM_INT);

        return $stmt->execute();
    }
}