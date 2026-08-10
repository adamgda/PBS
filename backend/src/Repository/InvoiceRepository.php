<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium faktur — dostęp do tabeli `invoices`.
 *
 * Etap 7a — faktury wystawione powiązane ze zleceniami.
 */
class InvoiceRepository extends BaseRepository
{
    protected string $table = 'invoices';
    protected string $primaryKey = 'id';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $numerFaktury): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `numer_faktury` = :numer LIMIT 1";
        $stmt = $this->executeQuery($sql, [':numer' => $numerFaktury]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array{numer?: string, klient?: string, status?: string, typ_wystawienia?: string, date_from?: string, date_to?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'numer_faktury', 'klient_nazwa', 'data_wystawienia', 'termin_platnosci', 'kwota_pln', 'status', 'typ_wystawienia', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        [$where, $params] = $this->buildWhere($filters);

        $sql = 'SELECT * FROM `invoices`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `{$sortColumn}` {$dir} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array{numer?: string, klient?: string, status?: string, typ_wystawienia?: string, date_from?: string, date_to?: string} $filters
     */
    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = 'SELECT COUNT(*) FROM `invoices`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Zlecenia zakończone (status zakonczone) bez wystawionej faktury.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findOrdersWithoutInvoice(int $limit, int $offset): array
    {
        $sql = 'SELECT o.`id`, o.`numer_zlecenia`, o.`klient_nazwa`, o.`terminal_id`, o.`data_zakonczenia`, o.`wartosc_pln`
                FROM `orders` o
                LEFT JOIN `invoices` i ON i.`order_id` = o.`id`
                WHERE o.`status` = \'zakonczone\' AND i.`id` IS NULL
                ORDER BY o.`data_zakonczenia` DESC, o.`id` DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->executeQuery($sql, []);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function countOrdersWithoutInvoice(): int
    {
        $sql = 'SELECT COUNT(*) FROM `orders` o
                LEFT JOIN `invoices` i ON i.`order_id` = o.`id`
                WHERE o.`status` = \'zakonczone\' AND i.`id` IS NULL';
        $stmt = $this->executeQuery($sql, []);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createInvoice(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateInvoice(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }

    /**
     * Buduje klauzulę WHERE z filtrów (współdzielone przez search/count).
     *
     * @param array{numer?: string, klient?: string, status?: string, typ_wystawienia?: string, date_from?: string, date_to?: string} $filters
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $numer = is_string($filters['numer'] ?? null) ? trim($filters['numer']) : '';
        if ($numer !== '') {
            $where[] = '`numer_faktury` LIKE :numer';
            $params[':numer'] = '%' . $numer . '%';
        }

        $klient = is_string($filters['klient'] ?? null) ? trim($filters['klient']) : '';
        if ($klient !== '') {
            $where[] = '`klient_nazwa` LIKE :klient';
            $params[':klient'] = '%' . $klient . '%';
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($status !== '') {
            $where[] = '`status` = :status';
            $params[':status'] = $status;
        }

        $typ = is_string($filters['typ_wystawienia'] ?? null) ? $filters['typ_wystawienia'] : '';
        if ($typ !== '') {
            $where[] = '`typ_wystawienia` = :typ';
            $params[':typ'] = $typ;
        }

        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = '`data_wystawienia` >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = '`data_wystawienia` <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        return [$where, $params];
    }
}