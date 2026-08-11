<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium konfiguracji alertów — dostęp do tabeli `alert_settings` (Etap 14).
 *
 * Każdy rekord to pojedyncza reguła: odbiorca e-mail + typ alertu + aktywność
 * oraz opcjonalna godzina wysyłki (np. dla braku raportu OC).
 */
class AlertConfigRepository extends BaseRepository
{
    protected string $table = 'alert_settings';
    protected string $primaryKey = 'id';

    /**
     * Wszystkie konfiguracje (posortowane po e-mailu i typie).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $sql = 'SELECT * FROM `alert_settings` WHERE `czy_aktywny` = TRUE ORDER BY `email_odbiorcy` ASC, `typ_alertu` ASC';
        $stmt = $this->executeQuery($sql);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Konfiguracje danego typu (np. `brak_raportu_oc`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $type): array
    {
        $sql = 'SELECT * FROM `alert_settings`
                WHERE `czy_aktywny` = TRUE AND `typ_alertu` = :typ
                ORDER BY `email_odbiorcy` ASC';
        $stmt = $this->executeQuery($sql, [':typ' => $type]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createConfig(array $data): array
    {
        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateConfig(int $id, array $data): ?array
    {
        return $this->update($id, $data);
    }
}
