import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  AnalyticsOverview,
  AnalyticsTerminal,
  AnalyticsEmployee,
  AnalyticsEquipment,
  AnalyticsOrderInTime,
  AnalyticsRelation,
  AnalyticsListResponse,
  AnalyticsRangeParams,
} from '../models/analytics.model';

/**
 * Serwis sekcji Analityka (Etap 12).
 * Endpointy read-only: overview, terminals, employees, equipment, relations.
 * Każdy przyjmuje opcjonalny zakres dat (domyślnie ostatnie 30 dni).
 */
@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  overview(params: AnalyticsRangeParams = {}): Observable<AnalyticsOverview> {
    return this.http.get<AnalyticsOverview>(`${this.apiUrl}/analytics/overview`, {
      params: this.toParams(params),
    });
  }

  terminals(params: AnalyticsRangeParams = {}): Observable<AnalyticsListResponse<AnalyticsTerminal>> {
    return this.http.get<AnalyticsListResponse<AnalyticsTerminal>>(`${this.apiUrl}/analytics/terminals`, {
      params: this.toParams(params),
    });
  }

  employees(params: AnalyticsRangeParams = {}): Observable<AnalyticsListResponse<AnalyticsEmployee>> {
    return this.http.get<AnalyticsListResponse<AnalyticsEmployee>>(`${this.apiUrl}/analytics/employees`, {
      params: this.toParams(params),
    });
  }

  equipment(params: AnalyticsRangeParams = {}): Observable<AnalyticsListResponse<AnalyticsEquipment>> {
    return this.http.get<AnalyticsListResponse<AnalyticsEquipment>>(`${this.apiUrl}/analytics/equipment`, {
      params: this.toParams(params),
    });
  }

  ordersInTime(params: AnalyticsRangeParams = {}): Observable<AnalyticsListResponse<AnalyticsOrderInTime>> {
    return this.http.get<AnalyticsListResponse<AnalyticsOrderInTime>>(`${this.apiUrl}/analytics/orders-in-time`, {
      params: this.toParams(params),
    });
  }

  relations(params: AnalyticsRangeParams = {}): Observable<AnalyticsListResponse<AnalyticsRelation>> {
    return this.http.get<AnalyticsListResponse<AnalyticsRelation>>(`${this.apiUrl}/analytics/relations`, {
      params: this.toParams(params),
    });
  }

  private toParams(params: AnalyticsRangeParams): HttpParams {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return httpParams;
  }
}
