import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  AlertConfig,
  AlertConfigListResponse,
  AlertConfigPayload,
} from '../models/alert-config.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Ustawienia → Alerty (Etap 14).
 * CRUD konfiguracji alertów: odbiorcy e-mail, typy, aktywność, czas wysyłki.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor).
 */
@Injectable({ providedIn: 'root' })
export class AlertConfigsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(): Observable<AlertConfigListResponse> {
    return this.http.get<AlertConfigListResponse>(`${this.apiUrl}/settings/alert-configs`);
  }

  create(payload: AlertConfigPayload): Observable<AlertConfig> {
    return this.http
      .post<AlertConfig>(`${this.apiUrl}/settings/alert-configs`, payload)
      .pipe(tap(() => invalidateCache('/settings/alert-configs')));
  }

  update(id: number, payload: AlertConfigPayload): Observable<AlertConfig> {
    return this.http
      .put<AlertConfig>(`${this.apiUrl}/settings/alert-configs/${id}`, payload)
      .pipe(tap(() => invalidateCache('/settings/alert-configs')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/settings/alert-configs/${id}`)
      .pipe(tap(() => invalidateCache('/settings/alert-configs')));
  }
}
