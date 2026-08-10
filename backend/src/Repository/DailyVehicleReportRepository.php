<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium raportów dziennych pojazdu — dostęp do tabeli `daily_vehicle_reports`.
 *
 * Etap 11 — Sekcja: Raportowanie.
 *
 * Lista zawiera JOIN z `equipment` w celu dostarczenia nazwy sprzętu w jednym
 * zapytaniu (anti-N+1). `findById` jest nadpisany, aby zwracał dane pojazdu
 * oraz e-mail autora raportu.
 */
class DailyVehicleReportRepository extends BaseRepository
{
    protected string $table = 'daily_vehicle_reports';
    protected string $primaryKey = 'id';

    /**
     * Szczegóły raportu pojazdowego z powiązaniami (sprzęt, autor).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT r.*, eq.`nazwa` AS equipment_nazwa, eq.`numer_seryjny` AS equipment_numer_seryjny,
                       eq.`kategoria` AS equipment_kategoria, u.`email` AS utworzony_przez_email
                FROM `daily_vehicle_reports` r
                LEFT JOIN `equipment` eq ON eq.`id` = r.`equipment_id`
                LEFT JOIN `users` u ON u.`id` = r.`utworzony_przez`
                WHERE r.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie raportów pojazdowych z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{equipment_id?: string, date_from?: string, date_to?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'data_raportu', 'equipment_nazwa', 'aktualny_przebieg', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $equipmentId = is_string($filters['equipment_id'] ?? null) ? $filters['equipment_id'] : '';
        if ($equipmentId !== '') {
            $where[] = 'r.`equipment_id` = :equipment_id';
            $params[':equipment_id'] = (int) $equipmentId;
        }

        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = 'r.`data_raportu` >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = 'r.`data_raportu` <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $sql = 'SELECT r.*, eq.`nazwa` AS equipment_nazwa, eq.`numer_seryjny` AS equipment_numer_seryjny,
                       eq.`kategoria` AS equipment_kategoria, u.`email` AS utworzony_przez_email
                FROM `daily_vehicle_reports` r
                LEFT JOIN `equipment` eq ON eq.`id` = r.`equipment_id`
                LEFT JOIN `users` u ON u.`id` = r.`utworzony_przez`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY r.`{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{equipment_id?: string, date_from?: string, date_to?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $equipmentId = is_string($filters['equipment_id'] ?? null) ? $filters['equipment_id'] : '';
        if ($equipmentId !== '') {
            $where[] = '`equipment_id` = :equipment_id';
            $params[':equipment_id'] = (int) $equipmentId;
        }

        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = '`data_raportu` >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = '`data_raportu` <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $sql = 'SELECT COUNT(*) FROM `daily_vehicle_reports`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Sprawdza, czy istnieje już raport dla danego pojazdu i daty (unikalność).
     */
    public function existsForEquipmentAndDate(int $equipmentId, string $date, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `daily_vehicle_reports`
                WHERE `equipment_id` = :equipment_id AND `data_raportu` = :data_raportu';
        $params = [':equipment_id' => $equipmentId, ':data_raportu' => $date];

        if ($excludeId !== null) {
            $sql .= ' AND `id` <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}
