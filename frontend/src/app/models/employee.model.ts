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
  documents?: EmployeeDocument[];
  created_at: string | null;
  updated_at: string | null;
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