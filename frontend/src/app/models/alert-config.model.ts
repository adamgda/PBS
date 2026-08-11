/**
 * Modele danych sekcji Ustawienia → Alerty (frontend PBS, Etap 14).
 */

/** Dopuszczalne typy alertów (zgodne z ENUM w `alert_settings`). */
export const ALERT_TYPES = [
  'certyfikat_wygasa',
  'przeglad_wymagany',
  'brak_raportu_oc',
  'awaria_zgloszona',
] as const;

export type AlertType = (typeof ALERT_TYPES)[number];

export interface AlertConfig {
  id: number;
  email_odbiorcy: string;
  typ_alertu: AlertType;
  czy_aktywny: boolean;
  czas_wysylki: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface AlertConfigListResponse {
  data: AlertConfig[];
}

export interface AlertConfigPayload {
  email_odbiorcy: string;
  typ_alertu: AlertType;
  czy_aktywny: boolean;
  czas_wysylki?: string | null;
}
