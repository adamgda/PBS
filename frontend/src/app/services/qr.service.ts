import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  QrMachine,
  QrInfo,
  QrTokenResponse,
  QrIncidentResponse,
  QrDailyReportResponse,
  CreateQrIncidentRequest,
  CreateQrDailyReportRequest,
} from '../models/qr.model';

/**
 * Serwis kodów QR dla maszyn (Etap 20).
 *
 * Zawiera:
 *  - autoryzowane operacje (generowanie tokena, podgląd kodu do wydruku naklejki),
 *  - publiczne operacje (informacja o maszynie, zgłoszenie awarii, raport OC)
 *    dostępne bez logowania z podstrony `/qr/{token}`.
 */
@Injectable({ providedIn: 'root' })
export class QrService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // --- Autoryzowane ---

  generateToken(equipmentId: number): Observable<QrTokenResponse> {
    return this.http.post<QrTokenResponse>(`${this.apiUrl}/equipment/${equipmentId}/qr-token`, {});
  }

  getQrInfo(equipmentId: number): Observable<QrInfo> {
    return this.http.get<QrInfo>(`${this.apiUrl}/equipment/${equipmentId}/qr`);
  }

  // --- Publiczne (z naklejki QR) ---

  getMachine(token: string): Observable<QrMachine> {
    return this.http.get<QrMachine>(`${this.apiUrl}/qr/${token}`);
  }

  createIncident(token: string, payload: CreateQrIncidentRequest): Observable<QrIncidentResponse> {
    return this.http.post<QrIncidentResponse>(`${this.apiUrl}/qr/${token}/incident`, payload);
  }

  createDailyReport(token: string, payload: CreateQrDailyReportRequest): Observable<QrDailyReportResponse> {
    return this.http.post<QrDailyReportResponse>(`${this.apiUrl}/qr/${token}/daily-report`, payload);
  }
}
