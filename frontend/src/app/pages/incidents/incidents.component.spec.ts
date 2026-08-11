/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { IncidentsComponent } from './incidents.component';
import { Incident } from '../../models/incidents.model';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('IncidentsComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [IncidentsComponent],
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

  function flushIncidents() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/incidents`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
  }

  function flushEquipment() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent i pobrać listę awarii', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.incidents()).toEqual([]);
    expect(fixture.componentInstance.total()).toBe(0);
  });

  it('powinien otworzyć modal zgłoszenia z domyślnymi wartościami', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    comp.openCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.formTyp()).toBe('sprzet');
    expect(comp.formEquipmentId()).toBeNull();
    expect(comp.formOpis()).toBe('');
  });

  it('powinien zablokować zgłoszenie przy pustym opisie', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.saveCreate();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien zgłosić awarię (POST /incidents)', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.formTyp.set('inne');
    comp.formOpis.set('Awaria światła');
    comp.saveCreate();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/incidents`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.typ).toBe('inne');
    expect(req.request.body.opis).toBe('Awaria światła');
    req.flush({
      id: 1, typ: 'inne', equipment_id: null, equipment_nazwa: null, opis: 'Awaria światła',
      status: 'zgloszona', data_zgloszenia: null, data_zakonczenia: null,
      zgloszona_przez: 1, zgloszona_przez_email: null, created_at: null, updated_at: null,
    });

    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/incidents`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('statusBadgeStatus mapuje status na kanoniczny klucz', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    expect(comp.statusBadgeStatus('zgloszona')).toBe('reported');
    expect(comp.statusBadgeStatus('w_trakcie_naprawy')).toBe('under_repair');
    expect(comp.statusBadgeStatus('naprawiona')).toBe('repaired');
    expect(comp.statusBadgeStatus('zamknieta')).toBe('closed');
  });

  it('typeLabel rozróżnia sprzet/inne', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    const eq = { id: 1, typ: 'sprzet', equipment_id: null, equipment_nazwa: null, opis: '', status: 'zgloszona', data_zgloszenia: null, data_zakonczenia: null, zgloszona_przez: 1, zgloszona_przez_email: null, created_at: null, updated_at: null } as never;
    const other = { id: 2, typ: 'inne', equipment_id: null, equipment_nazwa: null, opis: '', status: 'zgloszona', data_zgloszenia: null, data_zakonczenia: null, zgloszona_przez: 1, zgloszona_przez_email: null, created_at: null, updated_at: null } as never;
    expect(comp.typeLabel(eq)).toBe('awaria.list.type_equipment');
    expect(comp.typeLabel(other)).toBe('awaria.list.type_other');
  });

  it('lifecycleIndex odwzorowuje kolejność statusów', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    expect(comp.lifecycleIndex('zgloszona')).toBe(0);
    expect(comp.lifecycleIndex('w_trakcie_naprawy')).toBe(1);
    expect(comp.lifecycleIndex('naprawiona')).toBe(2);
    expect(comp.lifecycleIndex('zamknieta')).toBe(3);
  });

  it('setStatus ustawia status i wysyła PATCH /incidents/{id}/status', () => {
    const fixture = TestBed.createComponent(IncidentsComponent);
    fixture.detectChanges();
    flushIncidents();
    flushEquipment();

    const comp = fixture.componentInstance;
    const inc: Incident = {
      id: 5, typ: 'sprzet', equipment_id: 1, equipment_nazwa: 'RS-02', opis: 'test', status: 'zgloszona',
      data_zgloszenia: null, data_zakonczenia: null, zgloszona_przez: 1, zgloszona_przez_email: 'a@b.pl',
      created_at: null, updated_at: null,
    };
    comp.openDetails(inc);

    const detailsReq = httpMock.expectOne(`${environment.apiUrl}/incidents/5`);
    detailsReq.flush({ ...inc, comments: [], status_history: [] });

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