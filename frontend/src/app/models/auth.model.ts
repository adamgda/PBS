/**
 * Modele danych autentykacji dla frontendu PBS.
 */

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in: number;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse extends AuthTokens {
  user: AuthUser;
}

export interface AuthUser {
  id: number;
  email: string;
  role: 'super_admin' | 'admin' | 'user';
  permissions: Record<string, boolean>;
  is_active: boolean;
}

export interface RefreshResponse extends AuthTokens {}