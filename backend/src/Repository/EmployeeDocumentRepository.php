<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium dokumentów pracownika — dostęp do tabeli `employee_documents`.
 *
 * Etap 7 — Sekcja: Pracownicy (zakładka Certyfikaty i uprawnienia).
 */
class EmployeeDocumentRepository extends BaseRepository
{
    protected string $table = 'employee_documents';
    protected string $primaryKey = 'id';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByEmployeeId(int $employeeId): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `employee_id` = :employee_id ORDER BY `data_waznosci` ASC, `id` ASC";
        $stmt = $this->executeQuery($sql, [':employee_id' => $employeeId]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Dokumenty dla zbioru pracowników (do kolumny „Uprawnienia" w liście).
     * Posortowane wg daty ważności rosnąco — najpilniejszy dokument jest pierwszy.
     *
     * @param array<int, int> $employeeIds
     * @return array<int, array<string, mixed>>
     */
    public function findForEmployeeIds(array $employeeIds): array
    {
        $employeeIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $employeeIds)));
        if ($employeeIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($employeeIds as $i => $id) {
            $key = ':eid' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT * FROM `' . $this->table . '`'
            . ' WHERE `employee_id` IN (' . implode(',', $placeholders) . ')'
            . ' ORDER BY `data_waznosci` ASC, `id` ASC';
        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
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
    public function createDocument(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateDocument(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }
}