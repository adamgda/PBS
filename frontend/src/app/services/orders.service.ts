import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Order,
  OrderListResponse,
  OrderListParams,
  CreateOrderRequest,
  UpdateOrderRequest,
  CopyWeekRequest,
} from '../models/orders.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Harmonogram / Zlecenia (Etap 9).
 * CRUD zleceń + przypisywanie pracowników/sprzętu + kopiowanie tygodnia.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /orders.
 */
@Injectable({ providedIn: 'root' })
export class OrdersService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: OrderListParams): Observable<OrderListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<OrderListResponse>(`${this.apiUrl}/orders`, { params: httpParams });
  }

  get(id: number): Observable<Order> {
    return this.http.get<Order>(`${this.apiUrl}/orders/${id}`);
  }

  create(payload: CreateOrderRequest): Observable<Order> {
    return this.http
      .post<Order>(`${this.apiUrl}/orders`, payload)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  update(id: number, payload: UpdateOrderRequest): Observable<Order> {
    return this.http
      .put<Order>(`${this.apiUrl}/orders/${id}`, payload)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/orders/${id}`)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  copyWeek(payload: CopyWeekRequest): Observable<{ copied: number }> {
    return this.http
      .post<{ copied: number }>(`${this.apiUrl}/orders/copy-week`, payload)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  assignEmployee(orderId: number, payload: { employee_id: number; rola?: string | null; godziny?: number | null }): Observable<unknown> {
    return this.http
      .post(`${this.apiUrl}/orders/${orderId}/assign-employee`, payload)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  unassignEmployee(orderId: number, employeeId: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/orders/${orderId}/assign-employee/${employeeId}`)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  assignEquipment(orderId: number, equipmentId: number): Observable<unknown> {
    return this.http
      .post(`${this.apiUrl}/orders/${orderId}/assign-equipment`, { equipment_id: equipmentId })
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  unassignEquipment(orderId: number, equipmentId: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/orders/${orderId}/assign-equipment/${equipmentId}`)
      .pipe(tap(() => this.invalidateOrdersData()));
  }

  /**
   * Po mutacji zlecenia unieważnia cache GET dla listy zleceń ORAZ listy pracowników.
   * Przypisania/role/godziny w zleceniach zmieniają dane prezentowane w sekcji
   * Pracownicy (kolumna „Rola dziś", godziny w miesiącu, wynagrodzenie, urlop).
   */
  private invalidateOrdersData(): void {
    invalidateCache('/orders');
    invalidateCache('/employees');
  }
}