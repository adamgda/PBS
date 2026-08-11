import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import { DashboardSummary, DashboardAlerts, DashboardCharts } from '../models/dashboard.model';

/**
 * Serwis sekcji Dashboard (Etap 13).
 * Endpointy read-only: summary (KPI), alerts (lista alertów) oraz
 * charts (dane wykresów i aktywności).
 */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  summary(): Observable<DashboardSummary> {
    return this.http.get<DashboardSummary>(`${this.apiUrl}/dashboard/summary`);
  }

  alerts(): Observable<DashboardAlerts> {
    return this.http.get<DashboardAlerts>(`${this.apiUrl}/dashboard/alerts`);
  }

  charts(): Observable<DashboardCharts> {
    return this.http.get<DashboardCharts>(`${this.apiUrl}/dashboard/charts`);
  }
}
