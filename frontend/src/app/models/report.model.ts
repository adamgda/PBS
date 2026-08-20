/**
 * Modele danych sekcji Raportowanie (frontend PBS) — Etap 11.
 */

export interface TerminalReportAutoDataOrder {
  id: number;
  numer_zlecenia: string;
  klient_nazwa: string;
  data_rozpoczecia: string | null;
  data_zakonczenia: string | null;
  status: string;
}

export interface TerminalReportAutoDataEmployee {
  employee_id: number;
  employee_name: string | null;
  rola: string | null;
  godziny: number | null;
  stawka_godzinowa: number | null;
  wynagrodzenie: number;
  order_id: number;
  order_number: string;
}

export interface TerminalReportAutoDataEquipment {
  equipment_id: number;
  equipment_nazwa: string | null;
  equipment_numer_seryjny: string | null;
  equipment_kategoria: string | null;
}

export interface TerminalReportAutoData {
  orders: TerminalReportAutoDataOrder[];
  employees: TerminalReportAutoDataEmployee[];
  equipment: TerminalReportAutoDataEquipment[];
  total_hours: number;
  total_wages: number;
}

/** Odpowiedź GET /reports/terminal/auto-data — auto-dane dla nowego raportu (bez zapisu). */
export interface TerminalReportAutoDataResponse {
  terminal_id: number;
  terminal_nazwa: string | null;
  data_raportu: string;
  auto_data: TerminalReportAutoData;
}

export interface TerminalReport {
  id: number;
  terminal_id: number;
  terminal_nazwa: string | null;
  data_raportu: string | null;
  opis: string;
  uwagi: string | null;
  utworzony_przez: number;
  utworzony_przez_email: string | null;
  created_at: string | null;
  updated_at: string | null;
  auto_data?: TerminalReportAutoData;
}

export interface VehicleReport {
  id: number;
  equipment_id: number;
  equipment_nazwa: string | null;
  equipment_numer_seryjny: string | null;
  equipment_kategoria: string | null;
  data_raportu: string | null;
  aktualny_przebieg: number;
  przebieg_oc: string;
  uwagi: string | null;
  utworzony_przez: number | null;
  utworzony_przez_email: string | null;
  zrodlo: 'panel' | 'qr';
  created_at: string | null;
  updated_at: string | null;
}

export interface ReportListResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
}

export interface TerminalReportListParams {
  terminal_id?: string;
  date_from?: string;
  date_to?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface VehicleReportListParams {
  equipment_id?: string;
  date_from?: string;
  date_to?: string;
  zrodlo?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateTerminalReportRequest {
  terminal_id: number;
  data_raportu: string;
  opis: string;
  uwagi?: string | null;
}

export interface UpdateTerminalReportRequest extends CreateTerminalReportRequest {}

export interface CreateVehicleReportRequest {
  equipment_id: number;
  data_raportu: string;
  aktualny_przebieg: number;
  przebieg_oc: string;
  uwagi?: string | null;
}

export interface UpdateVehicleReportRequest extends CreateVehicleReportRequest {}
