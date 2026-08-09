/**
 * Modele danych sekcji Sprzęt (frontend PBS) — Etap 8.
 */

export type EquipmentCategory = 'pojazd' | 'inne';

export interface Equipment {
  id: number;
  kategoria: EquipmentCategory;
  nazwa: string;
  numer_seryjny: string | null;
  current_employee_id: number | null;
  employee_name: string | null;
  current_terminal_id: number | null;
  terminal_nazwa: string | null;
  ostatni_przebieg: number | null;
  is_active: boolean;
  vehicle_details?: VehicleDetails | null;
  service_plans?: ServicePlan[];
  timeline?: EquipmentHistory[];
  created_at: string | null;
  updated_at: string | null;
}

export interface VehicleDetails {
  equipment_id: number;
  ostatni_przebieg: number;
  ostatni_serwis_olejowy: string | null;
  ostatnia_awaria: string | null;
  data_ostatniej_oc: string | null;
  wynik_ostatniej_oc: string | null;
}

export interface ServicePlan {
  id: number;
  equipment_id: number;
  typ_przegladu: string;
  interwal_km: number | null;
  interwal_dni: number | null;
  data_ostatniego_wykonania: string | null;
  data_nastepnego_planowanego: string | null;
  is_active: boolean;
  needs_service: boolean;
}

export type EquipmentHistoryType = 'przebieg' | 'serwis' | 'awaria' | 'przypisanie' | 'inne';

export interface EquipmentHistory {
  id: number;
  equipment_id: number;
  typ: EquipmentHistoryType;
  opis: string;
  data: string | null;
  created_by: number | null;
}

export interface EquipmentListResponse {
  data: Equipment[];
  total: number;
  page: number;
  per_page: number;
}

export interface EquipmentListParams {
  nazwa?: string;
  kategoria?: string;
  employee_id?: string;
  terminal_id?: string;
  is_active?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateEquipmentRequest {
  kategoria: EquipmentCategory;
  nazwa: string;
  numer_seryjny?: string | null;
  current_employee_id?: number | null;
  current_terminal_id?: number | null;
  is_active?: boolean;
  // Dane pojazdu (tylko dla kategorii „pojazd")
  ostatni_przebieg?: number;
  ostatni_serwis_olejowy?: string | null;
  ostatnia_awaria?: string | null;
  data_ostatniej_oc?: string | null;
  wynik_ostatniej_oc?: string | null;
}

export interface UpdateEquipmentRequest extends CreateEquipmentRequest {}

export interface AssignEquipmentRequest {
  current_employee_id?: number | null;
  current_terminal_id?: number | null;
}

export interface CreateServicePlanRequest {
  typ_przegladu: string;
  interwal_km?: number | null;
  interwal_dni?: number | null;
  data_ostatniego_wykonania?: string | null;
  data_nastepnego_planowanego?: string | null;
  is_active?: boolean;
}

export interface UpdateServicePlanRequest extends CreateServicePlanRequest {}