import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, throwError, BehaviorSubject } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  LoginRequest,
  LoginResponse,
  RefreshResponse,
  AuthUser,
  ForgotPasswordResponse,
  SetPasswordResponse,
} from '../models/auth.model';

/**
 * Serwis autentykacji — zarządzanie tokenami JWT, użytkownikiem i sesją.
 *
 * Strategia przechowywania:
 *  - Preferowany: HttpOnly + Secure + SameSite=Strict cookie ustawiany przez backend.
 *  - Fallback (dev/brak cookie): access/refresh token w localStorage (z ochroną przez CSP).
 *  - Zabronione: sessionStorage.
 *
 * Dokumentacja: docs/technical-documentation.md — sekcja 9.4 JWT Security.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly apiUrl = environment.apiUrl;

  /** Signal z aktualnym użytkownikiem (null = niezalogowany) */
  private readonly _currentUser = signal<AuthUser | null>(this.loadUserFromStorage());

  /** Signal flagi zalogowania (computed z _currentUser) */
  readonly isLoggedIn = computed(() => this._currentUser() !== null);

  /** BehaviorSubject z aktualnym użytkownikiem (kompatybilność z RxJS) */
  readonly currentUser$ = new BehaviorSubject<AuthUser | null>(this._currentUser());

  /** Czy trwa proces odświeżania tokena (zapobieganie równoległym refreshom) */
  private isRefreshing = false;

  /**
   * Logowanie — POST /auth/login.
   * Backend ustawia HttpOnly cookie z tokenami (jeśli skonfigurowane).
   * Frontend przechowuje dodatkowo usera w localStorage dla szybkiego stanu UI.
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/auth/login`, credentials).pipe(
      tap((res) => this.handleAuthSuccess(res)),
      catchError((err) => throwError(() => err)),
    );
  }

  /**
   * Odświeżenie tokena — POST /auth/refresh.
   * Wywoływane przez interceptor przy 401 lub proaktywnie przed wygaśnięciem.
   */
  refresh(): Observable<RefreshResponse> {
    return this.http.post<RefreshResponse>(`${this.apiUrl}/auth/refresh`, { refresh_token: this.getRefreshToken() }).pipe(
      tap((res) => this.storeTokens(res)),
      catchError((err) => {
        // Token wygasł/odrzucony — natychmiast czyścimy sesję (nie ma sensu
        // wysyłać logout, bo refresh token jest już nieważny).
        this.clearSession();
        return throwError(() => err);
      }),
    );
  }

  /**
   * Wylogowanie — POST /auth/logout + wyczyszczenie stanu lokalnego.
   */
  logout(): Observable<unknown> {
    return this.http.post(`${this.apiUrl}/auth/logout`, { refresh_token: this.getRefreshToken() }).pipe(
      tap(() => this.clearSession()),
      catchError(() => {
        // Nawet jeśli request się nie powiedzie, czyścimy stan lokalny
        this.clearSession();
        return throwError(() => new Error('Logout failed'));
      }),
    );
  }

  /**
   * Prośba o reset hasła — POST /auth/forgot-password.
   * Backend zawsze zwraca 200 (nie ujawnia czy e-mail istnieje w bazie).
   */
  forgotPassword(email: string): Observable<ForgotPasswordResponse> {
    return this.http.post<ForgotPasswordResponse>(`${this.apiUrl}/auth/forgot-password`, { email });
  }

  /**
   * Ustawienie nowego hasła — POST /auth/set-password.
   * Token pochodzi z linku w e-mailu resetującym (query param `?token=...`).
   * Limit: 3 próby/h na token (wymuszane przez backend).
   */
  setPassword(token: string, password: string): Observable<SetPasswordResponse> {
    return this.http.post<SetPasswordResponse>(`${this.apiUrl}/auth/set-password`, { token, password });
  }

  /**
   * Natychmiastowe wyczyszczenie sesji (bez zapytania do API).
   * Używane przy wygaszeniu tokena lub ręcznym wylogowaniu.
   */
  clearSession(): void {
    localStorage.removeItem('pbs_access_token');
    localStorage.removeItem('pbs_refresh_token');
    localStorage.removeItem('pbs_user');
    localStorage.removeItem('pbs_token_expiry');
    this._currentUser.set(null);
    this.currentUser$.next(null);
    this.router.navigateByUrl('/login');
  }

  /**
   * Czy dostęp do danej sekcji jest dozwolony dla aktualnego użytkownika.
   * super_admin ma dostęp do wszystkiego.
   */
  hasPermission(section: string): boolean {
    const user = this._currentUser();
    if (!user) return false;
    if (user.role === 'super_admin') return true;
    return user.permissions[section] === true;
  }

  /**
   * Czy użytkownik ma jedną z ról.
   */
  hasRole(...roles: string[]): boolean {
    const user = this._currentUser();
    if (!user) return false;
    return roles.includes(user.role);
  }

  /**
   * Zwraca pierwszą trasę sekcji, do której aktualny użytkownik ma dostęp
   * (kolejność zgodna z menu). super_admin zawsze trafia na dashboard.
   * Używane przez PermissionGuard oraz trasę domyślną/wildcard, aby użytkownik
   * bez uprawnienia do dashboard nie był zapętlany na /dashboard.
   */
  firstAvailableRoute(): string {
    if (!this.isLoggedIn()) return '/login';
    const user = this._currentUser();
    if (!user) return '/login';
    if (user.role === 'super_admin') return '/dashboard';

    const sectionRoutes: Array<[string, string]> = [
      ['dashboard', '/dashboard'],
      ['pracownicy', '/pracownicy'],
      ['sprzet', '/sprzet'],
      ['terminale', '/terminale'],
      ['harmonogram', '/harmonogram'],
      ['analityka', '/analytics'],
      ['raportowanie', '/reporting'],
      ['awaria', '/awaria'],
      ['ustawienia', '/settings'],
    ];

    for (const [section, route] of sectionRoutes) {
      if (user.permissions[section] === true) return route;
    }

    // Zalogowany użytkownik bez żadnego uprawnienia sekcji — bezpieczny terminal
    // (strona logowania, bez PermissionGuard, więc brak pętli).
    return '/login';
  }

  /**
   * Zwraca aktualny access token (do dołączenia w nagłówku Authorization).
   * Jeśli tokeny są w HttpOnly cookie, zwracamy null — backend odczyta cookie.
   */
  getAccessToken(): string | null {
    return localStorage.getItem('pbs_access_token');
  }

  /**
   * Zwraca bieżący refresh token (do wysłania w body /auth/refresh oraz /auth/logout).
   * Backend nie używa cookie — token przechowywany jest w localStorage.
   */
  getRefreshToken(): string | null {
    return localStorage.getItem('pbs_refresh_token');
  }

  /**
   * Czy access token wkrótce wygaśnie (lub już wygasł)?
   * Używane przez interceptor do proaktywnego refresh.
   */
  isTokenExpiringSoon(): boolean {
    const expiryStr = localStorage.getItem('pbs_token_expiry');
    if (!expiryStr) return false;
    const expiry = parseInt(expiryStr, 10);
    const now = Date.now();
    const threshold = environment.refreshBeforeExpirySeconds * 1000;
    return now + threshold >= expiry;
  }

  /**
   * Flaga trwania refresh — zapobieganie kaskadom refresh przy równoległych 401.
   */
  get refreshing(): boolean {
    return this.isRefreshing;
  }

  setRefreshing(value: boolean): void {
    this.isRefreshing = value;
  }

  /** Aktualny użytkownik (signal) */
  get currentUser(): AuthUser | null {
    return this._currentUser();
  }

  // --- Metody pomocnicze ---

  /**
   * Odświeża aktualnego użytkownika (rola, uprawnienia) z backendu (GET /auth/me).
   * Backend jest jedynym źródłem prawdy — dzięki temu menu i guardy zawsze
   * odzwierciedlają aktualne uprawnienia po ich zmianie w adminie, bez re-logowania.
   */
  refreshCurrentUser(): Observable<AuthUser> {
    return this.http.get<AuthUser>(`${this.apiUrl}/auth/me`).pipe(
      tap((user) => this.setCurrentUser(user)),
    );
  }

  /** Ustawia aktualnego użytkownika w signalach i localStorage. */
  private setCurrentUser(user: AuthUser): void {
    this._currentUser.set(user);
    this.currentUser$.next(user);
    localStorage.setItem('pbs_user', JSON.stringify(user));
  }

  private handleAuthSuccess(res: LoginResponse): void {
    this.storeTokens(res);
    this._currentUser.set(res.user);
    this.currentUser$.next(res.user);
    localStorage.setItem('pbs_user', JSON.stringify(res.user));
  }

  private storeTokens(tokens: RefreshResponse): void {
    // Jeśli backend ustawia HttpOnly cookie, nie musimy przechowywać tokenów lokalnie.
    // Fallback dla dev: przechowujemy w localStorage.
    localStorage.setItem('pbs_access_token', tokens.access_token);
    localStorage.setItem('pbs_refresh_token', tokens.refresh_token);
    const expiry = Date.now() + (tokens.expires_in ?? 900) * 1000;
    localStorage.setItem('pbs_token_expiry', expiry.toString());
  }

  private loadUserFromStorage(): AuthUser | null {
    try {
      const userStr = localStorage.getItem('pbs_user');
      return userStr ? (JSON.parse(userStr) as AuthUser) : null;
    } catch {
      return null;
    }
  }
}