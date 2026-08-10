<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium stawek godzinowych pracowników — dostęp do tabeli `employee_rates`.
 *
 * Etap 7a — historia stawek (rozliczenie po dacie wejścia w życie).
 */
class EmployeeRateRepository extends BaseRepository
{
    protected string $table = 'employee_rates';
    protected string $primaryKey = 'id';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByEmployeeId(int $employeeId): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `employee_id` = :employee_id ORDER BY `data_od` DESC, `id` DESC";
        $stmt = $this->executeQuery($sql, [':employee_id' => $employeeId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Aktualna stawka (data_od <= dzisiaj, data_do NULL lub >= dzisiaj).
     *
     * @return array<string, mixed>|null
     */
    public function findCurrentRate(int $employeeId, string $date): ?array
    {
        $sql = "SELECT * FROM `{$this->table}`
                WHERE `employee_id` = :employee_id
                  AND `data_od` <= :date
                  AND (`data_do` IS NULL OR `data_do` >= :date2)
                ORDER BY `data_od` DESC, `id` DESC
                LIMIT 1";
        $stmt = $this->executeQuery($sql, [':employee_id' => $employeeId, ':date' => $date, ':date2' => $date]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Stawka obowiązująca w danej dacie (najnowsza z data_od <= date).
     *
     * @return array<string, mixed>|null
     */
    public function findRateAt(int $employeeId, string $date): ?array
    {
        $sql = "SELECT * FROM `{$this->table}`
                WHERE `employee_id` = :employee_id AND `data_od` <= :date
                ORDER BY `data_od` DESC, `id` DESC
                LIMIT 1";
        $stmt = $this->executeQuery($sql, [':employee_id' => $employeeId, ':date' => $date]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Zamyka poprzednią stawkę (ustawia data_do = dzień przed nową datą_od).
     */
    public function closePreviousRate(int $employeeId, string $newDataOd): void
    {
        $sql = "UPDATE `{$this->table}`
                SET `data_do` = DATE(:new_data_od) - INTERVAL 1 DAY
                WHERE `employee_id` = :employee_id AND `data_do` IS NULL";
        $this->executeQuery($sql, [':employee_id' => $employeeId, ':new_data_od' => $newDataOd]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRate(array $data): array
    {
        return $this->create($data);
    }

    /**
     * Wszystkie stawki dla wielu pracowników (do batchowego rozliczenia).
     *
     * @param array<int, int> $employeeIds
     * @return array<int, array<int, array<string, mixed>>>  employee_id => stawki (posortowane data_od DESC)
     */
    public function findAllByEmployeeIds(array $employeeIds): array
    {
        $map = [];
        if ($employeeIds === []) {
            return $map;
        }
        foreach ($employeeIds as $id) {
            $map[(int) $id] = $this->findByEmployeeId((int) $id);
        }

        return $map;
    }

    /**
     * Aktualne stawki dla wielu pracowników (mapa employee_id => stawka_godzinowa).
     *
     * @param array<int, int> $employeeIds
     * @return array<int, float>
     */
    public function findCurrentRatesForEmployees(array $employeeIds, string $date): array
    {
        $map = [];
        foreach ($employeeIds as $id) {
            $rate = $this->findCurrentRate($id, $date);
            if ($rate !== null) {
                $stawka = $rate['stawka_godzinowa'] ?? null;
                $map[$id] = $stawka === null ? 0.0 : (is_numeric($stawka) ? (float) $stawka : 0.0);
            }
        }

        return $map;
    }
}