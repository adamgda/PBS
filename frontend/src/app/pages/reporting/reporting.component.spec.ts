/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { ReportingComponent } from './reporting.component';
import { TranslateService } from '../../services/translate.service';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

/** Stub AuthService — zwraca zalogowanego użytkownika (e-mail w polu „Osoba sporządzająca"). */
class AuthServiceStub {
  get currentUser() {
    return { id: 1, email: 'admin@pbs.local', role: 'super_admin', permissions: {}, is_active: true };
  }
}

describe('ReportingComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReportingComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: TranslateService, useClass: TranslateServiceStub },
        { provide: AuthService, useClass: AuthServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  /** Opróżnia początkowe zapytania: opcje (terminals/equipment) + ostatnie raporty. */
  function flushInit(): void {
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`))
      .flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`))
      .flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/terminal`))
      .flush({ data: [], total: 0, page: 1, per_page: 5 });
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/vehicle`))
      .flush({ data: [], total: 0, page: 1, per_page: 5 });
  }

  function flushReload(): void {
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/terminal`))
      .flush({ data: [], total: 0, page: 1, per_page: 5 });
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/vehicle`))
      .flush({ data: [], total: 0, page: 1, per_page: 5 });
  }

  it('powinien utworzyć komponent i pobrać opcje oraz ostatnie raporty', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.terminalOptions()).toEqual([]);
    expect(fixture.componentInstance.recentReports()).toEqual([]);
  });

  it('powinien otworzyć modal tworzenia raportu pojazdowego', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.openVehicleCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.modalKind()).toBe('vehicle');
  });

  it('powinien pobrać auto-dane po wybraniu terminala', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.onTerminalSelected({ value: 1, label: 'BCT' });

    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/terminal/auto-data`),
    );
    req.flush({
      terminal_id: 1,
      terminal_nazwa: 'BCT',
      data_raportu: comp.createDate(),
      auto_data: {
        orders: [],
        employees: [
          { employee_id: 1, employee_name: 'Jan Kowalski', rola: 'operator', godziny: 8, stawka_godzinowa: 45, wynagrodzenie: 360, order_id: 1, order_number: 'ZL/1' },
        ],
        equipment: [],
        total_hours: 8,
        total_wages: 360,
      },
    });
    fixture.detectChanges();
    expect(comp.autoData()).not.toBeNull();
    expect(comp.autoTerminalName()).toBe('BCT');
    expect(comp.autoData()!.total_wages).toBe(360);
  });

  it('powinien zablokować zapis raportu terminalowego bez terminala', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.createOpis.set('Opis');
    comp.saveTerminal();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć raport terminalowy (POST /reports/terminal)', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.createTerminalId.set(1);
    comp.createOpis.set('Opis');
    comp.saveTerminal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/reports/terminal`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.terminal_id).toBe(1);
    expect(req.request.body.opis).toBe('Opis');
    req.flush({
      id: 1,
      terminal_id: 1,
      terminal_nazwa: 'BCT',
      data_raportu: comp.createDate(),
      opis: 'Opis',
      uwagi: null,
      utworzony_przez: 1,
      utworzony_przez_email: 'admin@pbs.local',
      created_at: null,
      updated_at: null,
    });

    flushReload();
    expect(comp.createTerminalId()).toBeNull();
    expect(comp.modalMode()).toBeNull();
  });

  it('powinien zablokować zapis raportu pojazdowego bez pojazdu', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.openVehicleCreate();
    comp.modalPrzebieg.set('100');
    comp.modalPrzebiegOc.set('OK');
    comp.saveModal();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć raport pojazdowy przez modal (POST /reports/vehicle)', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushInit();
    const comp = fixture.componentInstance;
    comp.openVehicleCreate();
    comp.modalEquipmentId.set(1);
    comp.modalPrzebieg.set('100');
    comp.modalPrzebiegOc.set('OK');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/reports/vehicle`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.equipment_id).toBe(1);
    req.flush({
      id: 1,
      equipment_id: 1,
      equipment_nazwa: 'RS-02',
      data_raportu: comp.modalDate(),
      aktualny_przebieg: 100,
      przebieg_oc: 'OK',
      uwagi: null,
      utworzony_przez: 1,
      utworzony_przez_email: 'admin@pbs.local',
      zrodlo: 'panel',
      created_at: null,
      updated_at: null,
    });

    flushReload();
    expect(comp.modalMode()).toBeNull();
  });
});

