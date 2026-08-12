/**
 * Modele danych publicznych kodów QR dla maszyn (frontend PBS) — Etap 20.
 */

export interface QrMachine {
  id: number;
  kategoria: 'pojazd' | 'inne';
  nazwa: string;
  numer_seryjny: string | null;
  is_active: boolean;
}

export interface QrInfo {
  qr_token: string;
  public_url: string;
  qr_svg: string;
  machine: {
    id: number;
    nazwa: string;
    numer_seryjny: string | null;
    kategoria: string;
  };
}

export interface QrTokenResponse {
  id: number;
  kategoria: string;
  nazwa: string;
  numer_seryjny: string | null;
  is_active: boolean;
  qr_token: string;
  public_url: string;
}

export interface QrIncidentResponse {
  id: number;
  numer_zgloszenia: string;
  status: string;
}

export interface QrDailyReportResponse {
  id: number;
  equipment_id: number;
  data_raportu: string;
  status: string;
}

export interface CreateQrIncidentRequest {
  opis: string;
  kontakt?: string | null;
  typ?: 'sprzet' | 'inne';
}

export interface CreateQrDailyReportRequest {
  aktualny_przebieg: number;
  przebieg_oc: string;
  uwagi?: string | null;
  data_raportu?: string | null;
}
