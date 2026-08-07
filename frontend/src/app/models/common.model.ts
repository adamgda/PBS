/**
 * Współdzielone modele TypeScript dla frontendu PBS.
 */

export interface Terminal {
  id: number;
  nazwa: string;
  adres: string;
  operator: string;
  telefon_operatora: string;
  email_operatora: string;
  is_active: boolean;
}

export interface Employee {
  id: number;
  imie: string;
  nazwisko: string;
  telefon: string;
  email: string;
  current_terminal?: Terminal;
  current_sprzet?: Equipment;
  is_active: boolean;
  documents?: EmployeeDocument[];
}

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  nazwa: string;
  numer_dokumentu: string;
  data_wydania: string;
  data_waznosci: string;
  is_expiring_soon?: boolean;
  is_expired?: boolean;
}

export interface Equipment {
  id: number;
  kategoria: 'pojazd' | 'inne';
  nazwa: string;
  numer_seryjny?: string;
  current_employee?: Employee;
  current_terminal?: Terminal;
  vehicle_details?: VehicleDetails;
  is_active: boolean;
}

export interface VehicleDetails {
  equipment_id: number;
  ostatni_przebieg: number;
  ostatni_serwis_olejowy?: string;
  ostatnia_awaria?: string;
  data_ostatniej_oc?: string;
  wynik_ostatniej_oc?: string;
}

export interface Order {
  id: number;
  numer_zlecenia: string;
  klient_nazwa: string;
  terminal: Terminal;
  data_rozpoczecia: string;
  data_zakonczenia: string;
  zakres_prac: string;
  wartosc_pln: number;
  status: 'nowe' | 'w_realizacji' | 'zakonczone';
  employees?: Employee[];
  equipment?: Equipment[];
}

export interface Incident {
  id: number;
  typ: 'sprzet' | 'inne';
  equipment?: Equipment;
  opis: string;
  status: 'zgloszona' | 'w_trakcie_naprawy' | 'naprawiona' | 'zamknieta';
  data_zgloszenia: string;
  data_zakonczenia?: string;
  downtime_hours?: number;
  comments?: IncidentComment[];
  status_history?: IncidentStatusHistory[];
}

export interface IncidentComment {
  id: number;
  incident_id: number;
  tresc: string;
  user_id: number;
  created_at: string;
}

export interface IncidentStatusHistory {
  id: number;
  incident_id: number;
  status_od: string;
  status_do: string;
  zmieniony_przez: number;
  created_at: string;
}

export interface DailyTerminalReport {
  id: number;
  terminal: Terminal;
  data_raportu: string;
  opis: string;
  uwagi?: string;
  employees?: Employee[];
  equipment?: Equipment[];
  orders?: Order[];
}

export interface DailyVehicleReport {
  id: number;
  equipment: Equipment;
  data_raportu: string;
  aktualny_przebieg: number;
  przebieg_oc: string;
  uwagi?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
}

export interface ApiError {
  message: string;
  code?: string;
  errors?: Record<string, string[]>;
}