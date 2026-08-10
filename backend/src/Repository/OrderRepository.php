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
    // --- Rozliczenia godzin i wynagrodzeń (Etap 7a) ---

    /**
     * Rozliczenie per pracownik dla zadanego miesiąca i okresu.
     *
     * Zwraca dla każdego pracownika: employee_id, imie, nazwisko, terminal_id,
     * rola (najczęstsza w miesiącu), godziny_1_15, godziny_15_23, godziny_total,
     * wynagrodzenie (godziny × stawka z historii po dacie zlecenia).
     *
     * @return array<int, array<string, mixed>>
     */
    public function settlementPerEmployee(string $month, string $period): array
    {
        [$dayFrom, $dayTo] = $this->periodBounds($month, $period);

        $sql = 'SELECT oe.`employee_id`,
                       emp.`imie`, emp.`nazwisko`,
                       o.`terminal_id`,
                       MAX(oe.`rola`) AS rola,
                       SUM(CASE WHEN DAY(o.`data_rozpoczecia`) < 15 THEN COALESCE(oe.`godziny`,0) ELSE 0 END) AS godziny_1_15,
                       SUM(CASE WHEN DAY(o.`data_rozpoczecia`) >= 15 THEN COALESCE(oe.`godziny`,0) ELSE 0 END) AS godziny_15_23,
                       SUM(COALESCE(oe.`godziny`,0)) AS godziny_total
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                LEFT JOIN `employees` emp ON emp.`id` = oe.`employee_id`
                WHERE DATE(o.`data_rozpoczecia`) BETWEEN :day_from AND :day_to
                  AND oe.`employee_id` IS NOT NULL
                GROUP BY oe.`employee_id`, emp.`imie`, emp.`nazwisko`, o.`terminal_id`
                ORDER BY emp.`nazwisko` ASC, emp.`imie` ASC';

        $stmt = $this->executeQuery($sql, [':day_from' => $dayFrom, ':day_to' => $dayTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Rozliczenie per port (terminal) dla zadanego miesiąca i okresu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settlementPerPort(string $month, string $period): array
    {
        [$dayFrom, $dayTo] = $this->periodBounds($month, $period);

        $sql = 'SELECT o.`terminal_id`,
                       t.`nazwa` AS terminal_nazwa,
                       COUNT(DISTINCT oe.`employee_id`) AS liczba_pracownikow,
                       SUM(COALESCE(oe.`godziny`,0)) AS suma_godzin
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                LEFT JOIN `terminals` t ON t.`id` = o.`terminal_id`
                WHERE DATE(o.`data_rozpoczecia`) BETWEEN :day_from AND :day_to
                  AND oe.`employee_id` IS NOT NULL
                GROUP BY o.`terminal_id`, t.`nazwa`
                ORDER BY t.`nazwa` ASC';

        $stmt = $this->executeQuery($sql, [':day_from' => $dayFrom, ':day_to' => $dayTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Szczegółowe wiersze rozliczeniowe (per pracownik + zlecenie) do dokładnego
     * wyliczenia wynagrodzenia po stawce obowiązującej w dacie zlecenia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settlementDetail(string $month, string $period): array
    {
        [$dayFrom, $dayTo] = $this->periodBounds($month, $period);

        $sql = 'SELECT oe.`employee_id`, DATE(o.`data_rozpoczecia`) AS data_zlecenia,
                       oe.`godziny`, o.`terminal_id`, oe.`rola`
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                WHERE DATE(o.`data_rozpoczecia`) BETWEEN :day_from AND :day_to
                  AND oe.`employee_id` IS NOT NULL
                ORDER BY oe.`employee_id` ASC, o.`data_rozpoczecia` ASC';
        $stmt = $this->executeQuery($sql, [':day_from' => $dayFrom, ':day_to' => $dayTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Suma godzin per pracownik w zadanym miesiącu (do kolumny „Godz. (mc)").
     *
     * @param array<int, int> $employeeIds
     * @return array<int, float>  employee_id => suma godzin
     */
    public function hoursPerEmployeeInMonth(string $month, array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $placeholders = implode(',', array_map(static fn (int $id): string => (string) $id, $employeeIds));
        $sql = "SELECT oe.`employee_id`, SUM(COALESCE(oe.`godziny`,0)) AS suma
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                WHERE DATE_FORMAT(o.`data_rozpoczecia`, '%Y-%m') = :month
                  AND oe.`employee_id` IS NOT NULL
                  AND oe.`employee_id` IN ({$placeholders})
                GROUP BY oe.`employee_id`";
        $stmt = $this->executeQuery($sql, [':month' => $month]);

        $map = [];
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $empId = $this->toInt($row['employee_id'] ?? 0);
            $suma = $row['suma'] ?? null;
            if ($empId > 0) {
                $map[$empId] = $suma === null ? 0.0 : (is_numeric($suma) ? (float) $suma : 0.0);
            }
        }

        return $map;
    }

    /**
     * Najczęstsza rola pracownika w bieżącym dniu (agregacja z najświeższych zleceń).
     *
     * @return array<int, string>  employee_id => rola
     */
    public function currentRolesByDate(string $date): array
    {
        $sql = 'SELECT oe.`employee_id`, oe.`rola`
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                WHERE DATE(o.`data_rozpoczecia`) = :date
                  AND oe.`employee_id` IS NOT NULL
                  AND oe.`rola` IS NOT NULL
                ORDER BY oe.`id` DESC';
        $stmt = $this->executeQuery($sql, [':date' => $date]);

        /** @var array<int, array<string, mixed>> */
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $empId = $this->toInt($row['employee_id'] ?? 0);
            if ($empId > 0 && !array_key_exists($empId, $map)) {
                $rola = $row['rola'];
                if (is_string($rola)) {
                    $map[$empId] = $rola;
                }
            }
        }

        return $map;
    }

    /**
     * Wyznacza granice dni dla miesiąca i wybranego okresu rozliczeniowego.
     *
     * @return array{0: string, 1: string}  [day_from, day_to] w formacie Y-m-d
     */
    private function periodBounds(string $month, string $period): array
    {
        $ts = strtotime($month . '-01');
        if ($ts === false) {
            $fallback = strtotime(date('Y-m') . '-01');
            $ts = $fallback === false ? time() : $fallback;
        }
        $year = (int) date('Y', $ts);
        $mon = (int) date('m', $ts);
        $lastDay = (int) date('t', $ts);

        $dayFrom = 1;
        $dayTo = $lastDay;

        if ($period === '1-15') {
            $dayFrom = 1;
            $dayTo = min(15, $lastDay);
        } elseif ($period === '15-23') {
            $dayFrom = 15;
            $dayTo = min(23, $lastDay);
        }

        return [
            sprintf('%04d-%02d-%02d', $year, $mon, $dayFrom),
            sprintf('%04d-%02d-%02d', $year, $mon, $dayTo),
        ];
    }

    // --- Przypisania pracowników i sprzętu ---

    /**
     * Lista przypisanych pracowników (z danymi pracownika; NULL = pracownik usunięty).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAssignedEmployees(int $orderId): array
    {
        // Stawka godzinowa obowiązująca w dacie rozpoczęcia zlecenia pobierana jest
        // poprzez skorelowane podzapytanie do `employee_rates` (najnowsza stawka
        // z data_od <= data_rozpoczecia zlecenia). Dzięki temu tabeli rozliczenia
        // godzin i wynagrodzeń wystarczy jedno zapytanie (anti-N+1).
        $sql = 'SELECT oe.`id`, oe.`order_id`, oe.`employee_id`, oe.`rola`, oe.`godziny`,
                       emp.`imie`, emp.`nazwisko`, emp.`email`,
                       o.`data_rozpoczecia` AS order_start,
                       (
                           SELECT er.`stawka_godzinowa`
                           FROM `employee_rates` er
                           WHERE er.`employee_id` = oe.`employee_id`
                             AND er.`data_od` <= COALESCE(o.`data_rozpoczecia`, NOW())
                           ORDER BY er.`data_od` DESC, er.`id` DESC
                           LIMIT 1
                       ) AS stawka_godzinowa
                FROM `order_employees` oe
                LEFT JOIN `employees` emp ON emp.`id` = oe.`employee_id`
                LEFT JOIN `orders` o ON o.`id` = oe.`order_id`
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

    public function attachEmployee(int $orderId, int $employeeId, ?string $rola = null, ?float $godziny = null): bool
    {
        $sql = 'INSERT IGNORE INTO `order_employees` (`order_id`, `employee_id`, `rola`, `godziny`)
                VALUES (:order_id, :employee_id, :rola, :godziny)';
        $this->executeQuery($sql, [
            ':order_id' => $orderId,
            ':employee_id' => $employeeId,
            ':rola' => $rola,
            ':godziny' => $godziny,
        ]);

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