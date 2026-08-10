<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium dashboardu — agregacje KPI i alertów dla sekcji Dashboard (Etap 13).
 *
 * Wszystkie zapytania są read-only i korzystają z istniejącego schematu
 * (`employees`, `terminals`, `equipment`, `incidents`, `orders`,
 * `order_employees`, `employee_rates`, `employee_documents`,
 * `vehicle_service_plans`, `employee_vacations`). Stawki godzinowe pobierane
 * są skorelowanym podzapytaniem (najnowsza stawka z `data_od` <= data
 * rozpoczęcia zlecenia) — wzorzec anti-N+1 jak w OrderRepository.
 */
class DashboardRepository extends BaseRepository
{
    protected string $table = 'orders';
    protected string $primaryKey = 'id';

    /**
     * Podsumowanie KPI dla dashboardu (stan bieżący).
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT
                    (SELECT COUNT(*) FROM `employees` WHERE `is_active` = TRUE) AS active_employees,
                    (SELECT COUNT(*) FROM `terminals` WHERE `is_active` = TRUE) AS active_terminals,
                    (SELECT COUNT(*) FROM `equipment`
                     WHERE `is_active` = TRUE AND `kategoria` = \'pojazd\') AS vehicles_in_use,
                    (SELECT COUNT(*) FROM `incidents`
                     WHERE `status` IN (\'zgloszona\', \'w_trakcie_naprawy\', \'naprawiona\')) AS active_incidents,
                    (SELECT COALESCE(SUM(oe.`godziny`),0)
                     FROM `order_employees` oe
                     INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                     WHERE o.`data_rozpoczecia` >= :today_from AND o.`data_rozpoczecia` <= :today_to) AS hours_today,
                    (SELECT COUNT(DISTINCT ev.`employee_id`)
                     FROM `employee_vacations` ev
                     WHERE ev.`status` = \'zatwierdzony\'
                       AND ev.`data_od` <= :today1 AND ev.`data_do` >= :today2) AS employees_on_leave,
                    (SELECT COALESCE(SUM(oe2.`godziny` * COALESCE((
                        SELECT er.`stawka_godzinowa`
                        FROM `employee_rates` er
                        WHERE er.`employee_id` = oe2.`employee_id`
                          AND er.`data_od` <= COALESCE(o2.`data_rozpoczecia`, NOW())
                        ORDER BY er.`data_od` DESC, er.`id` DESC
                        LIMIT 1
                    ),0)),0)
                     FROM `order_employees` oe2
                     INNER JOIN `orders` o2 ON o2.`id` = oe2.`order_id`
                     WHERE o2.`data_rozpoczecia` >= :month_from AND o2.`data_rozpoczecia` <= :month_to) AS monthly_wages';
        $stmt = $this->executeQuery($sql, [
            ':today_from' => $today . ' 00:00:00',
            ':today_to' => $today . ' 23:59:59',
            ':today1' => $today,
            ':today2' => $today,
            ':month_from' => date('Y-m-01') . ' 00:00:00',
            ':month_to' => date('Y-m-t') . ' 23:59:59',
        ]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? [] : $result;
    }

    /**
     * Alerty dla dashboardu — liczniki i listy pozycji.
     *
     * @return array<string, array{count: int, items: array<int, array<string, mixed>>}>
     */
    public function alerts(): array
    {
        $certs = $this->fetchAlertItems(
            'SELECT ed.`id`, ed.`nazwa`, ed.`data_waznosci`
             FROM `employee_documents` ed
             WHERE ed.`data_waznosci` IS NOT NULL
               AND ed.`data_waznosci` BETWEEN :from AND :to
             ORDER BY ed.`data_waznosci` ASC',
            '30',
        );

        $inspections = $this->fetchAlertItems(
            'SELECT vsp.`id`, vsp.`typ_przegladu`, vsp.`data_nastepnego_planowanego`
             FROM `vehicle_service_plans` vsp
             WHERE vsp.`is_active` = TRUE
               AND vsp.`data_nastepnego_planowanego` IS NOT NULL
               AND vsp.`data_nastepnego_planowanego` BETWEEN :from AND :to
             ORDER BY vsp.`data_nastepnego_planowanego` ASC',
            '30',
        );

        $incidents = $this->fetchAlertItems(
            'SELECT i.`id`, i.`opis`, i.`status`, i.`data_zgloszenia`
             FROM `incidents` i
             WHERE i.`status` IN (\'zgloszona\', \'w_trakcie_naprawy\', \'naprawiona\')
             ORDER BY i.`data_zgloszenia` ASC',
            null,
        );

        $returns = $this->fetchAlertItems(
            'SELECT ev.`id`, ev.`data_do`, emp.`imie`, emp.`nazwisko`
             FROM `employee_vacations` ev
             INNER JOIN `employees` emp ON emp.`id` = ev.`employee_id`
             WHERE ev.`status` = \'zatwierdzony\'
               AND ev.`data_do` BETWEEN :from AND :to
             ORDER BY ev.`data_do` ASC',
            '7',
        );

        return [
            'expiring_certs' => ['count' => count($certs), 'items' => $certs],
            'upcoming_inspections' => ['count' => count($inspections), 'items' => $inspections],
            'unresolved_incidents' => ['count' => count($incidents), 'items' => $incidents],
            'returning_from_leave' => ['count' => count($returns), 'items' => $returns],
        ];
    }

    /**
     * Wykonuje zapytanie listy alertów z opcjonalnym zakresem dni (od dziś).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAlertItems(string $sql, ?string $days): array
    {
        $params = [];
        if ($days !== null) {
            $toTs = strtotime('+' . $days . ' days');
            $params[':from'] = date('Y-m-d');
            $params[':to'] = $toTs === false ? date('Y-m-d') : date('Y-m-d', $toTs);
        }

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }
}
