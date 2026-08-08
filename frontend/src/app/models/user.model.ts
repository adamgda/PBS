/**
 * Modele danych sekcji Ustawienia → Użytkownicy (frontend PBS).
 */

export type UserRole = 'super_admin' | 'admin' | 'user';

/** Dopuszczalne sekcje uprawnień (zgodne z menu PBS). */
export const PERMISSION_SECTIONS = [
  'dashboard',
  'pracownicy',
  'sprzet',
  'terminale',
  'harmonogram',
  'analityka',
  'raportowanie',
  'ustawienia',
  'awaria',
] as const;

export type PermissionSection = (typeof PERMISSION_SECTIONS)[number];

export type Permissions = Record<PermissionSection, boolean>;

export interface User {
  id: number;
  email: string;
  role: UserRole;
  permissions: Permissions;
  is_active: boolean;
  must_change_password: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface UserListResponse {
  data: User[];
  total: number;
  page: number;
  per_page: number;
}

export interface UserListParams {
  email?: string;
  role?: string;
  is_active?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateUserRequest {
  email: string;
  role: UserRole;
  permissions: Permissions;
}

export interface CreateUserResponse extends User {
  /** Obecny tylko w trybie debug (APP_DEBUG) — link set-password do testów dev. */
  set_password_url?: string;
}

export interface UpdateUserRequest {
  email: string;
  role: UserRole;
  is_active?: boolean;
}

export interface UpdatePermissionsRequest {
  permissions: Permissions;
}