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