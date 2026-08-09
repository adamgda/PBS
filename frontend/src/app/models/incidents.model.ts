/**
 * Modele danych sekcji Awaria (frontend PBS) — Etap 10.
 */

export type IncidentType = 'sprzet' | 'inne';

export type IncidentStatus = 'zgloszona' | 'w_trakcie_naprawy' | 'naprawiona' | 'zamknieta';

export interface IncidentComment {
  id: number;
  incident_id: number;
  tresc: string;
  user_id: number;
  user_email: string | null;
  created_at: string | null;
}

export interface IncidentStatusHistory {
  id: number;
  incident_id: number;
  status_od: IncidentStatus;
  status_do: IncidentStatus;
  zmieniony_przez: number;
  zmieniony_przez_email: string | null;
  created_at: string | null;
}

export interface Incident {
  id: number;
  typ: IncidentType;
  equipment_id: number | null;
  equipment_nazwa: string | null;
  opis: string;
  status: IncidentStatus;
  data_zgloszenia: string | null;
  data_zakonczenia: string | null;
  zgloszona_przez: number;
  zgloszona_przez_email: string | null;
  created_at: string | null;
  updated_at: string | null;
  comments?: IncidentComment[];
  status_history?: IncidentStatusHistory[];
}

export interface IncidentListResponse {
  data: Incident[];
  total: number;
  page: number;
  per_page: number;
}

export interface IncidentListParams {
  typ?: string;
  status?: string;
  equipment_id?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateIncidentRequest {
  typ: IncidentType;
  equipment_id?: number | null;
  opis: string;
}

export interface ChangeIncidentStatusRequest {
  status: IncidentStatus;
}

export interface AddIncidentCommentRequest {
  tresc: string;
}