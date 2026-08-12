/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { Router } from '@angular/router';

import { AuthService } from './auth.service';
import { environment } from '../../environments/environment';
import { LoginResponse, RefreshResponse, AuthUser } from '../models/auth.model';

/** Stub Routera — nawigacja jest spy. */
class RouterStub {
  navigateByUrl = jasmine.createSpy('navigateByUrl');
  navigate = jasmine.createSpy('navigate');
}

const USER: AuthUser = {
  id: 1,
  email: 'jan.kowalski@pbs.local',
  role: 'admin',
  permissions: { dashboard: true, pracownicy: true, awarie: false },
  is_active: true,
};

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let router: RouterStub;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        AuthService,
        { provide: Router, useClass: RouterStub },
      ],
    });
    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router) as unknown as RouterStub;
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  it('login() wysyła POST /auth/login i zapisuje użytkownika oraz tokeny', () => {
    service.login({ email: 'jan.kowalski@pbs.local', password: 'x' }).subscribe((res) => {
      expect(res.user.email).toBe('jan.kowalski@pbs.local');
    });

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
    expect(req.request.method).toBe('POST');
    const body: LoginResponse = {
      access_token: 'at1',
      refresh_token: 'rt1',
      expires_in: 900,
      user: USER,
    };
    req.flush(body);

    expect(service.currentUser).toEqual(USER);
    expect(service.isLoggedIn()).toBe(true);
    expect(service.getAccessToken()).toBe('at1');
    expect(service.getRefreshToken()).toBe('rt1');
    expect(localStorage.getItem('pbs_user')).toBe(JSON.stringify(USER));
  });

  it('refresh() wysyła POST /auth/refresh z refresh_token i aktualizuje tokeny', () => {
    localStorage.setItem('pbs_refresh_token', 'old-refresh');
    service.refresh().subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/refresh`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ refresh_token: 'old-refresh' });
    const res: RefreshResponse = { access_token: 'new-at', refresh_token: 'new-rt', expires_in: 900 };
    req.flush(res);

    expect(service.getAccessToken()).toBe('new-at');
    expect(service.getRefreshToken()).toBe('new-rt');
  });

  it('refresh() czyści sesję i rzuca błąd, gdy odświeżenie się nie powiedzie', () => {
    localStorage.setItem('pbs_refresh_token', 'old-refresh');
    let errored = false;
    service.refresh().subscribe({ error: () => (errored = true) });

    httpMock.expectOne(`${environment.apiUrl}/auth/refresh`).flush(
      { error: 'invalid' },
      { status: 401, statusText: 'Unauthorized' },
    );

    expect(errored).toBe(true);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
    expect(service.isLoggedIn()).toBe(false);
  });

  it('logout() wysyła POST /auth/logout i czyści sesję', () => {
    localStorage.setItem('pbs_access_token', 'at');
    service.logout().subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/logout`);
    expect(req.request.method).toBe('POST');
    req.flush({ success: true });

    expect(service.isLoggedIn()).toBe(false);
    expect(service.getAccessToken()).toBeNull();
  });

  it('forgotPassword() wysyła POST /auth/forgot-password', () => {
    service.forgotPassword('jan@pbs.local').subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/forgot-password`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ email: 'jan@pbs.local' });
    req.flush({ message: 'ok' });
  });

  it('clearSession() usuwa dane lokalne i przekierowuje na /login', () => {
    localStorage.setItem('pbs_access_token', 'at');
    localStorage.setItem('pbs_refresh_token', 'rt');
    localStorage.setItem('pbs_user', JSON.stringify(USER));
    localStorage.setItem('pbs_token_expiry', '9999999999999');

    service.clearSession();

    expect(service.getAccessToken()).toBeNull();
    expect(service.getRefreshToken()).toBeNull();
    expect(service.isLoggedIn()).toBe(false);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
  });

  it('hasPermission() daje super_admin dostęp do wszystkiego', () => {
    service['_currentUser'].set({ ...USER, role: 'super_admin', permissions: {} });
    expect(service.hasPermission('cokolwiek')).toBe(true);
  });

  it('hasPermission() zwraca false dla niezalogowanego użytkownika', () => {
    service['_currentUser'].set(null);
    expect(service.hasPermission('pracownicy')).toBe(false);
  });

  it('hasPermission() sprawdza uprawnienia sekcji', () => {
    service['_currentUser'].set(USER);
    expect(service.hasPermission('pracownicy')).toBe(true);
    expect(service.hasPermission('awarie')).toBe(false);
  });

  it('hasRole() sprawdza przynależność do roli', () => {
    service['_currentUser'].set(USER);
    expect(service.hasRole('admin')).toBe(true);
    expect(service.hasRole('user', 'super_admin')).toBe(false);
    expect(service.hasRole('super_admin')).toBe(false);
  });

  it('isTokenExpiringSoon() zwraca true, gdy token jest bliski wygaśnięcia', () => {
    localStorage.setItem('pbs_token_expiry', (Date.now() + 5000).toString());
    expect(service.isTokenExpiringSoon()).toBe(true);
  });

  it('isTokenExpiringSoon() zwraca false przy braku wygaśnięcia', () => {
    localStorage.setItem('pbs_token_expiry', (Date.now() + 60_000_000).toString());
    expect(service.isTokenExpiringSoon()).toBe(false);
  });

  it('refreshing — getter i setter', () => {
    expect(service.refreshing).toBe(false);
    service.setRefreshing(true);
    expect(service.refreshing).toBe(true);
  });
});
