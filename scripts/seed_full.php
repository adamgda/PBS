<?php

declare(strict_types=1);

/**
 * PBS — skrypt: wyczyść dane (poza użytkownikami) i wgraj rozbudowane dane testowe.
 *
 * Użycie:  php scripts/seed_full.php
 *
 * - Czyści WSZYSTKIE tabele danych oprócz `users` (zostaje super_admin).
 * - Resetuje AUTO_INCREMENT w wyczyszczonych tabelach.
 * - Ustawia hasło superadmina na znane testowe (bezpieczne zgodnie z polityką).
 * - Wgrywa spójny zestaw danych: 5 terminali, 12 sprzętów, 22 pracowników,
 *   20 zleceń, faktury, awarie, raporty, alerty, notatki, logi audytowe.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Config\Config;
use App\Config\ConnectionFactory;

$config = Config::fromEnvFile(__DIR__ . '/../backend/.env');
$pdo = ConnectionFactory::fromConfig($config);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$ADMIN_PASSWORD = 'TestoweHaslo2026!';

// ============================================================
// KROK 1 — CZYSZCZENIE (poza `users`)
// ============================================================
$dataTables = [
    'terminals', 'equipment', 'vehicle_details', 'vehicle_service_plans',
    'equipment_history', 'employees', 'employee_documents', 'orders',
    'order_employees', 'order_equipment', 'incidents', 'incident_comments',
    'incident_status_history', 'daily_terminal_reports', 'daily_vehicle_reports',
    'alert_settings', 'alert_notifications', 'employee_rates',
    'employee_vacations', 'invoices', 'user_notes', 'audit_log',
    'password_reset_tokens', 'revoked_refresh_tokens',
];

foreach ($dataTables as $table) {
    $exists = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table),
    )->fetchColumn();
    if ((int) $exists > 0) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    }
}

// ============================================================
// KROK 2 — SUPERADMIN (ustaw hasło testowe + odblokuj konto)
// ============================================================
$adminId = (int) $pdo->query("SELECT `id` FROM `users` WHERE `role` = 'super_admin' ORDER BY `id` LIMIT 1")->fetchColumn();
if ($adminId <= 0) {
    fwrite(STDERR, "Brak użytkownika super_admin w tabeli users — przerywam.\n");
    exit(1);
}
$adminEmail = (string) $pdo->query("SELECT `email` FROM `users` WHERE `id` = {$adminId}")->fetchColumn();

$permissions = json_encode([
    'dashboard' => true, 'pracownicy' => true, 'sprzet' => true, 'terminale' => true,
    'harmonogram' => true, 'analityka' => true, 'raportowanie' => true,
    'ustawienia' => true, 'awaria' => true, 'export_csv' => true,
]);
$stmt = $pdo->prepare(
    "UPDATE `users`
     SET `password_hash` = ?, `role` = 'super_admin', `permissions` = ?,
         `is_active` = TRUE, `failed_login_attempts` = 0, `locked_until` = NULL,
         `must_change_password` = 0
     WHERE `id` = ?",
);
$stmt->execute([password_hash($ADMIN_PASSWORD, PASSWORD_BCRYPT), $permissions, $adminId]);

// ============================================================
// Helpery
// ============================================================
function d(string $expr): string
{
    return date('Y-m-d', strtotime($expr));
}

function dt(string $expr): string
{
    return date('Y-m-d H:i:s', strtotime($expr));
}

// ============================================================
// TERMINALE (5)
// ============================================================
$terminals = [
    ['Terminal Gdańsk Północny', 'ul. Portowa 1, Gdańsk', 'Baltic Operator Sp. z o.o.', '581234567', 'kontakt@baltic-operator.pl'],
    ['Terminal Gdynia', 'ul. Morska 12, Gdynia', 'Port Operations Sp. z o.o.', '582345678', 'bok@port-ops.pl'],
    ['Terminal Szczecin', 'ul. Nabrzeżna 5, Szczecin', 'Westport Sp. z o.o.', '913456789', 'sekretariat@westport.pl'],
    ['Terminal Świnoujście', 'ul. Wybrzeże 22, Świnoujście', 'Odra Port Sp. z o.o.', '914001122', 'biuro@odraport.pl'],
    ['Terminal Kołobrzeg', 'ul. Rybacka 7, Kołobrzeg', 'Bałtycka Żegluga Sp. z o.o.', '943551100', 'bok@baltycka-zegluga.pl'],
];
$stmtTerm = $pdo->prepare(
    "INSERT INTO `terminals` (`nazwa`, `adres`, `operator`, `telefon_operatora`, `email_operatora`, `is_active`)
     VALUES (?, ?, ?, ?, ?, TRUE)",
);
$terminalIds = [];
foreach ($terminals as $t) {
    $stmtTerm->execute($t);
    $terminalIds[] = (int) $pdo->lastInsertId();
}

// ============================================================
// SPRZĘT (12) — pojazdy (5) + inne (7)
// ============================================================
$vehicles = [
    ['pojazd', 'Ford Transit', 'FT-2024-001', 125000, '2026-01-15', d('-300 days'), 'Wszystko OK'],
    ['pojazd', 'Mercedes Sprinter', 'MS-2023-014', 89000, '2026-02-01', null, null],
    ['pojazd', 'Renault Master', 'RM-2025-008', 45000, '2026-02-15', d('-280 days'), 'Drobne uwagi dot. oświetlenia'],
    ['pojazd', 'Scania R450', 'SC-2022-003', 210000, '2026-03-10', d('-240 days'), 'Wszystko OK'],
    ['pojazd', 'Iveco Daily', 'IV-2023-011', 67000, '2026-04-05', d('-200 days'), 'Wszystko OK'],
];
$otherEquipment = [
    ['inne', 'Wózek widłowy Jungheinrich', 'JH-2019-003'],
    ['inne', 'Dźwig mobilny Liebherr', 'DM-2018-001'],
    ['inne', 'Pompa hydroforowa', 'PH-2022-002'],
    ['inne', 'Generator prądu', 'GP-2021-007'],
    ['inne', 'Zestaw narzędzi serwisowych', 'ZN-2024-010'],
    ['inne', 'Koparko-ładowarka', 'KL-2020-005'],
    ['inne', 'Teleskopowy podnośnik', 'TP-2024-002'],
];

$stmtEq = $pdo->prepare(
    "INSERT INTO `equipment` (`kategoria`, `nazwa`, `numer_seryjny`, `is_active`) VALUES (?, ?, ?, TRUE)",
);
$stmtVeh = $pdo->prepare(
    "INSERT INTO `vehicle_details` (`equipment_id`, `ostatni_przebieg`, `ostatni_serwis_olejowy`, `data_ostatniej_oc`, `wynik_ostatniej_oc`)
     VALUES (?, ?, ?, ?, ?)",
);

$vehicleIds = [];
foreach ($vehicles as $v) {
    $stmtEq->execute([$v[0], $v[1], $v[2]]);
    $eqId = (int) $pdo->lastInsertId();
    $vehicleIds[] = $eqId;
    $stmtVeh->execute([$eqId, $v[3], $v[4], $v[5], $v[6]]);
}

$otherIds = [];
foreach ($otherEquipment as $e) {
    $stmtEq->execute($e);
    $otherIds[] = (int) $pdo->lastInsertId();
}

// --- Plany przeglądów (service plans) dla pojazdów ---
$servicePlans = [
    [$vehicleIds[0], 'Przegląd okresowy', 15000, 180, d('-90 days'), d('+90 days')],
    [$vehicleIds[0], 'Wymiana oleju', 10000, 120, d('-60 days'), d('+60 days')],
    [$vehicleIds[1], 'Przegląd okresowy', 15000, 180, d('-70 days'), d('+110 days')],
    [$vehicleIds[2], 'Przegląd okresowy', 15000, 180, d('-40 days'), d('+140 days')],
    [$vehicleIds[3], 'Przegląd główny', 50000, 365, d('-200 days'), d('+165 days')],
    [$vehicleIds[4], 'Przegląd okresowy', 15000, 180, d('-30 days'), d('+150 days')],
];
$stmtSp = $pdo->prepare(
    "INSERT INTO `vehicle_service_plans`
        (`equipment_id`, `typ_przegladu`, `interwal_km`, `interwal_dni`, `data_ostatniego_wykonania`, `data_nastepnego_planowanego`, `is_active`)
     VALUES (?, ?, ?, ?, ?, ?, TRUE)",
);
foreach ($servicePlans as $sp) {
    $stmtSp->execute($sp);
}

// --- Historia sprzętu (equipment_history) ---
$eqHistory = [
    [$vehicleIds[0], 'serwis', 'Wymiana oleju i filtrów', dt('-60 days'), $adminId],
    [$vehicleIds[0], 'przebieg', 'Kontrola przebiegu 125 000 km', dt('-15 days'), $adminId],
    [$vehicleIds[1], 'serwis', 'Przegląd okresowy', dt('-70 days'), $adminId],
    [$vehicleIds[2], 'serwis', 'Korekta ustawień świateł', dt('-40 days'), $adminId],
    [$vehicleIds[3], 'serwis', 'Przegląd główny silnika', dt('-200 days'), $adminId],
    [$otherIds[0], 'przypisanie', 'Przypisanie do Terminala Gdańsk', dt('-10 days'), $adminId],
    [$otherIds[1], 'przypisanie', 'Przypisanie do Terminala Gdynia', dt('-8 days'), $adminId],
];
$stmtEqHist = $pdo->prepare(
    "INSERT INTO `equipment_history` (`equipment_id`, `typ`, `opis`, `data`, `created_by`) VALUES (?, ?, ?, ?, ?)",
);
foreach ($eqHistory as $h) {
    $stmtEqHist->execute($h);
}


// ============================================================
// PRACOWNICY (22)
// ============================================================
$employees = [
    ['Jan', 'Kowalski', '601111222', 'jan.kowalski@pbs.local', 0, 0],       // t1, FT
    ['Anna', 'Nowak', '602222333', 'anna.nowak@pbs.local', 0, null],
    ['Piotr', 'Wiśniewski', '603333444', 'piotr.wisniewski@pbs.local', 1, 1], // t2, MS
    ['Maria', 'Wójcik', '604444555', 'maria.wojcik@pbs.local', 1, null],
    ['Tomasz', 'Kamiński', '605555666', 'tomasz.kaminski@pbs.local', 2, 2],   // t3, RM
    ['Katarzyna', 'Lewandowska', '606666777', 'katarzyna.lewandowska@pbs.local', 2, null],
    ['Marek', 'Zieliński', '607777888', 'marek.zielinski@pbs.local', 3, 3],   // t4, SC
    ['Agnieszka', 'Szymańska', '608888999', 'agnieszka.szymanska@pbs.local', 3, null],
    ['Paweł', 'Woźniak', '609999000', 'pawel.wozniak@pbs.local', 4, 4],       // t5, IV
    ['Magdalena', 'Dąbrowska', '600111222', 'magdalena.dabrowska@pbs.local', 4, null],
    ['Krzysztof', 'Jankowski', '601222333', 'krzysztof.jankowski@pbs.local', 0, null],
    ['Ewa', 'Mazur', '602333444', 'ewa.mazur@pbs.local', 1, null],
    ['Michał', 'Krawczyk', '603444555', 'michal.krawczyk@pbs.local', 2, null],
    ['Zofia', 'Piotrowska', '604555666', 'zofia.piotrowska@pbs.local', 3, null],
    ['Rafał', 'Lis', '605666777', 'rafal.lis@pbs.local', 4, null],
    ['Aleksandra', 'Wiśniewska', '606777888', 'aleksandra.wisniewska@pbs.local', 0, null],
    ['Grzegorz', 'Nowicki', '607888999', 'grzegorz.nowicki@pbs.local', 1, null],
    ['Natalia', 'Zawadzka', '608999000', 'natalia.zawadzka@pbs.local', 2, null],
    ['Sebastian', 'Borkowski', '609000111', 'sebastian.borkowski@pbs.local', 3, null],
    ['Kinga', 'Adamczyk', '600333444', 'kinga.adamczyk@pbs.local', 4, null],
    ['Łukasz', 'Grabowski', '601444555', 'lukasz.grabowski@pbs.local', 0, null],
    ['Karolina', 'Michalska', '602555666', 'karolina.michalska@pbs.local', 1, null],
];

$stmtEmp = $pdo->prepare(
    "INSERT INTO `employees` (`imie`, `nazwisko`, `telefon`, `email`, `current_terminal_id`, `current_sprzet_id`, `is_active`)
     VALUES (?, ?, ?, ?, ?, ?, TRUE)",
);
$employeeIds = [];
foreach ($employees as $e) {
    $terminalId = $terminalIds[$e[4]];
    $sprzetId = $e[5] !== null ? $vehicleIds[$e[5]] : null;
    $stmtEmp->execute([$e[0], $e[1], $e[2], $e[3], $terminalId, $sprzetId]);
    $employeeIds[] = (int) $pdo->lastInsertId();
}

// Przypisz sprzęt "inny" do terminali i pracowników
$stmtUpdEq = $pdo->prepare("UPDATE `equipment` SET `current_terminal_id` = ?, `current_employee_id` = ? WHERE `id` = ?");
$stmtUpdEq->execute([$terminalIds[0], $employeeIds[20], $otherIds[0]]); // wózek → t1, Łukasz
$stmtUpdEq->execute([$terminalIds[1], $employeeIds[21], $otherIds[1]]); // dźwig → t2, Karolina
$stmtUpdEq->execute([$terminalIds[2], null, $otherIds[2]]);             // pompa → t3
$stmtUpdEq->execute([$terminalIds[3], null, $otherIds[3]]);             // generator → t4
$stmtUpdEq->execute([$terminalIds[4], null, $otherIds[5]]);             // koparka → t5
$stmtUpdEq->execute([$terminalIds[0], null, $otherIds[4]]);             // zestaw narzędzi → t1
$stmtUpdEq->execute([$terminalIds[1], null, $otherIds[6]]);             // podnośnik → t2


// ============================================================
// DOKUMENTY PRACOWNIKÓW (z częścią wygasających wkrótce → alerty)
// ============================================================
$documents = [
    [0, 'Uprawnienia na wózki widłowe UDT', 'UDT-2024-0089', d('-600 days'), d('+18 days')],
    [0, 'Badania medycyny pracy', 'BP-2025-0012', d('-150 days'), d('+200 days')],
    [1, 'Certyfikat BHP', 'BHP-2025-034', d('-200 days'), d('+60 days')],
    [2, 'Prawo jazdy kat. C', 'PJ-C-09876', d('-900 days'), d('+2200 days')],
    [2, 'Uprawnienia na wózki widłowe UDT', 'UDT-2023-0412', d('-800 days'), d('+25 days')],
    [3, 'Badania medycyny pracy', 'BP-2024-0199', d('-300 days'), d('+90 days')],
    [4, 'Certyfikat BHP', 'BHP-2024-101', d('-250 days'), d('+30 days')],
    [5, 'Badania medycyny pracy', 'BP-2025-0300', d('-100 days'), d('+120 days')],
    [6, 'Prawo jazdy kat. C', 'PJ-C-11223', d('-1100 days'), d('+1800 days')],
    [7, 'Certyfikat spawacza', 'CS-2024-005', d('-180 days'), d('+45 days')],
    [8, 'Badania medycyny pracy', 'BP-2025-0450', d('-80 days'), d('+150 days')],
    [10, 'Uprawnienia na wózki widłowe UDT', 'UDT-2025-0077', d('-120 days'), d('+28 days')],
    [11, 'Certyfikat BHP', 'BHP-2025-200', d('-90 days'), d('+200 days')],
    [12, 'Prawo jazdy kat. C', 'PJ-C-22334', d('-400 days'), d('+2200 days')],
    [13, 'Badania medycyny pracy', 'BP-2024-0555', d('-350 days'), d('+70 days')],
    [15, 'Certyfikat operatora dźwigu', 'OD-2023-014', d('-700 days'), d('+35 days')],
    [17, 'Certyfikat BHP', 'BHP-2025-310', d('-60 days'), d('+300 days')],
    [21, 'Uprawnienia na wózki widłowe UDT', 'UDT-2024-0999', d('-400 days'), d('+40 days')],
    [21, 'Certyfikat operatora dźwigu', 'OD-2024-022', d('-300 days'), d('+120 days')],
];
$stmtDoc = $pdo->prepare(
    "INSERT INTO `employee_documents` (`employee_id`, `nazwa`, `numer_dokumentu`, `data_wydania`, `data_waznosci`)
     VALUES (?, ?, ?, ?, ?)",
);
foreach ($documents as $doc) {
    $stmtDoc->execute([$employeeIds[$doc[0]], $doc[1], $doc[2], $doc[3], $doc[4]]);
}

// ============================================================
// STAWKI GODZINOWE (historia + obecna dla każdego)
// ============================================================
$rateBase = [45.00, 50.00, 48.00, 52.00, 55.00, 47.00, 53.00, 49.00, 51.00, 46.00, 54.00, 48.50];
$stmtRate = $pdo->prepare(
    "INSERT INTO `employee_rates` (`employee_id`, `stawka_godzinowa`, `data_od`, `data_do`) VALUES (?, ?, ?, ?)",
);
foreach ($employeeIds as $i => $empId) {
    $base = $rateBase[$i % count($rateBase)];
    $stmtRate->execute([$empId, $base - 5, d('first day of last month'), d('last day of last month')]);
    $stmtRate->execute([$empId, $base, date('Y-m-01'), null]);
}

// ============================================================
// URLOPY (część aktywna → KPI / alerty)
// ============================================================
$vacations = [
    [$employeeIds[1], d('-2 days'), d('+3 days'), 'wypoczynkowy', 'zatwierdzony'],
    [$employeeIds[3], d('+1 day'), d('+4 days'), 'na_zadanie', 'oczekujacy'],
    [$employeeIds[5], d('+6 days'), d('+9 days'), 'wypoczynkowy', 'zatwierdzony'],
    [$employeeIds[8], d('-4 days'), d('-1 day'), 'wypoczynkowy', 'zrealizowany'],
    [$employeeIds[10], d('0 days'), d('+2 days'), 'L4', 'zatwierdzony'],
    [$employeeIds[17], d('+10 days'), d('+14 days'), 'wypoczynkowy', 'oczekujacy'],
    [$employeeIds[20], d('-6 days'), d('-3 days'), 'na_zadanie', 'odrzucony'],
];
$stmtVac = $pdo->prepare(
    "INSERT INTO `employee_vacations` (`employee_id`, `data_od`, `data_do`, `typ`, `status`) VALUES (?, ?, ?, ?, ?)",
);
foreach ($vacations as $vac) {
    $stmtVac->execute($vac);
}


// ============================================================
// ZLECENIA (20) — różne terminale, statusy, zakresy dat
// ============================================================
$orderData = [
    // [numer, klient, terminalIdx, dataStart, dataKoniec, zakres, wartosc, status]
    ['ZL-2026-001', 'Baltic Operator Sp. z o.o.', 0, dt('-22 days 08:00'), dt('-22 days 16:00'), 'Rozładunek kontenerów', 5000, 'zakonczone'],
    ['ZL-2026-002', 'Port Operations Sp. z o.o.', 1, dt('-20 days 08:00'), dt('-20 days 16:00'), 'Przeładunek stali', 7500, 'zakonczone'],
    ['ZL-2026-003', 'Westport Sp. z o.o.', 2, dt('-18 days 08:00'), dt('-18 days 17:00'), 'Konserwacja nabrzeża', 3200, 'zakonczone'],
    ['ZL-2026-004', 'Odra Port Sp. z o.o.', 3, dt('-16 days 09:00'), dt('-16 days 17:00'), 'Załadunek zbóż', 9000, 'zakonczone'],
    ['ZL-2026-005', 'Bałtycka Żegluga Sp. z o.o.', 4, dt('-14 days 08:00'), dt('-14 days 15:00'), 'Rozładunek ryb mrożonych', 6400, 'zakonczone'],
    ['ZL-2026-006', 'Baltic Operator Sp. z o.o.', 0, dt('-12 days 08:00'), dt('-12 days 18:00'), 'Przeładunek kontenerów chłodniczych', 11000, 'zakonczone'],
    ['ZL-2026-007', 'Port Operations Sp. z o.o.', 1, dt('-10 days 08:00'), dt('-10 days 16:00'), 'Remont suwnicy', 14000, 'zakonczone'],
    ['ZL-2026-008', 'Westport Sp. z o.o.', 2, dt('-8 days 08:00'), dt('-8 days 15:00'), 'Załadunek drewna', 5200, 'zakonczone'],
    ['ZL-2026-009', 'Odra Port Sp. z o.o.', 3, dt('-6 days 08:00'), dt('-6 days 16:00'), 'Rozładunek paliw', 12800, 'zakonczone'],
    ['ZL-2026-010', 'Bałtycka Żegluga Sp. z o.o.', 4, dt('-3 days 08:00'), dt('-3 days 14:00'), 'Drobnica mieszana', 4600, 'w_realizacji'],
    ['ZL-2026-011', 'Baltic Operator Sp. z o.o.', 0, dt('-1 days 08:00'), dt('0 days 16:00'), 'Przeładunek maszyn', 9900, 'w_realizacji'],
    ['ZL-2026-012', 'Port Operations Sp. z o.o.', 1, dt('0 days 08:00'), dt('0 days 16:00'), 'Rozładunek kontenerów', 7200, 'w_realizacji'],
    ['ZL-2026-013', 'Westport Sp. z o.o.', 2, dt('0 days 09:00'), dt('+1 days 17:00'), 'Załadunek rudy', 13400, 'w_realizacji'],
    ['ZL-2026-014', 'Odra Port Sp. z o.o.', 3, dt('+1 days 08:00'), dt('+1 days 16:00'), 'Przeładunek gazu', 15600, 'w_realizacji'],
    ['ZL-2026-015', 'Bałtycka Żegluga Sp. z o.o.', 4, dt('+1 days 08:00'), dt('+2 days 15:00'), 'Konserwacja nabrzeża', 3900, 'w_realizacji'],
    ['ZL-2026-016', 'Baltic Operator Sp. z o.o.', 0, dt('+2 days 08:00'), dt('+2 days 16:00'), 'Rozładunek kontenerów', 6100, 'nowe'],
    ['ZL-2026-017', 'Port Operations Sp. z o.o.', 1, dt('+3 days 08:00'), dt('+3 days 16:00'), 'Załadunek stali', 8300, 'nowe'],
    ['ZL-2026-018', 'Westport Sp. z o.o.', 2, dt('+4 days 08:00'), dt('+4 days 17:00'), 'Przeładunek węgla', 10200, 'nowe'],
    ['ZL-2026-019', 'Odra Port Sp. z o.o.', 3, dt('+5 days 08:00'), dt('+5 days 16:00'), 'Drobnica', 5400, 'nowe'],
    ['ZL-2026-020', 'Bałtycka Żegluga Sp. z o.o.', 4, dt('+6 days 08:00'), dt('+6 days 15:00'), 'Rozładunek drewna', 4700, 'nowe'],
];

$stmtOrder = $pdo->prepare(
    "INSERT INTO `orders` (`numer_zlecenia`, `klient_nazwa`, `terminal_id`, `data_rozpoczecia`, `data_zakonczenia`, `zakres_prac`, `wartosc_pln`, `status`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
);
$stmtOrderEmp = $pdo->prepare("INSERT INTO `order_employees` (`order_id`, `employee_id`, `rola`, `godziny`) VALUES (?, ?, ?, ?)");
$stmtOrderEq = $pdo->prepare("INSERT INTO `order_equipment` (`order_id`, `equipment_id`) VALUES (?, ?)");

$roles = ['operator', 'brygadzista', 'sztauer', 'lukowy', 'operator_zurawia'];
$hoursOpts = [8.0, 7.5, 6.0, 8.5, 7.0];

// Pracownicy przypisani do danego terminala (indeksy listy $employees)
$terminalEmployees = [
    0 => [0, 1, 10, 15, 20],
    1 => [2, 3, 11, 16, 21],
    2 => [4, 5, 12, 17],
    3 => [6, 7, 13, 18],
    4 => [8, 9, 14, 19],
];

$orderIds = [];
foreach ($orderData as $i => $o) {
    $stmtOrder->execute([$o[0], $o[1], $terminalIds[$o[2]], $o[3], $o[4], $o[5], $o[6], $o[7]]);
    $orderId = (int) $pdo->lastInsertId();
    $orderIds[] = $orderId;

    // Przypisz 2-3 pracowników z tego terminala (rola + godziny)
    $pool = $terminalEmployees[$o[2]];
    $count = 2 + ($i % 2); // 2 lub 3
    for ($k = 0; $k < $count; $k++) {
        $empIdx = $pool[($i + $k) % count($pool)];
        $role = $roles[($i + $k) % count($roles)];
        $hours = $hoursOpts[($i + $k) % count($hoursOpts)];
        $stmtOrderEmp->execute([$orderId, $employeeIds[$empIdx], $role, $hours]);
    }

    // Przypisz 1-2 sprzęty z tego terminala
    $stmtOrderEq->execute([$orderId, $vehicleIds[$o[2]]]);
    if ($i % 2 === 0 && isset($otherIds[$o[2]])) {
        $stmtOrderEq->execute([$orderId, $otherIds[$o[2]]]);
    }
}


// ============================================================
// FAKTURY (10)
// ============================================================
$invoices = [
    // [orderIdx(lub null), numer, klient, kwota, data_wyst, termin, status, typ]
    [0, 'F-2026-001', 'Baltic Operator Sp. z o.o.', 5000, d('-20 days'), d('-6 days'), 'przeterminowana', 'po_zleceniu'],
    [1, 'F-2026-002', 'Port Operations Sp. z o.o.', 7500, d('-18 days'), d('+12 days'), 'wystawiona', 'po_zleceniu'],
    [2, 'F-2026-003', 'Westport Sp. z o.o.', 3200, d('-16 days'), d('+14 days'), 'zaplacona', 'po_zleceniu'],
    [3, 'F-2026-004', 'Odra Port Sp. z o.o.', 9000, d('-14 days'), d('+16 days'), 'wystawiona', 'po_zleceniu'],
    [4, 'F-2026-005', 'Bałtycka Żegluga Sp. z o.o.', 6400, d('-12 days'), d('+18 days'), 'zaplacona', 'po_zleceniu'],
    [5, 'F-2026-006', 'Baltic Operator Sp. z o.o.', 11000, d('-10 days'), d('+20 days'), 'wystawiona', 'po_zleceniu'],
    [6, 'F-2026-007', 'Port Operations Sp. z o.o.', 14000, d('-8 days'), d('-1 days'), 'przeterminowana', 'po_zleceniu'],
    [null, 'F-2026-008', 'Port Operations Sp. z o.o.', 3200, d('-15 days'), d('-5 days'), 'przeterminowana', 'koniec_miesiaca'],
    [null, 'F-2026-009', 'Westport Sp. z o.o.', 2600, d('-5 days'), d('+25 days'), 'wystawiona', 'koniec_miesiaca'],
    [8, 'F-2026-010', 'Odra Port Sp. z o.o.', 12800, d('-4 days'), d('+26 days'), 'wystawiona', 'po_zleceniu'],
];
$stmtInv = $pdo->prepare(
    "INSERT INTO `invoices` (`order_id`, `numer_faktury`, `klient_nazwa`, `kwota_pln`, `data_wystawienia`, `termin_platnosci`, `status`, `typ_wystawienia`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
);
foreach ($invoices as $inv) {
    $orderId = $inv[0] !== null ? $orderIds[$inv[0]] : null;
    $stmtInv->execute([$orderId, $inv[1], $inv[2], $inv[3], $inv[4], $inv[5], $inv[6], $inv[7]]);
}

// ============================================================
// AWARIE (6) + komentarze + historia statusów
// ============================================================
$incidents = [
    ['sprzet', 0, 'Nieszczelny układ hydrauliczny wózka widłowego', 'w_trakcie_naprawy'],
    ['inne', null, 'Awaria oświetlenia nabrzeża — brak światła na stanowisku 3', 'zgloszona'],
    ['sprzet', 1, 'Kontrolki ostrzegawcze — wymaga diagnostyki', 'naprawiona'],
    ['sprzet', 3, 'Wyciek oleju w układzie hamulcowym', 'w_trakcie_naprawy'],
    ['inne', null, 'Awaria pompy hydroforowej na nabrzeżu B', 'zamknieta'],
    ['sprzet', 4, 'Uszkodzona opona przyczepy', 'zgloszona'],
];
$stmtInc = $pdo->prepare(
    "INSERT INTO `incidents` (`typ`, `equipment_id`, `opis`, `status`, `zgloszona_przez`) VALUES (?, ?, ?, ?, ?)",
);
$stmtCom = $pdo->prepare("INSERT INTO `incident_comments` (`incident_id`, `tresc`, `user_id`) VALUES (?, ?, ?)");
$stmtHist = $pdo->prepare(
    "INSERT INTO `incident_status_history` (`incident_id`, `status_od`, `status_do`, `zmieniony_przez`) VALUES (?, ?, ?, ?)",
);
foreach ($incidents as $i => $inc) {
    $eqId = $inc[1] !== null ? $vehicleIds[$inc[1]] : null;
    $stmtInc->execute([$inc[0], $eqId, $inc[2], $inc[3], $adminId]);
    $incidentId = (int) $pdo->lastInsertId();

    $stmtCom->execute([$incidentId, 'Zgłoszenie przyjęte do realizacji.', $adminId]);
    if ($inc[3] !== 'zgloszona') {
        $stmtCom->execute([$incidentId, 'Wysłano technika na miejsce.', $adminId]);
    }
    if ($inc[3] === 'zamknieta') {
        $pdo->prepare("UPDATE `incidents` SET `data_zakonczenia` = NOW() WHERE `id` = ?")->execute([$incidentId]);
    }

    // Historia statusów
    $from = 'zgloszona';
    $to = $inc[3];
    if ($to !== 'zgloszona') {
        $stmtHist->execute([$incidentId, $from, $to === 'w_trakcie_naprawy' ? $to : 'w_trakcie_naprawy', $adminId]);
        if ($to === 'naprawiona' || $to === 'zamknieta') {
            $stmtHist->execute([$incidentId, 'w_trakcie_naprawy', $to, $adminId]);
        }
    }
}


// ============================================================
// RAPORTY DZIENNE (terminal + pojazd)
// ============================================================
$stmtTrep = $pdo->prepare(
    "INSERT INTO `daily_terminal_reports` (`terminal_id`, `data_raportu`, `opis`, `uwagi`, `utworzony_przez`)
     VALUES (?, ?, ?, ?, ?)",
);
$terminalReportDefs = [
    [0, '-9 days', 'Standardowy przeładunek kontenerów', 'Brak uwag'],
    [1, '-8 days', 'Przeładunek stali zakończony zgodnie z planem', null],
    [2, '-7 days', 'Prace konserwacyjne nabrzeża', 'Do obserwacji stan suwnicy'],
    [3, '-6 days', 'Załadunek zbóż na statek', null],
    [4, '-5 days', 'Rozładunek ryb mrożonych', 'Niski stan paliwa'],
    [0, '-4 days', 'Przeładunek kontenerów chłodniczych', null],
    [1, '-3 days', 'Remont suwnicy — postęp prac', 'Potrzebne części zamienne'],
    [2, '-2 days', 'Załadunek drewna', null],
    [3, '-1 days', 'Rozładunek paliw', 'Zachować procedury BHP'],
    [0, '0 days', 'Przeładunek maszyn', null],
    [1, '0 days', 'Rozładunek kontenerów', 'Ruch dwuzmianowy'],
    [4, '-1 days', 'Drobnica mieszana', null],
];
foreach ($terminalReportDefs as $tr) {
    $stmtTrep->execute([$terminalIds[$tr[0]], d($tr[1]), $tr[2], $tr[3], $adminId]);
}

$stmtVrep = $pdo->prepare(
    "INSERT INTO `daily_vehicle_reports` (`equipment_id`, `data_raportu`, `aktualny_przebieg`, `przebieg_oc`, `uwagi`, `utworzony_przez`)
     VALUES (?, ?, ?, ?, ?, ?)",
);
$vehicleReportDefs = [
    [0, '-8 days', 122300, 'Przebieg bez zastrzeżeń', null],
    [1, '-7 days', 87100, 'Przebieg zgodny', 'Zgłoszono drobny hałas'],
    [2, '-6 days', 43800, 'Przebieg bez zastrzeżeń', null],
    [3, '-5 days', 207500, 'Przebieg zgodny', null],
    [4, '-4 days', 65500, 'Przebieg bez zastrzeżeń', 'Kontrola opon'],
    [0, '-3 days', 123400, 'Przebieg bez zastrzeżeń', null],
    [1, '-2 days', 87900, 'Przebieg zgodny', null],
    [2, '-1 days', 44200, 'Przebieg bez zastrzeżeń', null],
    [3, '0 days', 210000, 'Przebieg zgodny', null],
    [4, '0 days', 67000, 'Przebieg bez zastrzeżeń', null],
    [0, '0 days', 125000, 'Przebieg bez zastrzeżeń', null],
    [2, '0 days', 45000, 'Przebieg bez zastrzeżeń', 'Uwagi dot. oświetlenia'],
];
foreach ($vehicleReportDefs as $vr) {
    $stmtVrep->execute([$vehicleIds[$vr[0]], d($vr[1]), $vr[2], $vr[3], $vr[4], $adminId]);
}


// ============================================================
// USTAWIENIA ALERTÓW + POWIADOMIENIA
// ============================================================
$alertSettings = [
    ['certyfikat_wygasa', true, '07:00:00'],
    ['przeglad_wymagany', true, '07:30:00'],
    ['brak_raportu_oc', true, '18:00:00'],
    ['awaria_zgloszona', true, null],
];
$stmtAlertCfg = $pdo->prepare(
    "INSERT INTO `alert_settings` (`email_odbiorcy`, `typ_alertu`, `czy_aktywny`, `czas_wysylki`) VALUES (?, ?, ?, ?)",
);
$alertCfgIds = [];
foreach ($alertSettings as $as) {
    $stmtAlertCfg->execute([$adminEmail, $as[0], $as[1], $as[2]]);
    $alertCfgIds[] = (int) $pdo->lastInsertId();
}

$stmtNotif = $pdo->prepare(
    "INSERT INTO `alert_notifications` (`alert_config_id`, `typ`, `ref_type`, `ref_id`, `data_wysylki`) VALUES (?, ?, ?, ?, ?)",
);
$notifications = [
    [$alertCfgIds[0], 'certyfikat_wygasa', 'employee_document', 1, d('-1 days')],
    [$alertCfgIds[0], 'certyfikat_wygasa', 'employee_document', 3, d('0 days')],
    [$alertCfgIds[1], 'przeglad_wymagany', 'vehicle_service_plan', 1, d('-2 days')],
    [$alertCfgIds[3], 'awaria_zgloszona', 'incident', 1, d('0 days')],
];
foreach ($notifications as $nt) {
    $stmtNotif->execute($nt);
}

// ============================================================
// NOTATKI UŻYTKOWNIKA (superadmin)
// ============================================================
$notes = [
    ['Sprawdzić harmonogram przeładunków na przyszły tydzień', 0, 1],
    ['Zamówić części zamienne do suwnicy (Terminal Gdynia)', 0, 2],
    ['Umówić przegląd techniczny Scania R450', 0, 3],
    ['Przygotować raport miesięczny dla zarządu', 1, 4],
];
$stmtNote = $pdo->prepare(
    "INSERT INTO `user_notes` (`user_id`, `tresc`, `is_done`, `kolejnosc`) VALUES (?, ?, ?, ?)",
);
foreach ($notes as $nt) {
    $stmtNote->execute([$adminId, $nt[0], $nt[1], $nt[2]]);
}

// ============================================================
// LOGI AUDYTOWE
// ============================================================
$auditActions = [
    ['order.create', 'orders', $orderIds[0], 'Utworzono zlecenie ZL-2026-001'],
    ['order.create', 'orders', $orderIds[5], 'Utworzono zlecenie ZL-2026-006'],
    ['incident.create', 'incidents', 1, 'Zgłoszono awarię wózka widłowego'],
    ['employee.create', 'employees', $employeeIds[20], 'Dodano pracownika Łukasz Grabowski'],
    ['invoice.create', 'invoices', 1, 'Wystawiono fakturę F-2026-001'],
    ['terminal.report.create', 'daily_terminal_reports', 1, 'Utworzono raport dzienny terminala'],
];
$stmtAudit = $pdo->prepare(
    "INSERT INTO `audit_log` (`user_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `user_agent`, `details`, `created_at`)
     VALUES (?, ?, ?, ?, '127.0.0.1', 'PBS-SEED', ?, ?)",
);
foreach ($auditActions as $aa) {
    $stmtAudit->execute([$adminId, $aa[0], $aa[1], $aa[2], json_encode(['note' => $aa[3]]), dt('-2 days')]);
}

// ============================================================
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Podsumowanie
$counts = [
    'users' => 'users', 'terminals' => 'terminals', 'equipment' => 'equipment',
    'employees' => 'employees', 'orders' => 'orders', 'order_employees' => 'order_employees',
    'order_equipment' => 'order_equipment', 'incidents' => 'incidents',
    'invoices' => 'invoices', 'daily_terminal_reports' => 'daily_terminal_reports',
    'daily_vehicle_reports' => 'daily_vehicle_reports', 'alert_settings' => 'alert_settings',
    'alert_notifications' => 'alert_notifications', 'user_notes' => 'user_notes',
    'audit_log' => 'audit_log', 'employee_documents' => 'employee_documents',
    'employee_rates' => 'employee_rates', 'employee_vacations' => 'employee_vacations',
];
echo "Seedowanie zakończone. Podsumowanie:\n";
echo str_pad('superadmin', 30) . $adminId . " ({$adminEmail}) hasło: {$ADMIN_PASSWORD}\n";
foreach ($counts as $label => $table) {
    $c = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo str_pad($label, 30) . $c . "\n";
}

