/**
 * Modele danych sekcji Terminale (frontend PBS) — Etap 6.
 */

export interface Terminal {
  id: number;
  nazwa: string;
  adres: string;
  operator: string;
  telefon_operatora: string | null;
  email_operatora: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface TerminalListResponse {
  data: Terminal[];
  total: number;
  page: number;
  per_page: number;
}

export interface TerminalListParams {
  nazwa?: string;
  operator?: string;
  is_active?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateTerminalRequest {
  nazwa: string;
  adres: string;
  operator: string;
  telefon_operatora?: string | null;
  email_operatora?: string | null;
  is_active?: boolean;
}

export interface UpdateTerminalRequest extends CreateTerminalRequest {}

export interface TerminalHoursRow {
  terminal_id: number | null;
  terminal_nazwa: string | null;
  liczba_pracownikow: number;
  suma_godzin: number;
  suma_wynagrodzen: number;
}

export interface TerminalHoursSummary {
  month: string;
  period: string;
  data: TerminalHoursRow[];
}