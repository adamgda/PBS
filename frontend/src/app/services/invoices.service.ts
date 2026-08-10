import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Invoice,
  InvoiceListResponse,
  InvoiceListParams,
  CreateInvoiceRequest,
  UpdateInvoiceRequest,
  InvoiceStatus,
  MissingInvoicesResponse,
} from '../models/invoice.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Faktury (Etap 7a).
 * CRUD faktur + zmiana statusu + detekcja zleceń zakończonych bez faktury.
 */
@Injectable({ providedIn: 'root' })
export class InvoicesService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: InvoiceListParams): Observable<InvoiceListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<InvoiceListResponse>(`${this.apiUrl}/invoices`, { params: httpParams });
  }

  get(id: number): Observable<Invoice> {
    return this.http.get<Invoice>(`${this.apiUrl}/invoices/${id}`);
  }

  create(payload: CreateInvoiceRequest): Observable<Invoice> {
    return this.http
      .post<Invoice>(`${this.apiUrl}/invoices`, payload)
      .pipe(tap(() => invalidateCache('/invoices')));
  }

  update(id: number, payload: UpdateInvoiceRequest): Observable<Invoice> {
    return this.http
      .put<Invoice>(`${this.apiUrl}/invoices/${id}`, payload)
      .pipe(tap(() => invalidateCache('/invoices')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/invoices/${id}`)
      .pipe(tap(() => invalidateCache('/invoices')));
  }

  updateStatus(id: number, status: InvoiceStatus): Observable<Invoice> {
    return this.http
      .patch<Invoice>(`${this.apiUrl}/invoices/${id}/status`, { status })
      .pipe(tap(() => invalidateCache('/invoices')));
  }

  missing(page = 1, perPage = 25): Observable<MissingInvoicesResponse> {
    const params = new HttpParams().set('page', page).set('per_page', perPage);
    return this.http.get<MissingInvoicesResponse>(`${this.apiUrl}/invoices/missing`, { params });
  }
}