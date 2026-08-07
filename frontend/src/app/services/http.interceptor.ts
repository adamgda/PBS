import { HttpInterceptorFn, HttpRequest, HttpHandlerFn, HttpErrorResponse, HttpResponse, HttpEvent } from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable, throwError, of, tap, BehaviorSubject, switchMap, filter, take, catchError, timeout, retry } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

/**
 * Cache entry — prosty cache GET w pamięci z TTL 60 s (frontend cache).
 */
interface CacheEntry {
  response: HttpResponse<unknown>;
  expiresAt: number;
}

const cache = new Map<string, CacheEntry>();
const CACHE_TTL = environment.cacheTtl;

/**
 * Główny HTTP Interceptor PBS.
 *
 * 1. Dołączanie JWT (Authorization: Bearer)
 * 2. Automatyczny refresh przy 401 (z zapobieganiem kaskadom)
 * 3. Timeout 10 s dla każdego żądania
 * 4. Retry (1 próba) z exponential backoff dla błędów sieciowych 5xx
 * 5. Cache GET z TTL 60 s (stale-while-revalidate)
 */
export const httpInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
): Observable<HttpEvent<unknown>> => {
  const authService = inject(AuthService);

  // 1. Dołączenie JWT
  const token = authService.getAccessToken();
  let authReq = req;
  if (token && !req.headers.has('Authorization')) {
    authReq = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
      withCredentials: true,
    });
  } else if (!req.headers.has('Authorization')) {
    authReq = req.clone({ withCredentials: true });
  }

  // 2. Cache tylko dla GET
  const cacheKey = req.urlWithParams;
  if (req.method === 'GET' && cache.has(cacheKey)) {
    const entry = cache.get(cacheKey)!;
    if (entry.expiresAt > Date.now()) {
      return of(entry.response.clone());
    }
    cache.delete(cacheKey);
  }

  const maxAttempts = environment.httpRetryAttempts;

  const handleRequest = (request: HttpRequest<unknown>): Observable<HttpEvent<unknown>> => {
    return next(request).pipe(
      timeout(environment.httpTimeout),
      retry({
        count: maxAttempts,
        delay: (error: unknown, retryCount: number) => {
          if (error instanceof HttpErrorResponse) {
            if (error.status === 0 || error.status >= 500) {
              const backoffMs = Math.pow(2, retryCount) * 1000;
              return of(true).pipe(
                tap(() => console.warn(`[PBS] Retry ${retryCount} po ${backoffMs}ms dla ${request.url}`)),
                switchMap(() => throwError(() => error)),
              );
            }
          }
          return throwError(() => error);
        },
      }),
      tap((event) => {
        if (event instanceof HttpResponse && request.method === 'GET') {
          const expiresAt = Date.now() + CACHE_TTL;
          cache.set(cacheKey, { response: event, expiresAt });
        }
      }),
      catchError((error: HttpErrorResponse) => {
        if (error.status === 401 && !request.url.includes('/auth/')) {
          return handle401Error(request, next, authService, handleRequest);
        }
        return throwError(() => error);
      }),
    );
  };

  return handleRequest(authReq);
};

/**
 * Obsługa 401 — automatyczny refresh tokena i ponowienie żądania.
 */
const refreshSubject = new BehaviorSubject<boolean>(false);

function handle401Error(
  request: HttpRequest<unknown>,
  next: HttpHandlerFn,
  authService: AuthService,
  retryRequest: (req: HttpRequest<unknown>) => Observable<HttpEvent<unknown>>,
): Observable<HttpEvent<unknown>> {
  if (authService.refreshing) {
    return refreshSubject.pipe(
      filter((ok) => ok),
      take(1),
      switchMap(() => retryRequest(cloneWithNewToken(request, authService))),
      catchError(() => {
        authService.clearSession();
        return throwError(() => new Error('Session expired'));
      }),
    );
  }

  authService.setRefreshing(true);
  refreshSubject.next(false);

  return authService.refresh().pipe(
    switchMap(() => {
      authService.setRefreshing(false);
      refreshSubject.next(true);
      return retryRequest(cloneWithNewToken(request, authService));
    }),
    catchError((err) => {
      authService.setRefreshing(false);
      refreshSubject.next(false);
      authService.clearSession();
      return throwError(() => err);
    }),
  );
}

function cloneWithNewToken(req: HttpRequest<unknown>, authService: AuthService): HttpRequest<unknown> {
  const token = authService.getAccessToken();
  if (token) {
    return req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }
  return req;
}

/**
 * Ręczne czyszczenie cache (np. po mutacji — invalidacja tagów).
 */
export function invalidateCache(urlPattern?: string): void {
  if (!urlPattern) {
    cache.clear();
    return;
  }
  for (const key of cache.keys()) {
    if (key.includes(urlPattern)) {
      cache.delete(key);
    }
  }
}