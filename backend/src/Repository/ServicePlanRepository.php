<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium planów przeglądów pojazdu — dostęp do tabeli `vehicle_service_plans`.
 *
 * Etap 8 — Sekcja: Sprzęt.
 */
class ServicePlanRepository extends BaseRepository
{
    protected string $table = 'vehicle_service_plans';
    protected string $primaryKey = 'id';

    /**
     * Lista planów przeglądów dla danego sprzętu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByEquipmentId(int $equipmentId): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `equipment_id` = :equipment_id ORDER BY `id` ASC";
        $stmt = $this->executeQuery($sql, [':equipment_id' => $equipmentId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1";
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPlan(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updatePlan(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    public function deletePlan(int $id): bool
    {
        return $this->delete($id);
    }
}