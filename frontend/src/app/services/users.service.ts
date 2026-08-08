import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  User,
  UserListResponse,
  UserListParams,
  CreateUserRequest,
  CreateUserResponse,
  UpdateUserRequest,
  UpdatePermissionsRequest,
} from '../models/user.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Ustawienia → Użytkownicy.
 * CRUD użytkowników + zarządzanie uprawnieniami per sekcja.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla listy i detali.
 */
@Injectable({ providedIn: 'root' })
export class UsersService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: UserListParams): Observable<UserListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<UserListResponse>(`${this.apiUrl}/users`, { params: httpParams });
  }

  get(id: number): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/users/${id}`);
  }

  create(payload: CreateUserRequest): Observable<CreateUserResponse> {
    return this.http
      .post<CreateUserResponse>(`${this.apiUrl}/users`, payload)
      .pipe(tap(() => invalidateCache('/users')));
  }

  update(id: number, payload: UpdateUserRequest): Observable<User> {
    return this.http.put<User>(`${this.apiUrl}/users/${id}`, payload).pipe(
      tap(() => invalidateCache('/users')),
    );
  }

  updatePermissions(id: number, payload: UpdatePermissionsRequest): Observable<User> {
    return this.http
      .patch<User>(`${this.apiUrl}/users/${id}/permissions`, payload)
      .pipe(tap(() => invalidateCache('/users')));
  }

  delete(id: number): Observable<{ success: boolean }> {
    return this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/users/${id}`)
      .pipe(tap(() => invalidateCache('/users')));
  }
}