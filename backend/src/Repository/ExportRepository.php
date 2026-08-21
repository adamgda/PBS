<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium eksportu CSV — read-only zapytania dla sekcji „Eksport danych".
 *
 * Wszystkie metody zwracają surowe wiersze, które `ExportService` serializuje
 * do formatu CSV (RFC 4180). Filtry `from` / `to` (Y-m-d) są opcjonalne —
 * pusty string oznacza brak ograniczenia.
 *
 * Zestawy:
 * - `orders`        — zlecenia z rozliczeniem godzin i wynagrodzeń (per przypisanie).
 * - `employees`     — pracownicy (z terminalem i sprzętem).
 * - `incidents`     — awarie (z nazwą sprzętu i czasem trwania).
 * - `daily_reports` — raporty dzienne (terminalowe + pojazdowe, UNION).
 */
class ExportRepository extends BaseRepository
{
    protected string $table = 'orders';
    protected string $primaryKey = 'id';

    /**
     * Zlecenia + rozliczenie: wiersz per przypisanie pracownika (order_employees).
     * Wynagrodzenie liczone najnowszą stawką z `employee_rates` (anti-N+1, wzór DashboardRepository).
     *
     * @return array<int, array<string, mixed>>
     */
    public function orders(string $from, string $to): array
    {
        $sql = 'SELECT
                    o.`id` AS order_id,
                    o.`numer_zlecenia`,
                    o.`klient_nazwa`,
                    t.`nazwa` AS terminal_nazwa,
                    o.`data_rozpoczecia`,
                    o.`data_zakonczenia`,
                    o.`zakres_prac`,
                    o.`wartosc_pln`,
                    o.`status`,
                    CONCAT(e.`imie`, \' \', e.`nazwisko`) AS pracownik,
                    oe.`rola`,
                    oe.`godziny`,
                    COALESCE((SELECT er.`stawka_godzinowa`
                        FROM `employee_rates` er
                        WHERE er.`employee_id` = oe.`employee_id`
                          AND er.`data_od` <= COALESCE(o.`data_rozpoczecia`, NOW())
                        ORDER BY er.`data_od` DESC, er.`id` DESC
                        LIMIT 1), 0) AS stawka_godzinowa
                FROM `order_employees` oe
                INNER JOIN `orders` o ON o.`id` = oe.`order_id`
                INNER JOIN `terminals` t ON t.`id` = o.`terminal_id`
                INNER JOIN `employees` e ON e.`id` = oe.`employee_id`
                WHERE o.`data_rozpoczecia` >= :from AND o.`data_rozpoczecia` <= :to
                ORDER BY o.`data_rozpoczecia` DESC, o.`id` DESC';

        $stmt = $this->executeQuery($sql, $this->rangeParams($from, $to));
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function employees(): array
    {
        $sql = 'SELECT
                    e.`id` AS employee_id,
                    e.`imie`,
                    e.`nazwisko`,
                    e.`telefon`,
                    e.`email`,
                    e.`is_active`,
                    t.`nazwa` AS terminal_nazwa,
                    eq.`nazwa` AS sprzet_nazwa
                FROM `employees` e
                LEFT JOIN `terminals` t ON t.`id` = e.`current_terminal_id`
                LEFT JOIN `equipment` eq ON eq.`id` = e.`current_sprzet_id`
                ORDER BY e.`nazwisko`, e.`imie`';

        $stmt = $this->executeQuery($sql);
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Sprzęt / pojazdy z danymi pojazdu (przebieg, OC, przeglądy) oraz
     * przypisaniem do terminala i pracownika. `data_nastepnego_przegladu` to
     * najwcześniejszy aktywny planowany przegląd (MIN z `vehicle_service_plans`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function equipment(): array
    {
        $sql = 'SELECT
                    eq.`id` AS equipment_id,
                    eq.`kategoria`,
                    eq.`nazwa`,
                    eq.`numer_seryjny`,
                    eq.`is_active`,
                    t.`nazwa` AS terminal_nazwa,
                    CONCAT(emp.`imie`, \' \', emp.`nazwisko`) AS przypisany_pracownik,
                    vd.`ostatni_przebieg`,
                    vd.`ostatni_serwis_olejowy`,
                    vd.`ostatnia_awaria`,
                    vd.`data_ostatniej_oc`,
                    vd.`wynik_ostatniej_oc`,
                    (SELECT MIN(vsp.`data_nastepnego_planowanego`)
                        FROM `vehicle_service_plans` vsp
                        WHERE vsp.`equipment_id` = eq.`id`
                          AND vsp.`is_active` = TRUE
                          AND vsp.`data_nastepnego_planowanego` IS NOT NULL) AS data_nastepnego_przegladu
                FROM `equipment` eq
                LEFT JOIN `terminals` t ON t.`id` = eq.`current_terminal_id`
                LEFT JOIN `employees` emp ON emp.`id` = eq.`current_employee_id`
                LEFT JOIN `vehicle_details` vd ON vd.`equipment_id` = eq.`id`
                ORDER BY eq.`kategoria`, eq.`nazwa`';

        $stmt = $this->executeQuery($sql);
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Awarie z czasem trwania (godziny) między zgłoszeniem a zakończeniem.
     *
     * @return array<int, array<string, mixed>>
     */
    public function incidents(string $from, string $to): array
    {
        $sql = 'SELECT
                    i.`id` AS incident_id,
                    i.`typ`,
                    eq.`nazwa` AS sprzet_nazwa,
                    i.`opis`,
                    i.`status`,
                    i.`data_zgloszenia`,
                    i.`data_zakonczenia`,
                    u.`email` AS zgloszona_przez_email,
                    CASE
                        WHEN i.`data_zakonczenia` IS NULL THEN NULL
                        ELSE TIMESTAMPDIFF(HOUR, i.`data_zgloszenia`, i.`data_zakonczenia`)
                    END AS czas_trwania_godziny
                FROM `incidents` i
                LEFT JOIN `equipment` eq ON eq.`id` = i.`equipment_id`
                LEFT JOIN `users` u ON u.`id` = i.`zgloszona_przez`
                WHERE i.`data_zgloszenia` >= :from AND i.`data_zgloszenia` <= :to
                ORDER BY i.`data_zgloszenia` DESC';

        $stmt = $this->executeQuery($sql, $this->rangeParams($from, $to));
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Raporty dzienne — terminalowe + pojazdowe w jednym zestawie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dailyReports(string $from, string $to): array
    {
        $sql = 'SELECT
                    \'terminal\' AS typ_raportu,
                    r.`data_raportu`,
                    t.`nazwa` AS obiekt,
                    r.`opis`,
                    r.`uwagi`,
                    u.`email` AS utworzono_przez
                FROM `daily_terminal_reports` r
                INNER JOIN `terminals` t ON t.`id` = r.`terminal_id`
                LEFT JOIN `users` u ON u.`id` = r.`utworzony_przez`
                WHERE r.`data_raportu` >= :from AND r.`data_raportu` <= :to
                UNION ALL
                SELECT
                    \'pojazd\',
                    r.`data_raportu`,
                    eq.`nazwa`,
                    CONCAT(\'Przebieg: \', r.`aktualny_przebieg`, \' | OC: \', r.`przebieg_oc`),
                    r.`uwagi`,
                    u2.`email`
                    FROM `daily_vehicle_reports` r
                    INNER JOIN `equipment` eq ON eq.`id` = r.`equipment_id`
                    LEFT JOIN `users` u2 ON u2.`id` = r.`utworzony_przez`
                    WHERE r.`data_raportu` >= :from AND r.`data_raportu` <= :to
                ORDER BY `data_raportu` DESC, `typ_raportu` DESC';

        $stmt = $this->executeQuery($sql, $this->rangeParams($from, $to));
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array{':from': string, ':to': string}
     */
    private function rangeParams(string $from, string $to): array
    {
        $fromVal = $from !== '' ? $from . ' 00:00:00' : '1970-01-01 00:00:00';
        $toVal = $to !== '' ? $to . ' 23:59:59' : '9999-12-31 23:59:59';

        return [
            ':from' => $fromVal,
            ':to' => $toVal,
        ];
    }
}

