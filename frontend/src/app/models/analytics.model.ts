/**
 * Modele danych sekcji Analityka (frontend PBS) — Etap 12.
 */

export interface AnalyticsOverview {
  date_from: string;
  date_to: string;
  total_orders: number;
  total_hours: number;
  total_wages: number;
  total_value: number;
  total_incidents: number;
  incident_downtime_hours: number;
}

export interface AnalyticsTerminal {
  terminal_id: number;
  nazwa: string | null;
  order_count: number;
  total_hours: number;
}

export interface AnalyticsEmployee {
  employee_id: number;
  imie: string | null;
  nazwisko: string | null;
  total_hours: number;
  total_wages: number;
  rola: string | null;
}

export interface AnalyticsEquipment {
  equipment_id: number;
  nazwa: string | null;
  kategoria: string | null;
  assignment_count: number;
}

export interface AnalyticsRelation {
  employee_id: number;
  imie: string | null;
  nazwisko: string | null;
  assignment_count: number;
  terminal_nazwa: string | null;
  equipment_nazwa: string | null;
  total_hours: number;
}

export interface AnalyticsListResponse<T> {
  date_from: string;
  date_to: string;
  data: T[];
}

export interface AnalyticsRangeParams {
  date_from?: string;
  date_to?: string;
}
