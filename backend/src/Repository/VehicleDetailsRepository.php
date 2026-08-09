<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium danych pojazdu — dostęp do tabeli `vehicle_details`.
 *
 * Etap 8 — Sekcja: Sprzęt.
 *
 * Rekord 1:1 z `equipment` (tylko dla kategorii „pojazd").
 */
class VehicleDetailsRepository extends BaseRepository
{
    protected string $table = 'vehicle_details';
    protected string $primaryKey = 'equipment_id';

    /**
     * @return array<string, mixed>|null
     */
    public function findByEquipmentId(int $equipmentId): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `equipment_id` = :equipment_id LIMIT 1";
        $stmt = $this->executeQuery($sql, [':equipment_id' => $equipmentId]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createDetails(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateDetails(int $equipmentId, array $data): ?array
    {
        return $this->update($equipmentId, $data);
    }

    public function deleteDetails(int $equipmentId): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `equipment_id` = :equipment_id";
        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, [':equipment_id' => $equipmentId]);

        return $stmt->execute();
    }
}