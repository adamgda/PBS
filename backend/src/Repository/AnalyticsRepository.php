<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium analityki — agregacje statystyczne dla sekcji Analityka (Etap 12).
 *
 * Wszystkie zapytania są read-only i korzystają z istniejącego schematu
 * (`orders`, `order_employees`, `order_equipment`, `employee_rates`,
 * `incidents`, `terminals`, `equipment`, `employees`). Stawki godzinowe
 * pobierane są skorelowanym podzapytaniem (najnowsza stawka z `data_od`
 * <= data rozpoczęcia zlecenia) — wzorzec anti-N+1 jak w OrderRepository.
 */
class AnalyticsRepository extends BaseRepository
{
    protected string $table = 'orders';
    protected string $primaryKey = 'id';

    /**
     * Główne statystyki (KPI) w zadanym zakresie dat.
     *
     * @return array<string, mixed>
     */
    public function overview(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT
                    (SELECT COUNT(*) FROM `orders`
                     WHERE `data_rozpoczecia` >= :df1 AND `data_rozpoczecia` <= :dt1) AS total_orders,
                    (SELECT COALESCE(SUM(`wartosc_pln`),0) FROM `orders`
                     WHERE `data_rozpoczecia` >= :df2 AND `data_rozpoczecia` <= :dt2) AS total_value,
                    (SELECT COALESCE(SUM(oe.`godziny`),0)
                     FROM `order_employees` oe
                     INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                     WHERE o.`data_rozpoczecia` >= :df3 AND o.`data_rozpoczecia` <= :dt3) AS total_hours,
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
                     WHERE o2.`data_rozpoczecia` >= :df4 AND o2.`data_rozpoczecia` <= :dt4) AS total_wages,
                    (SELECT COUNT(*) FROM `incidents`
                     WHERE `data_zgloszenia` >= :df5 AND `data_zgloszenia` <= :dt5) AS total_incidents,
                    (SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, `data_zgloszenia`, `data_zakonczenia`)),0)
                     FROM `incidents`
                     WHERE `data_zakonczenia` IS NOT NULL
                       AND `data_zgloszenia` >= :df6 AND `data_zgloszenia` <= :dt6) AS incident_downtime_hours';
        $stmt = $this->executeQuery($sql, [
            ':df1' => $dateFrom, ':dt1' => $dateTo,
            ':df2' => $dateFrom, ':dt2' => $dateTo,
            ':df3' => $dateFrom, ':dt3' => $dateTo,
            ':df4' => $dateFrom, ':dt4' => $dateTo,
            ':df5' => $dateFrom, ':dt5' => $dateTo,
            ':df6' => $dateFrom, ':dt6' => $dateTo,
        ]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? [] : $result;
    }

    /**
     * Statystyki per terminal (liczba zleceń i suma godzin) w zakresie dat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function terminals(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT t.`id` AS terminal_id, t.`nazwa`,
                       COUNT(DISTINCT o.`id`) AS order_count,
                       COALESCE(SUM(oe.`godziny`),0) AS total_hours
                FROM `terminals` t
                LEFT JOIN `orders` o
                    ON o.`terminal_id` = t.`id`
                    AND o.`data_rozpoczecia` >= :date_from
                    AND o.`data_rozpoczecia` <= :date_to
                LEFT JOIN `order_employees` oe ON oe.`order_id` = o.`id`
                GROUP BY t.`id`, t.`nazwa`
                ORDER BY order_count DESC, t.`nazwa` ASC';
        $stmt = $this->executeQuery($sql, [':date_from' => $dateFrom, ':date_to' => $dateTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Statystyki per pracownik (godziny, wynagrodzenie, rola) w zakresie dat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function employees(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT oe.`employee_id`,
                       emp.`imie`, emp.`nazwisko`,
                       COALESCE(SUM(oe.`godziny`),0) AS total_hours,
                       COALESCE(SUM(oe.`godziny` * COALESCE((
                           SELECT er.`stawka_godzinowa`
                           FROM `employee_rates` er
                           WHERE er.`employee_id` = oe.`employee_id`
                             AND er.`data_od` <= COALESCE(o.`data_rozpoczecia`, NOW())
                           ORDER BY er.`data_od` DESC, er.`id` DESC
                           LIMIT 1
                       ),0)),0) AS total_wages,
                       (SELECT oe2.`rola`
                        FROM `order_employees` oe2
                        WHERE oe2.`employee_id` = oe.`employee_id`
                          AND oe2.`rola` IS NOT NULL
                        ORDER BY oe2.`id` DESC
                        LIMIT 1) AS rola
                FROM `order_employees` oe
                LEFT JOIN `employees` emp ON emp.`id` = oe.`employee_id`
                LEFT JOIN `orders` o ON o.`id` = oe.`order_id`
                WHERE oe.`employee_id` IS NOT NULL
                  AND o.`data_rozpoczecia` >= :date_from
                  AND o.`data_rozpoczecia` <= :date_to
                GROUP BY oe.`employee_id`, emp.`imie`, emp.`nazwisko`
                ORDER BY total_hours DESC, emp.`nazwisko` ASC';
        $stmt = $this->executeQuery($sql, [':date_from' => $dateFrom, ':date_to' => $dateTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Statystyki per sprzęt (liczba przypisań) w zakresie dat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function equipment(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT eq.`id` AS equipment_id, eq.`nazwa`, eq.`kategoria`,
                       COUNT(DISTINCT oeq.`id`) AS assignment_count
                FROM `equipment` eq
                LEFT JOIN `order_equipment` oeq ON oeq.`equipment_id` = eq.`id`
                LEFT JOIN `orders` o
                    ON o.`id` = oeq.`order_id`
                    AND o.`data_rozpoczecia` >= :date_from
                    AND o.`data_rozpoczecia` <= :date_to
                GROUP BY eq.`id`, eq.`nazwa`, eq.`kategoria`
                ORDER BY assignment_count DESC, eq.`nazwa` ASC';
        $stmt = $this->executeQuery($sql, [':date_from' => $dateFrom, ':date_to' => $dateTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Zlecenia w czasie — liczba zleceń per dzień w zakresie dat.
     * Dni bez zleceń są uzupełniane zerami, dzięki czemu seria jest ciągła.
     *
     * @return array<int, array{day: string, count: int}>
     */
    public function ordersInTime(string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT DATE(`data_rozpoczecia`) AS `day`, COUNT(*) AS `count`
                FROM `orders`
                WHERE `data_rozpoczecia` >= :date_from
                  AND `data_rozpoczecia` <= :date_to
                GROUP BY DATE(`data_rozpoczecia`)';
        $stmt = $this->executeQuery($sql, [':date_from' => $dateFrom, ':date_to' => $dateTo]);

        /** @var array<int, array{day: string, count: int|string}> $rows */
        $rows = $stmt->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['day']] = (int) $row['count'];
        }

        $result = [];
        $cursor = new \DateTimeImmutable(substr($dateFrom, 0, 10));
        $end = new \DateTimeImmutable(substr($dateTo, 0, 10));
        $step = new \DateInterval('P1D');

        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $result[] = ['day' => $day, 'count' => $counts[$day] ?? 0];
            $cursor = $cursor->add($step);
        }

        return $result;
    }

    /**
     * Relacje między zasobami — top pracownicy (przypisania, terminal, sprzęt, godziny).
     *
     * @return array<int, array<string, mixed>>
     */
    public function relations(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $sql = 'SELECT oe.`employee_id`, emp.`imie`, emp.`nazwisko`,
                       COUNT(DISTINCT oe.`id`) AS assignment_count,
                       t.`nazwa` AS terminal_nazwa,
                       eq.`nazwa` AS equipment_nazwa,
                       COALESCE(SUM(oe.`godziny`),0) AS total_hours
                FROM `order_employees` oe
                LEFT JOIN `employees` emp ON emp.`id` = oe.`employee_id`
                LEFT JOIN `orders` o ON o.`id` = oe.`order_id`
                LEFT JOIN `terminals` t ON t.`id` = emp.`current_terminal_id`
                LEFT JOIN `equipment` eq ON eq.`id` = emp.`current_sprzet_id`
                WHERE oe.`employee_id` IS NOT NULL
                  AND o.`data_rozpoczecia` >= :date_from
                  AND o.`data_rozpoczecia` <= :date_to
                GROUP BY oe.`employee_id`, emp.`imie`, emp.`nazwisko`, t.`nazwa`, eq.`nazwa`
                ORDER BY assignment_count DESC, total_hours DESC
                LIMIT ' . $limit;
        $stmt = $this->executeQuery($sql, [':date_from' => $dateFrom, ':date_to' => $dateTo]);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }
}
