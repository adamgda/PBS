import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuditLogListParams, AuditLogListResponse, ClearAuditLogsResponse } from '../models/audit-log.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis logów audytowych (Dashboard → Logi audytowe).
 * Dostęp wyłącznie dla super_admin — wymuszane po stronie backendu.
 */
@Injectable({ providedIn: 'root' })
export class AuditLogsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: AuditLogListParams): Observable<AuditLogListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<AuditLogListResponse>(`${this.apiUrl}/audit-logs`, { params: httpParams });
  }

  clear(): Observable<ClearAuditLogsResponse> {
    return this.http
      .delete<ClearAuditLogsResponse>(`${this.apiUrl}/audit-logs`)
      .pipe(tap(() => invalidateCache('/audit-logs')));
  }
}
