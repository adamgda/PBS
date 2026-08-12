<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium awarii — dostęp do tabel `incidents`, `incident_comments`,
 * `incident_status_history`.
 *
 * Etap 10 — Sekcja: Awaria.
 *
 * Lista zawiera JOIN z `equipment` i `users` w celu dostarczenia nazwy sprzętu
 * oraz e-maila zgłaszającego w jednym zapytaniu (anti-N+1). Komentarze i historia
 * statusów pobierane są osobnymi zapytaniami.
 */
class IncidentRepository extends BaseRepository
{
    protected string $table = 'incidents';
    protected string $primaryKey = 'id';

    /**
     * Szczegóły awarii z powiązaniami (sprzęt, zgłaszający).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT i.*, eq.`nazwa` AS equipment_nazwa, u.`email` AS zgloszona_przez_email
                FROM `incidents` i
                LEFT JOIN `equipment` eq ON eq.`id` = i.`equipment_id`
                LEFT JOIN `users` u ON u.`id` = i.`zgloszona_przez`
                WHERE i.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie awarii z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{typ?: string, status?: string, equipment_id?: string, zrodlo?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'typ', 'status', 'data_zgloszenia', 'data_zakonczenia', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $typ = is_string($filters['typ'] ?? null) ? $filters['typ'] : '';
        if ($typ !== '') {
            $where[] = 'i.`typ` = :typ';
            $params[':typ'] = $typ;
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($status !== '') {
            $where[] = 'i.`status` = :status';
            $params[':status'] = $status;
        }

        $equipmentId = is_string($filters['equipment_id'] ?? null) ? $filters['equipment_id'] : '';
        if ($equipmentId !== '') {
            $where[] = 'i.`equipment_id` = :equipment_id';
            $params[':equipment_id'] = (int) $equipmentId;
        }

        $zrodlo = is_string($filters['zrodlo'] ?? null) ? $filters['zrodlo'] : '';
        if ($zrodlo !== '') {
            $where[] = 'i.`zrodlo` = :zrodlo';
            $params[':zrodlo'] = $zrodlo;
        }

        $sql = 'SELECT i.*, eq.`nazwa` AS equipment_nazwa, u.`email` AS zgloszona_przez_email
                FROM `incidents` i
                LEFT JOIN `equipment` eq ON eq.`id` = i.`equipment_id`
                LEFT JOIN `users` u ON u.`id` = i.`zgloszona_przez`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY i.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{typ?: string, status?: string, equipment_id?: string, zrodlo?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $typ = is_string($filters['typ'] ?? null) ? $filters['typ'] : '';
        if ($typ !== '') {
            $where[] = '`typ` = :typ';
            $params[':typ'] = $typ;
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($status !== '') {
            $where[] = '`status` = :status';
            $params[':status'] = $status;
        }

        $equipmentId = is_string($filters['equipment_id'] ?? null) ? $filters['equipment_id'] : '';
        if ($equipmentId !== '') {
            $where[] = '`equipment_id` = :equipment_id';
            $params[':equipment_id'] = (int) $equipmentId;
        }

        $zrodlo = is_string($filters['zrodlo'] ?? null) ? $filters['zrodlo'] : '';
        if ($zrodlo !== '') {
            $where[] = '`zrodlo` = :zrodlo';
            $params[':zrodlo'] = $zrodlo;
        }

        $sql = 'SELECT COUNT(*) FROM `incidents`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createIncident(array $data): array
    {
        return $this->create($data);
    }

    /**
     * Aktualizacja statusu awarii (opcjonalnie z datą zakończenia).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateIncident(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    // --- Komentarze ---

    /**
     * Lista komentarzy awarii (z e-mailem autora).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findComments(int $incidentId): array
    {
        $sql = 'SELECT c.*, u.`email` AS user_email
                FROM `incident_comments` c
                LEFT JOIN `users` u ON u.`id` = c.`user_id`
                WHERE c.`incident_id` = :incident_id
                ORDER BY c.`created_at` ASC, c.`id` ASC';
        $stmt = $this->executeQuery($sql, [':incident_id' => $incidentId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function addComment(int $incidentId, int $userId, string $tresc): array
    {
        $sql = 'INSERT INTO `incident_comments` (`incident_id`, `tresc`, `user_id`)
                VALUES (:incident_id, :tresc, :user_id)';
        $this->executeQuery($sql, [
            ':incident_id' => $incidentId,
            ':tresc' => $tresc,
            ':user_id' => $userId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $comments = $this->findComments($incidentId);
        foreach ($comments as $c) {
            if (is_array($c) && $this->toInt($c['id'] ?? 0) === $id) {
                return $c;
            }
        }

        return ['id' => $id, 'incident_id' => $incidentId, 'tresc' => $tresc, 'user_id' => $userId, 'user_email' => null, 'created_at' => null];
    }

    // --- Historia statusów ---

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStatusHistory(int $incidentId): array
    {
        $sql = 'SELECT h.*, u.`email` AS zmieniony_przez_email
                FROM `incident_status_history` h
                LEFT JOIN `users` u ON u.`id` = h.`zmieniony_przez`
                WHERE h.`incident_id` = :incident_id
                ORDER BY h.`created_at` ASC, h.`id` ASC';
        $stmt = $this->executeQuery($sql, [':incident_id' => $incidentId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function addStatusHistory(int $incidentId, string $statusOd, string $statusDo, int $userId): void
    {
        $sql = 'INSERT INTO `incident_status_history` (`incident_id`, `status_od`, `status_do`, `zmieniony_przez`)
                VALUES (:incident_id, :status_od, :status_do, :zmieniony_przez)';
        $this->executeQuery($sql, [
            ':incident_id' => $incidentId,
            ':status_od' => $statusOd,
            ':status_do' => $statusDo,
            ':zmieniony_przez' => $userId,
        ]);
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