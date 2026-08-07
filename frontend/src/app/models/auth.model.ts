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

export interface ForgotPasswordRequest {
  email: string;
}

export interface ForgotPasswordResponse {
  message: string;
  /** Obecny tylko w trybie debug (APP_DEBUG=true) — link resetujący do testów dev. */
  token?: string;
  reset_url?: string;
}

export interface SetPasswordRequest {
  token: string;
  password: string;
}

export interface SetPasswordResponse {
  success: boolean;
}