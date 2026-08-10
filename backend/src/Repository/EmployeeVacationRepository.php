<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium urlopów pracowników — dostęp do tabeli `employee_vacations`.
 *
 * Etap 7a — rejestr urlopów (od/do, typ, status).
 */
class EmployeeVacationRepository extends BaseRepository
{
    protected string $table = 'employee_vacations';
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
     * Aktywne urlopy pracownika (status oczekujacy/zatwierdzony) pokrywające daną datę.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActiveOnDate(int $employeeId, string $date): array
    {
        $sql = "SELECT * FROM `{$this->table}`
                WHERE `employee_id` = :employee_id
                  AND `status` IN ('oczekujacy','zatwierdzony')
                  AND `data_od` <= :date AND `data_do` >= :date2
                ORDER BY `data_od` DESC";
        $stmt = $this->executeQuery($sql, [':employee_id' => $employeeId, ':date' => $date, ':date2' => $date]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Czy pracownik ma urlop pokrywający daną datę.
     */
    public function isOnLeave(int $employeeId, string $date): bool
    {
        return $this->findActiveOnDate($employeeId, $date) !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVacation(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateVacation(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Zbiór ID pracowników na urlopie w danym dniu (status oczekujacy/zatwierdzony).
     *
     * @param array<int, int> $employeeIds
     * @return array<int, bool>  employee_id => true
     */
    public function findOnLeaveEmployeeIds(array $employeeIds, string $date): array
    {
        $onLeave = [];
        foreach ($employeeIds as $id) {
            if ($this->isOnLeave((int) $id, $date)) {
                $onLeave[(int) $id] = true;
            }
        }

        return $onLeave;
    }

    /**
     * Liczba pracowników na urlopie w danym dniu.
     *
     * @param array<int, int> $employeeIds
     */
    public function countOnLeave(array $employeeIds, string $date): int
    {
        return count($this->findOnLeaveEmployeeIds($employeeIds, $date));
    }
}