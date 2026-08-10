/**
 * Modele danych sekcji Pracownicy (frontend PBS) — Etap 7.
 */

export interface Employee {
  id: number;
  imie: string;
  nazwisko: string;
  telefon: string | null;
  email: string | null;
  current_terminal_id: number | null;
  terminal_nazwa: string | null;
  current_sprzet_id: number | null;
  sprzet_nazwa: string | null;
  is_active: boolean;
  stawka_godzinowa: number;
  godziny_mc: number;
  wynagrodzenie: number;
  rola_dzis: EmployeeRole | null;
  on_leave: boolean;
  /** Podsumowanie najpilniejszego dokumentu (kolumna „Uprawnienia" w liście). */
  uprawnienie?: EmployeeUprawnienie;
  documents?: EmployeeDocument[];
  created_at: string | null;
  updated_at: string | null;
}

/** Podsumowanie uprawnień/certyfikatów pracownika wyświetlane w kolumnie „Uprawnienia". */
export interface EmployeeUprawnienie {
  nazwa: string | null;
  data_waznosci: string | null;
  dni: number | null;
  status: 'expired' | 'expiring' | 'ok' | 'none';
}

/** Rola dnia (przypisanie stanowiska na dany dzień/zlecenie). */
export type EmployeeRole =
  | 'operator'
  | 'brygadzista'
  | 'sztauer'
  | 'lukowy'
  | 'operator_zurawia';

export const EMPLOYEE_ROLES: EmployeeRole[] = [
  'operator',
  'brygadzista',
  'sztauer',
  'lukowy',
  'operator_zurawia',
];

/** Stawka godzinowa (historia zmian). */
export interface EmployeeRate {
  id: number;
  employee_id: number;
  stawka_godzinowa: number;
  data_od: string | null;
  data_do: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface CreateRateRequest {
  stawka_godzinowa: number;
  data_od: string;
}

/** Urlop pracownika. */
export type VacationType = 'wypoczynkowy' | 'na_zadanie' | 'L4';
export type VacationStatus = 'oczekujacy' | 'zatwierdzony' | 'odrzucony' | 'zrealizowany';

export interface EmployeeVacation {
  id: number;
  employee_id: number;
  data_od: string | null;
  data_do: string | null;
  typ: VacationType;
  status: VacationStatus;
  created_at: string | null;
  updated_at: string | null;
}

export interface CreateVacationRequest {
  data_od: string;
  data_do: string;
  typ: VacationType;
  status?: VacationStatus;
}

/** Rozliczenie per pracownik. */
export type SettlementPeriod = 'all' | '1-15' | '15-23';

export interface SettlementRow {
  employee_id: number;
  imie: string | null;
  nazwisko: string | null;
  rola?: string | null;
  godziny_1_15: number;
  godziny_15_23: number;
  godziny_total: number;
  wynagrodzenie: number;
}

export interface SettlementResponse {
  month: string;
  period: SettlementPeriod;
  data: SettlementRow[];
  total_godziny: number;
  total_wynagrodzenie: number;
}

export interface SettlementByPortRow {
  terminal_id: number | null;
  terminal_nazwa: string | null;
  liczba_pracownikow: number;
  suma_godzin: number;
  suma_wynagrodzen: number;
}

export interface SettlementByPortResponse {
  month: string;
  period: SettlementPeriod;
  data: SettlementByPortRow[];
}

export interface EmployeeSummary {
  month: string;
  godziny_total: number;
  wynagrodzenie_total: number;
  godziny_1_15: number;
  godziny_15_23: number;
  wynagrodzenie_1_15: number;
  wynagrodzenie_15_23: number;
  na_urlopie: number;
}

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  nazwa: string;
  numer_dokumentu: string | null;
  data_wydania: string | null;
  data_waznosci: string | null;
  plik: string | null;
  is_expired: boolean;
  is_expiring_soon: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface EmployeeListResponse {
  data: Employee[];
  total: number;
  page: number;
  per_page: number;
}

export interface EmployeeListParams {
  q?: string;
  imie?: string;
  nazwisko?: string;
  terminal_id?: string;
  sprzet_id?: string;
  is_active?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateEmployeeRequest {
  imie: string;
  nazwisko: string;
  telefon?: string | null;
  email?: string | null;
  current_terminal_id?: number | null;
  current_sprzet_id?: number | null;
  is_active?: boolean;
}

export interface UpdateEmployeeRequest extends CreateEmployeeRequest {}

export interface AssignEmployeeRequest {
  current_terminal_id?: number | null;
  current_sprzet_id?: number | null;
}

export interface CreateDocumentRequest {
  nazwa: string;
  numer_dokumentu?: string | null;
  data_wydania?: string | null;
  data_waznosci?: string | null;
}

export interface UpdateDocumentRequest {
  nazwa?: string;
  numer_dokumentu?: string | null;
  data_wydania?: string | null;
  data_waznosci?: string | null;
}