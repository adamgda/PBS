/**
 * Modele danych logów audytowych (Dashboard → Logi audytowe, wyłącznie super_admin).
 */

export interface AuditLog {
  id: number;
  user_id: number | null;
  /** E-mail użytkownika (dołączony z tabeli `users`); null gdy wpis systemowy/anonimowy. */
  user_email: string | null;
  action: string;
  resource_type: string | null;
  resource_id: number | null;
  ip_address: string | null;
  user_agent: string | null;
  details: Record<string, unknown> | null;
  created_at: string | null;
}

export interface AuditLogListResponse {
  data: AuditLog[];
  total: number;
  page: number;
  per_page: number;
}

export interface AuditLogListParams {
  action?: string;
  user_email?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface ClearAuditLogsResponse {
  success: boolean;
  cleared: number;
}
