/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { IncidentDetailsComponent } from './incident-details.component';
import { Incident } from '../../../models/incidents.model';
import { TranslateService } from '../../../services/translate.service';
import { environment } from '../../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('IncidentDetailsComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [IncidentDetailsComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('lifecycleIndex odwzorowuje kolejność statusów', () => {
    const fixture = TestBed.createComponent(IncidentDetailsComponent);
    fixture.detectChanges();

    const comp = fixture.componentInstance;
    expect(comp.lifecycleIndex('zgloszona')).toBe(0);
    expect(comp.lifecycleIndex('w_trakcie_naprawy')).toBe(1);
    expect(comp.lifecycleIndex('naprawiona')).toBe(2);
    expect(comp.lifecycleIndex('zamknieta')).toBe(3);
  });

  it('setStatus ustawia status i wysyła PATCH /incidents/{id}/status', () => {
    const fixture = TestBed.createComponent(IncidentDetailsComponent);
    fixture.detectChanges();

    const comp = fixture.componentInstance;
    const inc: Incident = {
      id: 5, typ: 'sprzet', equipment_id: 1, equipment_nazwa: 'RS-02', opis: 'test', status: 'zgloszona',
      data_zgloszenia: null, data_zakonczenia: null, zgloszona_przez: 1, zgloszona_przez_email: 'a@b.pl',
      zrodlo: 'panel', created_at: null, updated_at: null,
    };
    comp.incident.set(inc);

    comp.setStatus('naprawiona');
    expect(comp.newStatus()).toBe('naprawiona');

    const patchReq = httpMock.expectOne(`${environment.apiUrl}/incidents/5/status`);
    expect(patchReq.request.method).toBe('PATCH');
    expect(patchReq.request.body.status).toBe('naprawiona');
    patchReq.flush({ ...inc, status: 'naprawiona', comments: [], status_history: [] });

    // Po sukcesie changeStatus przeładowuje szczegóły (GET /incidents/5)
    const reload = httpMock.expectOne(`${environment.apiUrl}/incidents/5`);
    reload.flush({ ...inc, status: 'naprawiona', comments: [], status_history: [] });
  });
});
