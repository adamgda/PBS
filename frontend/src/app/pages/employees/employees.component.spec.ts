/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { EmployeesComponent } from './employees.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('EmployeesComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EmployeesComponent],
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

  function flushEmployeeList() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
  }

  function flushTerminalOptions() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent i pobrać listę pracowników', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.employees()).toEqual([]);
    expect(fixture.componentInstance.total()).toBe(0);
  });

  it('powinien otworzyć modal tworzenia z domyślnymi wartościami', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.openCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.modalImie()).toBe('');
    expect(comp.modalNazwisko()).toBe('');
    expect(comp.modalIsActive()).toBe(true);
  });

  it('powinien zablokować tworzenie przy pustym imieniu', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.saveModal();
    // Nie wysłano POST
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć pracownika (POST /employees)', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.modalImie.set('Jan');
    comp.modalNazwisko.set('Kowalski');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.imie).toBe('Jan');
    expect(req.request.body.nazwisko).toBe('Kowalski');

    req.flush({
      id: 8, imie: 'Jan', nazwisko: 'Kowalski', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, created_at: null, updated_at: null,
    });

    // Po sukcesie lista przeładowana (GET /employees)
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('statusLabel rozróżnia aktywny/nieaktywny', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const active = {
      id: 1, imie: 'Jan', nazwisko: 'K', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, created_at: null, updated_at: null,
    } as never;
    const inactive = {
      id: 2, imie: 'A', nazwisko: 'B', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: false, created_at: null, updated_at: null,
    } as never;
    expect(comp.statusLabel(active)).toBe('pracownicy.status.active');
    expect(comp.statusLabel(inactive)).toBe('pracownicy.status.inactive');
  });

  it('docStatusTone rozróżnia wygasły/wygasający/ważny', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const expired = { id: 1, employee_id: 1, nazwa: 'A', numer_dokumentu: null, data_wydania: null, data_waznosci: '2020-01-01', plik: null, is_expired: true, is_expiring_soon: false, created_at: null, updated_at: null } as never;
    const soon = { id: 2, employee_id: 1, nazwa: 'B', numer_dokumentu: null, data_wydania: null, data_waznosci: '2099-01-01', plik: null, is_expired: false, is_expiring_soon: true, created_at: null, updated_at: null } as never;
    const valid = { id: 3, employee_id: 1, nazwa: 'C', numer_dokumentu: null, data_wydania: null, data_waznosci: null, plik: null, is_expired: false, is_expiring_soon: false, created_at: null, updated_at: null } as never;
    expect(comp.docStatusTone(expired)).toBe('danger');
    expect(comp.docStatusTone(soon)).toBe('warning');
    expect(comp.docStatusTone(valid)).toBe('success');
  });
});