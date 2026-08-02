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
    }

    public function name(): string
    {
        return 'test_data_seeder';
    }
}

return new TestDataSeeder();