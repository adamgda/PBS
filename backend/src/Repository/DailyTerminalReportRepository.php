<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium raportów dziennych terminala — dostęp do tabeli `daily_terminal_reports`.
 *
 * Etap 11 — Sekcja: Raportowanie.
 *
 * Lista zawiera JOIN z `terminals` w celu dostarczenia nazwy terminala w jednym
 * zapytaniu (anti-N+1). `findById` jest nadpisany, aby zwracał nazwę terminala
 * oraz e-mail autora raportu.
 */
class DailyTerminalReportRepository extends BaseRepository
{
    protected string $table = 'daily_terminal_reports';
    protected string $primaryKey = 'id';

    /**
     * Szczegóły raportu terminalowego z powiązaniami (terminal, autor).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT r.*, t.`nazwa` AS terminal_nazwa, u.`email` AS utworzony_przez_email
                FROM `daily_terminal_reports` r
                LEFT JOIN `terminals` t ON t.`id` = r.`terminal_id`
                LEFT JOIN `users` u ON u.`id` = r.`utworzony_przez`
                WHERE r.`id` = :id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Wyszukiwanie raportów terminalowych z paginacją, filtrowaniem i sortowaniem.
     *
     * @param array{terminal_id?: string, date_from?: string, date_to?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'data_raportu', 'terminal_nazwa', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = 'r.`terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
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

        $sql = 'SELECT r.*, t.`nazwa` AS terminal_nazwa, u.`email` AS utworzony_przez_email
                FROM `daily_terminal_reports` r
                LEFT JOIN `terminals` t ON t.`id` = r.`terminal_id`
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
     * @param array{terminal_id?: string, date_from?: string, date_to?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        $where = [];
        $params = [];

        $terminalId = is_string($filters['terminal_id'] ?? null) ? $filters['terminal_id'] : '';
        if ($terminalId !== '') {
            $where[] = '`terminal_id` = :terminal_id';
            $params[':terminal_id'] = (int) $terminalId;
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

        $sql = 'SELECT COUNT(*) FROM `daily_terminal_reports`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Sprawdza, czy istnieje już raport dla danego terminala i daty (unikalność).
     */
    public function existsForTerminalAndDate(int $terminalId, string $date, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `daily_terminal_reports`
                WHERE `terminal_id` = :terminal_id AND `data_raportu` = :data_raportu';
        $params = [':terminal_id' => $terminalId, ':data_raportu' => $date];

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
