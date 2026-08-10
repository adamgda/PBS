import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Employee,
  EmployeeDocument,
  EmployeeListResponse,
  EmployeeListParams,
  CreateEmployeeRequest,
  UpdateEmployeeRequest,
  AssignEmployeeRequest,
  CreateDocumentRequest,
  UpdateDocumentRequest,
  EmployeeRate,
  CreateRateRequest,
  EmployeeVacation,
  CreateVacationRequest,
  VacationStatus,
  SettlementResponse,
  SettlementByPortResponse,
  SettlementPeriod,
  EmployeeSummary,
} from '../models/employee.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Pracownicy (Etap 7).
 * CRUD pracowników + zarządzanie dokumentami (certyfikaty/uprawnienia)
 * + szybkie przypisanie terminala/sprzętu + upload plików.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /employees.
 */
@Injectable({ providedIn: 'root' })
export class EmployeesService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: EmployeeListParams): Observable<EmployeeListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<EmployeeListResponse>(`${this.apiUrl}/employees`, { params: httpParams });
  }

  get(id: number): Observable<Employee> {
    return this.http.get<Employee>(`${this.apiUrl}/employees/${id}`);
  }

  create(payload: CreateEmployeeRequest): Observable<Employee> {
    return this.http
      .post<Employee>(`${this.apiUrl}/employees`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  update(id: number, payload: UpdateEmployeeRequest): Observable<Employee> {
    return this.http
      .put<Employee>(`${this.apiUrl}/employees/${id}`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/employees/${id}`)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  assign(id: number, payload: AssignEmployeeRequest): Observable<Employee> {
    return this.http
      .patch<Employee>(`${this.apiUrl}/employees/${id}/assignment`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  listDocuments(employeeId: number): Observable<{ data: EmployeeDocument[] }> {
    return this.http.get<{ data: EmployeeDocument[] }>(`${this.apiUrl}/employees/${employeeId}/documents`);
  }

  createDocument(employeeId: number, payload: CreateDocumentRequest, file?: File | null): Observable<EmployeeDocument> {
    if (file) {
      const formData = new FormData();
      formData.append('nazwa', payload.nazwa);
      if (payload.numer_dokumentu) formData.append('numer_dokumentu', payload.numer_dokumentu);
      if (payload.data_wydania) formData.append('data_wydania', payload.data_wydania);
      if (payload.data_waznosci) formData.append('data_waznosci', payload.data_waznosci);
      formData.append('plik', file, file.name);
      return this.http
        .post<EmployeeDocument>(`${this.apiUrl}/employees/${employeeId}/documents`, formData)
        .pipe(tap(() => invalidateCache('/employees')));
    }
    return this.http
      .post<EmployeeDocument>(`${this.apiUrl}/employees/${employeeId}/documents`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  updateDocument(documentId: number, payload: UpdateDocumentRequest): Observable<EmployeeDocument> {
    return this.http
      .put<EmployeeDocument>(`${this.apiUrl}/documents/${documentId}`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  deleteDocument(documentId: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/documents/${documentId}`)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  // --- Stawki (Etap 7a) ---

  listRates(employeeId: number): Observable<{ data: EmployeeRate[] }> {
    return this.http.get<{ data: EmployeeRate[] }>(`${this.apiUrl}/employees/${employeeId}/rates`);
  }

  createRate(employeeId: number, payload: CreateRateRequest): Observable<EmployeeRate> {
    return this.http
      .post<EmployeeRate>(`${this.apiUrl}/employees/${employeeId}/rates`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  // --- Urlopy (Etap 7a) ---

  listVacations(employeeId: number): Observable<{ data: EmployeeVacation[] }> {
    return this.http.get<{ data: EmployeeVacation[] }>(`${this.apiUrl}/employees/${employeeId}/vacations`);
  }

  createVacation(employeeId: number, payload: CreateVacationRequest): Observable<EmployeeVacation> {
    return this.http
      .post<EmployeeVacation>(`${this.apiUrl}/employees/${employeeId}/vacations`, payload)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  updateVacationStatus(vacationId: number, status: VacationStatus): Observable<EmployeeVacation> {
    return this.http
      .patch<EmployeeVacation>(`${this.apiUrl}/vacations/${vacationId}/status`, { status })
      .pipe(tap(() => invalidateCache('/employees')));
  }

  deleteVacation(vacationId: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/vacations/${vacationId}`)
      .pipe(tap(() => invalidateCache('/employees')));
  }

  // --- Rozliczenia i podsumowania (Etap 7a) ---

  settlement(month: string, period: SettlementPeriod): Observable<SettlementResponse> {
    const params = new HttpParams().set('month', month).set('period', period);
    return this.http.get<SettlementResponse>(`${this.apiUrl}/employees/settlement`, { params });
  }

  settlementByPort(month: string, period: SettlementPeriod): Observable<SettlementByPortResponse> {
    const params = new HttpParams().set('month', month).set('period', period);
    return this.http.get<SettlementByPortResponse>(`${this.apiUrl}/employees/settlement/by-port`, { params });
  }

  summary(month: string): Observable<EmployeeSummary> {
    const params = new HttpParams().set('month', month);
    return this.http.get<EmployeeSummary>(`${this.apiUrl}/employees/summary`, { params });
  }
}