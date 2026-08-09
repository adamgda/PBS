<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium historii sprzętu — dostęp do tabeli `equipment_history`.
 *
 * Etap 8 — Sekcja: Sprzęt.
 *
 * Log jest append-only (brak UPDATE/DELETE) — zgodnie z polityką audytu.
 */
class EquipmentHistoryRepository extends BaseRepository
{
    protected string $table = 'equipment_history';
    protected string $primaryKey = 'id';

    /**
     * Lista zdarzeń dla danego sprzętu, posortowana chronologicznie (od najnowszego).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByEquipmentId(int $equipmentId): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `equipment_id` = :equipment_id ORDER BY `data` DESC, `id` DESC";
        $stmt = $this->executeQuery($sql, [':equipment_id' => $equipmentId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Dodaje wpis do historii sprzętu (append-only).
     *
     * @param array<string, mixed> $details opcjonalne metadane
     */
    public function add(int $equipmentId, string $typ, string $opis, ?int $createdBy = null, ?array $details = null): void
    {
        $sql = "INSERT INTO `{$this->table}` (`equipment_id`, `typ`, `opis`, `created_by`)
                VALUES (:equipment_id, :typ, :opis, :created_by)";
        $this->executeQuery($sql, [
            ':equipment_id' => $equipmentId,
            ':typ' => $typ,
            ':opis' => $opis,
            ':created_by' => $createdBy,
        ]);
    }
}