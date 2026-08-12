/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import {
  HttpClient,
  provideHttpClient,
  withInterceptors,
} from '@angular/common/http';
import {
  provideHttpClientTesting,
  HttpTestingController,
} from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';

import { httpInterceptor, invalidateCache } from './http.interceptor';
import { AuthService } from './auth.service';

/** Stub AuthService używany przez interceptor. */
const authStub = {
  getAccessToken: () => null as string | null,
  refresh: () => of({ access_token: 'new', refresh_token: 'nr', expires_in: 900 }),
  setRefreshing: (_v: boolean) => undefined,
  clearSession: () => undefined,
  refreshing: false,
};

describe('httpInterceptor', () => {
  let http: HttpClient;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    invalidateCache();
    (authStub as { getAccessToken: () => string | null }).getAccessToken = () => null;
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([httpInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: authStub },
      ],
    });
    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    invalidateCache();
  });

  it('dołącza nagłówek Authorization: Bearer, gdy jest token', () => {
    authStub.getAccessToken = () => 'jwt-token-123';
    http.get('/api/me').subscribe();

    const req = httpMock.expectOne('/api/me');
    expect(req.request.headers.get('Authorization')).toBe('Bearer jwt-token-123');
    req.flush({ ok: true });
  });

  it('nie dodaje Authorization, gdy token nie istnieje', () => {
    http.get('/api/public').subscribe();
    const req = httpMock.expectOne('/api/public');
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush({ ok: true });
  });

  it('buforuje odpowiedzi GET (drugie żądanie nie trafia do sieci)', () => {
    http.get('/api/items').subscribe();
    httpMock.expectOne('/api/items').flush({ data: [1, 2, 3] });

    // Drugie wywołanie — powinno wrócić z cache bez nowego requestu
    let result: unknown;
    http.get('/api/items').subscribe((r) => (result = r));
    expect(result).toEqual({ data: [1, 2, 3] });
    httpMock.expectNone('/api/items');
  });

  it('po wygaśnięciu cache ponawia żądanie GET', () => {
    // Wywołujemy invalidateCache (symuluje upływ TTL), potem nowe żądanie
    http.get('/api/items').subscribe();
    httpMock.expectOne('/api/items').flush({ data: [1] });

    invalidateCache('/api/items');
    http.get('/api/items').subscribe();
    httpMock.expectOne('/api/items').flush({ data: [2] });
  });

  it('po 401 odświeża token i ponawia oryginalne żądanie', () => {
    let token: string | null = 'jwt-token-123';
    authStub.getAccessToken = () => token;
    const refreshSpy = jasmine
      .createSpy('refresh')
      .and.callFake(() => {
        token = 'new-token';
        return of({ access_token: 'new-token', refresh_token: 'nr', expires_in: 900 });
      });
    authStub.refresh = refreshSpy;

    let response: unknown;
    http.get('/api/secure').subscribe((r) => (response = r));

    // Najpierw 401 na oryginalnym żądaniu
    httpMock.expectOne('/api/secure').flush({}, { status: 401, statusText: 'Unauthorized' });

    // Interceptor wywołał refresh
    expect(refreshSpy).toHaveBeenCalled();

    // Ponowienie oryginalnego żądania — teraz z nowym tokenem
    const retryReq = httpMock.expectOne('/api/secure');
    expect(retryReq.request.headers.get('Authorization')).toBe('Bearer new-token');
    retryReq.flush({ data: 'ok' });

    expect(response).toEqual({ data: 'ok' });
  });

  it('czyści sesję i rzuca błąd, gdy refresh również zawiedzie', () => {
    authStub.getAccessToken = () => 'jwt-token-123';
    authStub.refresh = () => throwError(() => new Error('refresh failed'));
    const clearSpy = jasmine.createSpy('clearSession');
    authStub.clearSession = clearSpy;

    let errored = false;
    http.get('/api/secure').subscribe({ error: () => (errored = true) });

    httpMock.expectOne('/api/secure').flush({}, { status: 401, statusText: 'Unauthorized' });

    expect(errored).toBe(true);
    expect(clearSpy).toHaveBeenCalled();
  });
});
