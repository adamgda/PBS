import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  TerminalReport,
  VehicleReport,
  ReportListResponse,
  TerminalReportListParams,
  VehicleReportListParams,
  CreateTerminalReportRequest,
  UpdateTerminalReportRequest,
  CreateVehicleReportRequest,
  UpdateVehicleReportRequest,
  TerminalReportAutoDataResponse,
} from '../models/report.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Raportowanie (Etap 11).
 * Lista + szczegóły + tworzenie + edycja raportów terminalowych i pojazdowych.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /reports.
 */
@Injectable({ providedIn: 'root' })
export class ReportsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // --- Raporty terminalowe ---

  listTerminalReports(params: TerminalReportListParams): Observable<ReportListResponse<TerminalReport>> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<ReportListResponse<TerminalReport>>(`${this.apiUrl}/reports/terminal`, {
      params: httpParams,
    });
  }

  getTerminalReport(id: number): Observable<TerminalReport> {
    return this.http.get<TerminalReport>(`${this.apiUrl}/reports/terminal/${id}`);
  }

  /** Auto-dane z harmonogramu dla formularza nowego raportu terminalowego. */
  getTerminalAutoData(terminalId: number, date: string): Observable<TerminalReportAutoDataResponse> {
    const httpParams = new HttpParams().set('terminal_id', String(terminalId)).set('date', date);
    return this.http.get<TerminalReportAutoDataResponse>(`${this.apiUrl}/reports/terminal/auto-data`, {
      params: httpParams,
    });
  }

  createTerminalReport(payload: CreateTerminalReportRequest): Observable<TerminalReport> {
    return this.http
      .post<TerminalReport>(`${this.apiUrl}/reports/terminal`, payload)
      .pipe(tap(() => invalidateCache('/reports/terminal')));
  }

  updateTerminalReport(id: number, payload: UpdateTerminalReportRequest): Observable<TerminalReport> {
    return this.http
      .put<TerminalReport>(`${this.apiUrl}/reports/terminal/${id}`, payload)
      .pipe(tap(() => invalidateCache('/reports/terminal')));
  }

  // --- Raporty pojazdowe ---

  listVehicleReports(params: VehicleReportListParams): Observable<ReportListResponse<VehicleReport>> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<ReportListResponse<VehicleReport>>(`${this.apiUrl}/reports/vehicle`, {
      params: httpParams,
    });
  }

  getVehicleReport(id: number): Observable<VehicleReport> {
    return this.http.get<VehicleReport>(`${this.apiUrl}/reports/vehicle/${id}`);
  }

  createVehicleReport(payload: CreateVehicleReportRequest): Observable<VehicleReport> {
    return this.http
      .post<VehicleReport>(`${this.apiUrl}/reports/vehicle`, payload)
      .pipe(tap(() => invalidateCache('/reports/vehicle')));
  }

  updateVehicleReport(id: number, payload: UpdateVehicleReportRequest): Observable<VehicleReport> {
    return this.http
      .put<VehicleReport>(`${this.apiUrl}/reports/vehicle/${id}`, payload)
      .pipe(tap(() => invalidateCache('/reports/vehicle')));
  }
}
