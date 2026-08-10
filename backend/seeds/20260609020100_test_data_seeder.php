<?php

declare(strict_types=1);

use App\Seeders\SeederInterface;

final class TestDataSeeder implements SeederInterface
{
    public function run(PDO $pdo): void
    {
        // --- Terminale ---
        $terminals = [
            ['Terminal Gdańsk', 'ul. Portowa 1, Gdańsk', 'Baltic Operator Sp. z o.o.', '581234567', 'kontakt@baltic-operator.pl'],
            ['Terminal Gdynia', 'ul. Morska 12, Gdynia', 'Port Operations Sp. z o.o.', '582345678', 'bok@port-ops.pl'],
            ['Terminal Szczecin', 'ul. Nabrzeżna 5, Szczecin', 'Westport Sp. z o.o.', '913456789', 'sekretariat@westport.pl'],
        ];

        $terminalIds = [];
        $stmtTerminal = $pdo->prepare(
            'INSERT INTO `terminals` (`nazwa`, `adres`, `operator`, `telefon_operatora`, `email_operatora`, `is_active`)
             VALUES (?, ?, ?, ?, ?, TRUE)',
        );

        foreach ($terminals as $t) {
            $stmtTerminal->execute($t);
            $terminalIds[] = (int) $pdo->lastInsertId();
        }

        // --- Sprzęt (pojazdy) ---
        $vehicles = [
            ['pojazd', 'Ford Transit', 'FT-2024-001', 125000, '2026-01-15', '2026-03-01', 'Wszystko OK'],
            ['pojazd', 'Mercedes Sprinter', 'MS-2023-014', 89000, '2026-02-01', null, null],
            ['pojazd', 'Renault Master', 'RM-2025-008', 45000, '2026-02-15', '2026-02-28', 'Drobne uwagi dot. oświetlenia'],
        ];

        $stmtEquipment = $pdo->prepare(
            'INSERT INTO `equipment` (`kategoria`, `nazwa`, `numer_seryjny`, `is_active`)
             VALUES (?, ?, ?, TRUE)',
        );
        $stmtVehicleDetails = $pdo->prepare(
            'INSERT INTO `vehicle_details` (`equipment_id`, `ostatni_przebieg`, `ostatni_serwis_olejowy`, `data_ostatniej_oc`, `wynik_ostatniej_oc`)
             VALUES (?, ?, ?, ?, ?)',
        );

        $vehicleEquipmentIds = [];
        foreach ($vehicles as $v) {
            $stmtEquipment->execute([$v[0], $v[1], $v[2]]);
            $eqId = (int) $pdo->lastInsertId();
            $vehicleEquipmentIds[] = $eqId;
            $stmtVehicleDetails->execute([$eqId, $v[3], $v[4], $v[5], $v[6]]);
        }

        // --- Sprzęt (inne) ---
        $otherEquipment = [
            ['inne', 'Wóztek widłowy Jungheinrich', 'JH-2019-003'],
            ['inne', 'Zestaw narzędzi serwisowych', 'ZN-2024-010'],
            ['inne', 'Pompa hydroforowa', 'PH-2022-002'],
        ];

        $otherEquipmentIds = [];
        foreach ($otherEquipment as $e) {
            $stmtEquipment->execute($e);
            $otherEquipmentIds[] = (int) $pdo->lastInsertId();
        }

        // --- Pracownicy ---
        $employees = [
            ['Jan', 'Kowalski', '601111222', 'jan.kowalski@pbs.local', $terminalIds[0], $vehicleEquipmentIds[0]],
            ['Anna', 'Nowak', '602222333', 'anna.nowak@pbs.local', $terminalIds[0], null],
            ['Piotr', 'Wiśniewski', '603333444', 'piotr.wisniewski@pbs.local', $terminalIds[1], $vehicleEquipmentIds[1]],
            ['Maria', 'Wójcik', '604444555', 'maria.wojcik@pbs.local', $terminalIds[1], null],
            ['Tomasz', 'Kamiński', '605555666', 'tomasz.kaminski@pbs.local', $terminalIds[2], $vehicleEquipmentIds[2]],
        ];

        $stmtEmployee = $pdo->prepare(
            'INSERT INTO `employees` (`imie`, `nazwisko`, `telefon`, `email`, `current_terminal_id`, `current_sprzet_id`, `is_active`)
             VALUES (?, ?, ?, ?, ?, ?, TRUE)',
        );

        $employeeIds = [];
        foreach ($employees as $emp) {
            $stmtEmployee->execute($emp);
            $employeeIds[] = (int) $pdo->lastInsertId();
        }

        // --- Dokumenty pracownika (certyfikaty/uprawnienia) ---
        $documents = [
            [0, 'Uprawnienia na wózki widłowe UDT', 'UDT-2024-0089', '2024-03-01', '2027-03-01'],
            [0, 'Badania medycyny pracy', 'BP-2025-0012', '2025-06-15', '2027-06-15'],
            [1, 'Certyfikat BHP', 'BHP-2025-034', '2025-01-10', '2026-08-15'],
            [2, 'Prawo jazdy kat. C', 'PJ-C-09876', '2023-05-20', '2033-05-20'],
            [2, 'Uprawnienia na wózki widłowe UDT', 'UDT-2023-0412', '2023-04-12', '2026-04-12'],
            [4, 'Badania medycyny pracy', 'BP-2024-0199', '2024-11-01', '2026-11-01'],
        ];

        $stmtDocument = $pdo->prepare(
            'INSERT INTO `employee_documents` (`employee_id`, `nazwa`, `numer_dokumentu`, `data_wydania`, `data_waznosci`)
             VALUES (?, ?, ?, ?, ?)',
        );

        foreach ($documents as $doc) {
            $stmtDocument->execute([
                $employeeIds[$doc[0]],
                $doc[1],
                $doc[2],
                $doc[3],
                $doc[4],
            ]);
        }

        // --- Przypisz sprzęt "inny" do terminali ---
        $stmtUpdateEquipment = $pdo->prepare(
            'UPDATE `equipment` SET `current_terminal_id` = ?, `current_employee_id` = ? WHERE `id` = ?',
        );
        $stmtUpdateEquipment->execute([$terminalIds[0], $employeeIds[0], $otherEquipmentIds[0]]);
        $stmtUpdateEquipment->execute([$terminalIds[1], $employeeIds[2], $otherEquipmentIds[1]]);
        $stmtUpdateEquipment->execute([$terminalIds[2], $employeeIds[4], $otherEquipmentIds[2]]);

        // --- Zlecenia (Etap 9) ---
        $adminId = (int) $pdo->query('SELECT `id` FROM `users` WHERE `email` = \'admin@pbs.local\' LIMIT 1')->fetchColumn();
        $weekStart = date('Y-m-d', strtotime('monday this week'));

        $orders = [
            ['ZL-2026-001', 'Baltic Operator Sp. z o.o.', $terminalIds[0], "{$weekStart} 08:00:00", "{$weekStart} 16:00:00", 'Rozładunek kontenerów', 5000, 'nowe'],
            ['ZL-2026-002', 'Port Operations Sp. z o.o.', $terminalIds[1], date('Y-m-d H:i:s', strtotime("{$weekStart} 08:00:00 +1 day")), date('Y-m-d H:i:s', strtotime("{$weekStart} 16:00:00 +1 day")), 'Przeładunek stali', 7500, 'w_realizacji'],
            ['ZL-2026-003', 'Westport Sp. z o.o.', $terminalIds[2], date('Y-m-d H:i:s', strtotime("{$weekStart} 08:00:00 +2 days")), date('Y-m-d H:i:s', strtotime("{$weekStart} 17:00:00 +2 days")), 'Konserwacja nabrzeża', 3200, 'zakonczone'],
        ];

        $stmtOrder = $pdo->prepare(
            'INSERT INTO `orders` (`numer_zlecenia`, `klient_nazwa`, `terminal_id`, `data_rozpoczecia`, `data_zakonczenia`, `zakres_prac`, `wartosc_pln`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmtOrderEmp = $pdo->prepare('INSERT INTO `order_employees` (`order_id`, `employee_id`, `rola`, `godziny`) VALUES (?, ?, ?, ?)');
        $stmtOrderEq = $pdo->prepare('INSERT INTO `order_equipment` (`order_id`, `equipment_id`) VALUES (?, ?)');

        // Role przypisań (Etap 7a) — pracownik pełni daną rolę w zleceniu.
        $orderRoles = ['operator', 'brygadzista', 'sztauer'];
        $orderHours = [8.0, 7.5, 6.0];

        $orderIds = [];
        foreach ($orders as $i => $o) {
            $stmtOrder->execute($o);
            $orderId = (int) $pdo->lastInsertId();
            $orderIds[] = $orderId;
            $empIdx = array_search($o[2], $terminalIds, true);
            if ($empIdx !== false && isset($employeeIds[$empIdx])) {
                $stmtOrderEmp->execute([$orderId, $employeeIds[$empIdx], $orderRoles[$i % 3], $orderHours[$i % 3]]);
            }
            if (isset($vehicleEquipmentIds[$empIdx !== false ? $empIdx : 0])) {
                $stmtOrderEq->execute([$orderId, $vehicleEquipmentIds[$empIdx !== false ? $empIdx : 0]]);
            }
        }

        // --- Stawki godzinowe (Etap 7a) ---
        $stmtRate = $pdo->prepare(
            'INSERT INTO `employee_rates` (`employee_id`, `stawka_godzinowa`, `data_od`, `data_do`)
             VALUES (?, ?, ?, ?)',
        );
        $rateBase = [45.00, 50.00, 48.00, 52.00, 55.00];
        $rateStart = date('Y-m-01');
        $prevStart = date('Y-m-d', strtotime('first day of last month'));
        foreach ($employeeIds as $i => $empId) {
            $stmtRate->execute([$empId, $rateBase[$i % 5] - 5, $prevStart, date('Y-m-d', strtotime('last day of last month'))]);
            $stmtRate->execute([$empId, $rateBase[$i % 5], $rateStart, null]);
        }

        // --- Urlopy (Etap 7a) ---
        $stmtVacation = $pdo->prepare(
            'INSERT INTO `employee_vacations` (`employee_id`, `data_od`, `data_do`, `typ`, `status`)
             VALUES (?, ?, ?, ?, ?)',
        );
        $stmtVacation->execute([$employeeIds[1], date('Y-m-d', strtotime('+1 week')), date('Y-m-d', strtotime('+1 week +2 days')), 'wypoczynkowy', 'zatwierdzony']);
        $stmtVacation->execute([$employeeIds[3], date('Y-m-d', strtotime('+2 days')), date('Y-m-d', strtotime('+4 days')), 'na_zadanie', 'oczekujacy']);

        // --- Faktury (Etap 7a) ---
        $stmtInvoice = $pdo->prepare(
            'INSERT INTO `invoices` (`order_id`, `numer_faktury`, `klient_nazwa`, `kwota_pln`, `data_wystawienia`, `termin_platnosci`, `status`, `typ_wystawienia`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        // Pierwsze zlecenie ma fakturę, trzecie (zakonczone) nie — pojawi się w invoices/missing.
        $stmtInvoice->execute([$orderIds[0], 'F-2026-001', 'Baltic Operator Sp. z o.o.', 5000, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+14 days')), 'wystawiona', 'po_zleceniu']);
        $stmtInvoice->execute([null, 'F-2026-002', 'Port Operations Sp. z o.o.', 3200, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-2 days')), 'przeterminowana', 'koniec_miesiaca']);

        // --- Awaria (Etap 10) ---
        $incidents = [
            ['sprzet', $vehicleEquipmentIds[0], 'Nieszczelny układ hydrauliczny wózka widłowego', 'w_trakcie_naprawy'],
            ['inne', null, 'Awaria oświetlenia nabrzeża — brak światła na stanowisku 3', 'zgloszona'],
            ['sprzet', $vehicleEquipmentIds[1], ' Kontrolki ostrzegawcze — wymaga diagnostyki', 'naprawiona'],
        ];

        $stmtIncident = $pdo->prepare(
            'INSERT INTO `incidents` (`typ`, `equipment_id`, `opis`, `status`, `zgloszona_przez`)
             VALUES (?, ?, ?, ?, ?)',
        );
        $stmtComment = $pdo->prepare(
            'INSERT INTO `incident_comments` (`incident_id`, `tresc`, `user_id`) VALUES (?, ?, ?)',
        );

        foreach ($incidents as $inc) {
            $stmtIncident->execute([$inc[0], $inc[1], $inc[2], $inc[3], $adminId]);
            $incidentId = (int) $pdo->lastInsertId();
            $stmtComment->execute([$incidentId, 'Zgłoszenie przyjęte do realizacji.', $adminId]);
            if ($inc[3] === 'naprawiona') {
                $pdo->prepare('UPDATE `incidents` SET `data_zakonczenia` = NOW() WHERE `id` = ?')->execute([$incidentId]);
            }
        }
    }

    public function name(): string
    {
        return 'test_data_seeder';
    }
}

return new TestDataSeeder();