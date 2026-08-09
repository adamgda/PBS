import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Terminal,
  TerminalListResponse,
  TerminalListParams,
  CreateTerminalRequest,
  UpdateTerminalRequest,
} from '../models/terminal.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Terminale (Etap 6).
 * CRUD terminali: lista (paginacja + filtry), pobranie, dodanie, edycja, usunięcie.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /terminals.
 */
@Injectable({ providedIn: 'root' })
export class TerminalsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: TerminalListParams): Observable<TerminalListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<TerminalListResponse>(`${this.apiUrl}/terminals`, { params: httpParams });
  }

  get(id: number): Observable<Terminal> {
    return this.http.get<Terminal>(`${this.apiUrl}/terminals/${id}`);
  }

  create(payload: CreateTerminalRequest): Observable<Terminal> {
    return this.http
      .post<Terminal>(`${this.apiUrl}/terminals`, payload)
      .pipe(tap(() => invalidateCache('/terminals')));
  }

  update(id: number, payload: UpdateTerminalRequest): Observable<Terminal> {
    return this.http
      .put<Terminal>(`${this.apiUrl}/terminals/${id}`, payload)
      .pipe(tap(() => invalidateCache('/terminals')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/terminals/${id}`)
      .pipe(tap(() => invalidateCache('/terminals')));
  }
}