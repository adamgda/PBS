import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  Incident,
  IncidentListResponse,
  IncidentListParams,
  CreateIncidentRequest,
  ChangeIncidentStatusRequest,
  AddIncidentCommentRequest,
  IncidentComment,
} from '../models/incidents.model';
import { invalidateCache } from './http.interceptor';

/**
 * Serwis sekcji Awaria (Etap 10).
 * Lista + szczegóły (komentarze, historia statusów), zgłoszenie, zmiana statusu, komentarze.
 *
 * Po każdej mutacji unieważniany jest cache GET (interceptor) dla /incidents.
 */
@Injectable({ providedIn: 'root' })
export class IncidentsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  list(params: IncidentListParams): Observable<IncidentListResponse> {
    let httpParams = new HttpParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }
    return this.http.get<IncidentListResponse>(`${this.apiUrl}/incidents`, { params: httpParams });
  }

  get(id: number): Observable<Incident> {
    return this.http.get<Incident>(`${this.apiUrl}/incidents/${id}`);
  }

  create(payload: CreateIncidentRequest): Observable<Incident> {
    return this.http
      .post<Incident>(`${this.apiUrl}/incidents`, payload)
      .pipe(tap(() => invalidateCache('/incidents')));
  }

  changeStatus(id: number, payload: ChangeIncidentStatusRequest): Observable<Incident> {
    return this.http
      .patch<Incident>(`${this.apiUrl}/incidents/${id}/status`, payload)
      .pipe(tap(() => invalidateCache('/incidents')));
  }

  addComment(id: number, payload: AddIncidentCommentRequest): Observable<IncidentComment> {
    return this.http
      .post<IncidentComment>(`${this.apiUrl}/incidents/${id}/comments`, payload)
      .pipe(tap(() => invalidateCache('/incidents')));
  }
}