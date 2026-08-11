<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium źródeł danych dla mechanizmu alertów (Etap 14).
 *
 * Wszystkie zapytania są read-only i korzystają z istniejącego schematu:
 * `employee_documents` (certyfikaty), `vehicle_service_plans` (przeglądy),
 * `equipment` + `daily_vehicle_reports` (brak raportu OC) oraz `incidents`
 * (nowe awarie).
 */
class AlertSourceRepository extends BaseRepository
{
    protected string $table = 'employee_documents';
    protected string $primaryKey = 'id';

    /**
     * Certyfikaty/uprawnienia pracowników wygasające w ciągu podanej liczby dni.
     *
     * @return array<int, array<string, mixed>>
     */
    public function expiringCertificates(int $days): array
    {
        $from = date('Y-m-d');
        $toTs = strtotime('+' . $days . ' days');
        $to = $toTs === false ? $from : date('Y-m-d', $toTs);

        $sql = 'SELECT ed.`id`, ed.`nazwa`, ed.`numer_dokumentu`, ed.`data_waznosci`,
                       emp.`imie`, emp.`nazwisko`
                FROM `employee_documents` ed
                INNER JOIN `employees` emp ON emp.`id` = ed.`employee_id`
                WHERE ed.`data_waznosci` IS NOT NULL
                  AND ed.`data_waznosci` BETWEEN :from AND :to
                ORDER BY ed.`data_waznosci` ASC';
        $stmt = $this->executeQuery($sql, [':from' => $from, ':to' => $to]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Zbliżające się przeglądy pojazdów (plan w ciągu podanej liczby dni).
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcomingInspections(int $days): array
    {
        $from = date('Y-m-d');
        $toTs = strtotime('+' . $days . ' days');
        $to = $toTs === false ? $from : date('Y-m-d', $toTs);

        $sql = 'SELECT vsp.`id`, vsp.`typ_przegladu`, vsp.`data_nastepnego_planowanego`,
                       eq.`nazwa`, eq.`numer_seryjny`
                FROM `vehicle_service_plans` vsp
                INNER JOIN `equipment` eq ON eq.`id` = vsp.`equipment_id`
                WHERE vsp.`is_active` = TRUE
                  AND vsp.`data_nastepnego_planowanego` IS NOT NULL
                  AND vsp.`data_nastepnego_planowanego` BETWEEN :from AND :to
                ORDER BY vsp.`data_nastepnego_planowanego` ASC';
        $stmt = $this->executeQuery($sql, [':from' => $from, ':to' => $to]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Aktywne pojazdy, dla których brak raportu OC danego dnia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function vehiclesMissingOcReport(string $date): array
    {
        $sql = 'SELECT eq.`id`, eq.`nazwa`, eq.`numer_seryjny`
                FROM `equipment` eq
                WHERE eq.`is_active` = TRUE
                  AND eq.`kategoria` = \'pojazd\'
                  AND NOT EXISTS (
                      SELECT 1 FROM `daily_vehicle_reports` dvr
                      WHERE dvr.`equipment_id` = eq.`id`
                        AND dvr.`data_raportu` = :date
                  )
                ORDER BY eq.`nazwa` ASC';
        $stmt = $this->executeQuery($sql, [':date' => $date]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Awarie zgłoszone od podanej daty/godziny.
     *
     * @return array<int, array<string, mixed>>
     */
    public function newIncidents(string $sinceDateTime): array
    {
        $sql = 'SELECT i.`id`, i.`opis`, i.`status`, i.`data_zgloszenia`,
                       eq.`nazwa` AS equipment_nazwa
                FROM `incidents` i
                LEFT JOIN `equipment` eq ON eq.`id` = i.`equipment_id`
                WHERE i.`data_zgloszenia` >= :since
                ORDER BY i.`data_zgloszenia` ASC';
        $stmt = $this->executeQuery($sql, [':since' => $sinceDateTime]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }
}
