/**
 * Modele danych sekcji Harmonogram / Zlecenia (frontend PBS) — Etap 9.
 */

export type OrderStatus = 'nowe' | 'w_realizacji' | 'zakonczone';

export interface OrderEmployee {
  id: number;
  order_id: number;
  employee_id: number | null;
  employee_name: string | null;
  employee_email: string | null;
  rola: string | null;
  godziny: number | null;
  stawka_godzinowa: number | null;
  wynagrodzenie: number;
}

export interface AssignEmployeeRequest {
  employee_id: number;
  rola?: string | null;
  godziny?: number | null;
}

export interface OrderEquipment {
  id: number;
  order_id: number;
  equipment_id: number;
  equipment_nazwa: string | null;
  equipment_numer_seryjny: string | null;
  equipment_kategoria: string | null;
}

export interface Order {
  id: number;
  numer_zlecenia: string;
  klient_nazwa: string;
  terminal_id: number;
  terminal_nazwa: string | null;
  data_rozpoczecia: string | null;
  data_zakonczenia: string | null;
  zakres_prac: string;
  wartosc_pln: number;
  status: OrderStatus;
  created_at: string | null;
  updated_at: string | null;
  employees?: OrderEmployee[];
  equipment?: OrderEquipment[];
}

export interface OrderListResponse {
  data: Order[];
  total: number;
  page: number;
  per_page: number;
}

export interface OrderListParams {
  numer?: string;
  klient?: string;
  terminal_id?: string;
  status?: string;
  date_from?: string;
  date_to?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateOrderRequest {
  numer_zlecenia: string;
  klient_nazwa: string;
  terminal_id: number;
  data_rozpoczecia: string;
  data_zakonczenia: string;
  zakres_prac: string;
  wartosc_pln: number;
  status?: OrderStatus;
}

export interface UpdateOrderRequest extends CreateOrderRequest {}

export interface CopyWeekRequest {
  source_week_start: string;
  target_week_start: string;
}