/** Modele danych sekcji Faktury (frontend PBS) — Etap 7a. */

export type InvoiceStatus = 'wystawiona' | 'zaplacona' | 'przeterminowana';
export type InvoiceTypWystawienia = 'po_zleceniu' | 'po_tygodniu' | 'koniec_miesiaca';

export interface Invoice {
  id: number;
  order_id: number | null;
  numer_faktury: string;
  klient_nazwa: string;
  kwota_pln: number;
  data_wystawienia: string | null;
  termin_platnosci: string | null;
  status: InvoiceStatus;
  typ_wystawienia: InvoiceTypWystawienia;
  created_at: string | null;
  updated_at: string | null;
}

export interface InvoiceListResponse {
  data: Invoice[];
  total: number;
  page: number;
  per_page: number;
}

export interface InvoiceListParams {
  numer?: string;
  klient?: string;
  status?: string;
  typ_wystawienia?: string;
  date_from?: string;
  date_to?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateInvoiceRequest {
  order_id?: number | null;
  numer_faktury: string;
  klient_nazwa: string;
  kwota_pln: number;
  data_wystawienia: string;
  termin_platnosci?: string | null;
  status?: InvoiceStatus;
  typ_wystawienia: InvoiceTypWystawienia;
}

export interface UpdateInvoiceRequest extends CreateInvoiceRequest {}

export interface MissingInvoiceRow {
  order_id: number;
  numer_zlecenia: string | null;
  klient_nazwa: string | null;
  terminal_id: number | null;
  data_zakonczenia: string | null;
  wartosc_pln: number;
}

export interface MissingInvoicesResponse {
  data: MissingInvoiceRow[];
  total: number;
  page: number;
  per_page: number;
}