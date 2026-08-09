import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Equipment,
  EquipmentListResponse,
  EquipmentListParams,
  CreateEquipmentRequest,
  UpdateEquipmentRequest,
  AssignEquipmentRequest,
  ServicePlan,
  CreateServicePlanRequest,
  UpdateServicePlanRequest,
  EquipmentHistory,
} from '../models/equipment.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Sprzęt (Etap 8).
 * CRUD sprzętu + szybkie przypisanie + oś czasu + planowanie przeglądów.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /equipment.
 */
@Injectable({ providedIn: 'root' })
export class EquipmentService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: EquipmentListParams): Observable<EquipmentListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<EquipmentListResponse>(`${this.apiUrl}/equipment`, { params: httpParams });
  }

  get(id: number): Observable<Equipment> {
    return this.http.get<Equipment>(`${this.apiUrl}/equipment/${id}`);
  }

  create(payload: CreateEquipmentRequest): Observable<Equipment> {
    return this.http
      .post<Equipment>(`${this.apiUrl}/equipment`, payload)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  update(id: number, payload: UpdateEquipmentRequest): Observable<Equipment> {
    return this.http
      .put<Equipment>(`${this.apiUrl}/equipment/${id}`, payload)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/equipment/${id}`)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  assign(id: number, payload: AssignEquipmentRequest): Observable<Equipment> {
    return this.http
      .patch<Equipment>(`${this.apiUrl}/equipment/${id}/assignment`, payload)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  timeline(id: number): Observable<{ data: EquipmentHistory[] }> {
    return this.http.get<{ data: EquipmentHistory[] }>(`${this.apiUrl}/equipment/${id}/timeline`);
  }

  listServicePlans(equipmentId: number): Observable<{ data: ServicePlan[] }> {
    return this.http.get<{ data: ServicePlan[] }>(`${this.apiUrl}/equipment/${equipmentId}/service-plans`);
  }

  createServicePlan(equipmentId: number, payload: CreateServicePlanRequest): Observable<ServicePlan> {
    return this.http
      .post<ServicePlan>(`${this.apiUrl}/equipment/${equipmentId}/service-plans`, payload)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  updateServicePlan(planId: number, payload: UpdateServicePlanRequest): Observable<ServicePlan> {
    return this.http
      .put<ServicePlan>(`${this.apiUrl}/service-plans/${planId}`, payload)
      .pipe(tap(() => invalidateCache('/equipment')));
  }

  deleteServicePlan(planId: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/service-plans/${planId}`)
      .pipe(tap(() => invalidateCache('/equipment')));
  }
}