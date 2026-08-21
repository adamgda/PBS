/**
 * Modele danych sekcji „Eksport danych" (frontend PBS).
 *
 * Typy eksportu odpowiadają bieliście endpointów backendu:
 * GET /api/v1/exports/{type}?from=&to=
 */

export type ExportType = 'orders' | 'employees' | 'equipment' | 'incidents' | 'daily_reports';

export interface ExportParams {
  /** Data początkowa zakresu (YYYY-MM-DD) — opcjonalna. */
  from?: string;
  /** Data końcowa zakresu (YYYY-MM-DD) — opcjonalna. */
  to?: string;
}

/** Opis zestawu eksportu dla widoku (karta w UI). */
export interface ExportDataset {
  type: ExportType;
  icon: string;
  titleKey: string;
  descKey: string;
}
