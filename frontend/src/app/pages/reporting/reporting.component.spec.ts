/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { ReportingComponent } from './reporting.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
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
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function flushOptions(): void {
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`))
      .flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`))
      .flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  function flushTerminalList(): void {
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/terminal`))
      .flush({ data: [], total: 0, page: 1, per_page: 25 });
  }

  it('powinien utworzyć komponent i pobrać listę raportów terminalowych', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.terminalReports()).toEqual([]);
    expect(fixture.componentInstance.activeTab()).toBe('terminal');
  });

  it('powinien przełączyć zakładkę na pojazdy i pobrać listę raportów pojazdowych', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();

    const comp = fixture.componentInstance;
    comp.setActiveTab('vehicle');
    fixture.detectChanges();
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/vehicle`))
      .flush({ data: [], total: 0, page: 1, per_page: 25 });
    expect(comp.activeTab()).toBe('vehicle');
  });

  it('powinien otworzyć modal tworzenia raportu terminalowego', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.modalOpis()).toBe('');
    expect(comp.modalTerminalId()).toBeNull();
  });

  it('powinien zablokować zapis raportu terminalowego bez terminala', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.modalOpis.set('Opis');
    comp.saveModal();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć raport terminalowy (POST /reports/terminal)', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.modalTerminalId.set(1);
    comp.modalOpis.set('Opis');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/reports/terminal`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.terminal_id).toBe(1);
    expect(req.request.body.opis).toBe('Opis');
    req.flush({
      id: 1,
      terminal_id: 1,
      terminal_nazwa: 'BCT',
      data_raportu: '2026-06-17',
      opis: 'Opis',
      uwagi: null,
      utworzony_przez: 1,
      utworzony_przez_email: 'admin@pbs.local',
      created_at: null,
      updated_at: null,
    });

    const reload = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/terminal`),
    );
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('powinien zablokować zapis raportu pojazdowego bez pojazdu', () => {
    const fixture = TestBed.createComponent(ReportingComponent);
    fixture.detectChanges();
    flushOptions();
    flushTerminalList();

    const comp = fixture.componentInstance;
    comp.setActiveTab('vehicle');
    fixture.detectChanges();
    httpMock
      .expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/reports/vehicle`))
      .flush({ data: [], total: 0, page: 1, per_page: 25 });

    comp.openCreate();
    comp.modalPrzebieg.set('100');
    comp.modalPrzebiegOc.set('OK');
    comp.saveModal();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });
});
