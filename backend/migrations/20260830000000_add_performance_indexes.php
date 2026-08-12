<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Etap 15a — Wydajność i optymalizacja: indeksowanie bazy danych.
 *
 * Dodaje brakujące indeksy z dokumentacji (sekcja 14.1) dla wszystkich tabel.
 * Indeksy są dodawane idempotentnie (sprawdzamy istnienie przed dodaniem),
 * dzięki czemu migracja jest bezpieczna do wielokrotnego uruchomienia.
 *
 * Zasady (dokumentacja 14.1):
 *  - Klucze obce automatycznie tworzą indeks w MySQL — pomijamy je.
 *  - Złożone indeksy dla częstych kombinacji filtrów.
 *  - Indeksy dodawane w migracjach — nie ręcznie w bazie.
 */
final class AddPerformanceIndexes extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        // === employees ===
        $this->addIndex($pdo, 'employees', 'idx_employees_is_active', ['is_active']);
        $this->addIndex($pdo, 'employees', 'idx_employees_current_terminal', ['current_terminal_id']);
        $this->addIndex($pdo, 'employees', 'idx_employees_current_sprzet', ['current_sprzet_id']);
        $this->addIndex($pdo, 'employees', 'idx_employees_nazwisko_imie', ['nazwisko', 'imie']);

        // === equipment ===
        $this->addIndex($pdo, 'equipment', 'idx_equipment_is_active', ['is_active']);
        $this->addIndex($pdo, 'equipment', 'idx_equipment_kategoria', ['kategoria']);
        $this->addIndex($pdo, 'equipment', 'idx_equipment_current_employee', ['current_employee_id']);
        $this->addIndex($pdo, 'equipment', 'idx_equipment_current_terminal', ['current_terminal_id']);

        // === orders ===
        $this->addIndex($pdo, 'orders', 'idx_orders_terminal', ['terminal_id']);
        $this->addIndex($pdo, 'orders', 'idx_orders_status', ['status']);
        $this->addIndex($pdo, 'orders', 'idx_orders_data_rozpoczecia', ['data_rozpoczecia']);
        $this->addIndex($pdo, 'orders', 'idx_orders_data_zakonczenia', ['data_zakonczenia']);
        $this->addIndex($pdo, 'orders', 'idx_orders_status_data_rozpoczecia', ['status', 'data_rozpoczecia']);

        // === incidents ===
        $this->addIndex($pdo, 'incidents', 'idx_incidents_status', ['status']);
        $this->addIndex($pdo, 'incidents', 'idx_incidents_equipment', ['equipment_id']);
        $this->addIndex($pdo, 'incidents', 'idx_incidents_data_zgloszenia', ['data_zgloszenia']);
        $this->addIndex($pdo, 'incidents', 'idx_incidents_zgloszona_przez', ['zgloszona_przez']);

        // === equipment_history ===
        $this->addIndex($pdo, 'equipment_history', 'idx_equipment_history_equipment_data', ['equipment_id', 'data']);

        // === daily_terminal_reports ===
        $this->addIndex($pdo, 'daily_terminal_reports', 'idx_dtr_terminal_data', ['terminal_id', 'data_raportu']);

        // === daily_vehicle_reports ===
        $this->addIndex($pdo, 'daily_vehicle_reports', 'idx_dvr_equipment_data', ['equipment_id', 'data_raportu']);

        // === order_employees ===
        $this->addIndex($pdo, 'order_employees', 'idx_order_employees_order', ['order_id']);
        $this->addIndex($pdo, 'order_employees', 'idx_order_employees_employee', ['employee_id']);
        $this->addIndex($pdo, 'order_employees', 'idx_order_employees_rola', ['rola']);
        $this->addIndex($pdo, 'order_employees', 'idx_order_employees_employee_rola', ['employee_id', 'rola']);

        // === order_equipment ===
        $this->addIndex($pdo, 'order_equipment', 'idx_order_equipment_order', ['order_id']);
        $this->addIndex($pdo, 'order_equipment', 'idx_order_equipment_equipment', ['equipment_id']);

        // === employee_rates ===
        $this->addIndex($pdo, 'employee_rates', 'idx_employee_rates_employee', ['employee_id']);
        $this->addIndex($pdo, 'employee_rates', 'idx_employee_rates_data_do', ['data_do']);

        // === employee_vacations ===
        $this->addIndex($pdo, 'employee_vacations', 'idx_employee_vacations_employee', ['employee_id']);
        $this->addIndex($pdo, 'employee_vacations', 'idx_employee_vacations_data_od_do', ['data_od', 'data_do']);

        // === invoices ===
        $this->addIndex($pdo, 'invoices', 'idx_invoices_order', ['order_id']);
        $this->addIndex($pdo, 'invoices', 'idx_invoices_status', ['status']);
        $this->addIndex($pdo, 'invoices', 'idx_invoices_typ_wystawienia', ['typ_wystawienia']);
        $this->addIndex($pdo, 'invoices', 'idx_invoices_data_wystawienia', ['data_wystawienia']);
        $this->addIndex($pdo, 'invoices', 'idx_invoices_klient_nazwa', ['klient_nazwa']);
    }

    public function down(PDO $pdo): void
    {
        // Usunięcie indeksów w odwrotnej kolejności (bezpieczne, idempotentne).
        $indexes = [
            'invoices' => [
                'idx_invoices_klient_nazwa',
                'idx_invoices_data_wystawienia',
                'idx_invoices_typ_wystawienia',
                'idx_invoices_status',
                'idx_invoices_order',
            ],
            'employee_vacations' => [
                'idx_employee_vacations_data_od_do',
                'idx_employee_vacations_employee',
            ],
            'employee_rates' => [
                'idx_employee_rates_data_do',
                'idx_employee_rates_employee',
            ],
            'order_equipment' => [
                'idx_order_equipment_equipment',
                'idx_order_equipment_order',
            ],
            'order_employees' => [
                'idx_order_employees_employee_rola',
                'idx_order_employees_rola',
                'idx_order_employees_employee',
                'idx_order_employees_order',
            ],
            'daily_vehicle_reports' => ['idx_dvr_equipment_data'],
            'daily_terminal_reports' => ['idx_dtr_terminal_data'],
            'equipment_history' => ['idx_equipment_history_equipment_data'],
            'incidents' => [
                'idx_incidents_zgloszona_przez',
                'idx_incidents_data_zgloszenia',
                'idx_incidents_equipment',
                'idx_incidents_status',
            ],
            'orders' => [
                'idx_orders_status_data_rozpoczecia',
                'idx_orders_data_zakonczenia',
                'idx_orders_data_rozpoczecia',
                'idx_orders_status',
                'idx_orders_terminal',
            ],
            'equipment' => [
                'idx_equipment_current_terminal',
                'idx_equipment_current_employee',
                'idx_equipment_kategoria',
                'idx_equipment_is_active',
            ],
            'employees' => [
                'idx_employees_nazwisko_imie',
                'idx_employees_current_sprzet',
                'idx_employees_current_terminal',
                'idx_employees_is_active',
            ],
        ];

        foreach ($indexes as $table => $names) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            foreach ($names as $name) {
                $this->dropIndex($pdo, $table, $name);
            }
        }
    }

    public function name(): string
    {
        return '20260830000000_add_performance_indexes';
    }

    /**
     * Dodaje indeks, jeśli nie istnieje.
     *
     * @param array<int, string> $columns
     */
    private function addIndex(PDO $pdo, string $table, string $name, array $columns): void
    {
        if (!$this->tableExists($pdo, $table)) {
            return;
        }
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $cols = implode('`, `', $columns);
        $this->execute($pdo, "ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$cols}`)");
    }

    private function dropIndex(PDO $pdo, string $table, string $name): void
    {
        if (!$this->indexExists($pdo, $table, $name)) {
            return;
        }

        $this->execute($pdo, "ALTER TABLE `{$table}` DROP INDEX `{$name}`");
    }

    private function indexExists(PDO $pdo, string $table, string $name): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        );
        $stmt->execute([$table, $name]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}

return new AddPerformanceIndexes();

