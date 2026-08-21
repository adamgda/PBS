import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import { ExportType, ExportParams } from '../models/export.model';

/**
 * Serwis sekcji „Eksport danych".
 * Pobiera plik CSV z backendu jako Blob (odpowiedź surowa, nie JSON).
 *
 * Eksport z założenia nie jest cache'owany (interceptor cache'uje GET) —
 * do URL dodawany jest parametr `_ts`, aby każdy eksport był świeży.
 */
@Injectable({ providedIn: 'root' })
export class ExportsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  exportCsv(type: ExportType, params: ExportParams): Observable<Blob> {
    let httpParams = new HttpParams();
    if (params.from) {
      httpParams = httpParams.set('from', params.from);
    }
    if (params.to) {
      httpParams = httpParams.set('to', params.to);
    }
    // Pomijanie frontend cache GET (interceptor) — każdy eksport ma być świeży.
    httpParams = httpParams.set('_ts', String(Date.now()));

    return this.http.get(`${this.apiUrl}/exports/${type}`, {
      params: httpParams,
      responseType: 'blob',
    });
  }
}
