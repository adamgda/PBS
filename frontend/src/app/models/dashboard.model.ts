/**
 * Modele danych sekcji Dashboard (frontend PBS) — Etap 13.
 */

export interface DashboardSummary {
  active_employees: number;
  active_terminals: number;
  vehicles_in_use: number;
  active_incidents: number;
  hours_today: number;
  employees_on_leave: number;
  monthly_wages: number;
}

export interface DashboardAlertItem {
  id: number;
  [key: string]: string | number | null;
}

export interface DashboardAlertGroup {
  count: number;
  items: DashboardAlertItem[];
}

export interface DashboardAlerts {
  expiring_certs: DashboardAlertGroup;
  upcoming_inspections: DashboardAlertGroup;
  unresolved_incidents: DashboardAlertGroup;
  returning_from_leave: DashboardAlertGroup;
}
