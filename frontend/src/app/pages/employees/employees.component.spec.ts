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
    // GET /employees?... (lista) — predykat wyklucza /employees/* (summary, rates itp.)
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`) && !r.url.includes('/employees/'),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
    // GET /employees/summary (KPI — wywoływane w konstruktorze, Etap 7a)
    const summaryReq = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.endsWith('/employees/summary'),
    );
    summaryReq.flush({ month: '2026-08', godziny_total: 0, wynagrodzenie_total: 0, godziny_1_15: 0, godziny_15_23: 0, wynagrodzenie_1_15: 0, wynagrodzenie_15_23: 0, na_urlopie: 0 });
  }

  function flushTerminalOptions() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 100 });
    // Opcje filtra „Sprzęt" (EquipmentService w konstruktorze)
    const eqReq = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`),
    );
    eqReq.flush({ data: [], total: 0, page: 1, per_page: 100 });
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
    comp.modalEmail.set('jan.kowalski@pbs.local');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.imie).toBe('Jan');
    expect(req.request.body.nazwisko).toBe('Kowalski');
    expect(req.request.body.email).toBe('jan.kowalski@pbs.local');

    req.flush({
      id: 8, imie: 'Jan', nazwisko: 'Kowalski', telefon: null, email: 'jan.kowalski@pbs.local',
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, created_at: null, updated_at: null,
    });

    // Po sukcesie lista przeładowana (GET /employees)
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('powinien zablokować tworzenie pracownika bez adresu e-mail (link do hasła)', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.modalImie.set('Jan');
    comp.modalNazwisko.set('Kowalski');
    comp.saveModal();
    // Nie wysłano POST
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
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

  // --- Etap 7a ---

  it('roleLabel mapuje rolę dnia lub zwraca placeholder', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    expect(comp.roleLabel('operator')).toBe('pracownicy.roles.operator');
    expect(comp.roleLabel('brygadzista')).toBe('pracownicy.roles.foreman');
    expect(comp.roleLabel('operator_zurawia')).toBe('pracownicy.roles.crane_operator');
    expect(comp.roleLabel(null)).toBe('pracownicy.list.unassigned');
  });

  it('invoiceStatusLabel / invoiceStatusTone mapują status faktury', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    expect(comp.invoiceStatusLabel('zaplacona')).toBe('pracownicy.invoices.status_paid');
    expect(comp.invoiceStatusLabel('przeterminowana')).toBe('pracownicy.invoices.status_overdue');
    expect(comp.invoiceStatusTone('zaplacona')).toBe('success');
    expect(comp.invoiceStatusTone('przeterminowana')).toBe('danger');
    expect(comp.invoiceStatusTone('wystawiona')).toBe('warning');
  });

  it('vacationTypeLabel / vacationStatusLabel mapują etykiety urlopu', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    expect(comp.vacationTypeLabel('wypoczynkowy')).toBe('pracownicy.leave.type_vacation');
    expect(comp.vacationTypeLabel('L4')).toBe('pracownicy.leave.type_sick');
    expect(comp.vacationStatusLabel('oczekujacy')).toBe('pracownicy.leave.status_planned');
    expect(comp.vacationStatusLabel('odrzucony')).toBe('pracownicy.leave.status_rejected');
  });

  it('openChangeRate ustawia modal stawki z wartością i datą', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const emp = {
      id: 1, imie: 'Jan', nazwisko: 'Kowalski', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, stawka_godzinowa: 50, godziny_mc: 0, wynagrodzenie: 0, rola_dzis: null, on_leave: false,
      created_at: null, updated_at: null,
    } as never;
    comp.openChangeRate(emp);
    expect(comp.rateEmployee()?.id).toBe(1);
    expect(comp.rateValue()).toBe('50');
    expect(comp.rateDataOd().length).toBeGreaterThan(0);

    const req = httpMock.expectOne((r) => r.method === 'GET' && r.url.endsWith('/employees/1/rates'));
    req.flush({ data: [] });
  });

  it('saveRate blokuje się przy nieprawidłowej stawce', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const emp = {
      id: 1, imie: 'Jan', nazwisko: 'K', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, stawka_godzinowa: 0, godziny_mc: 0, wynagrodzenie: 0, rola_dzis: null, on_leave: false,
      created_at: null, updated_at: null,
    } as never;
    comp.openChangeRate(emp);
    const req = httpMock.expectOne((r) => r.method === 'GET' && r.url.endsWith('/employees/1/rates'));
    req.flush({ data: [] });

    comp.rateValue.set('0');
    comp.saveRate();
    const postReqs = httpMock.match((r) => r.method === 'POST' && r.url.endsWith('/employees/1/rates'));
    expect(postReqs.length).toBe(0);
  });

  it('saveRate wysyła POST .../rates z payloadem', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const emp = {
      id: 2, imie: 'Anna', nazwisko: 'Nowak', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, stawka_godzinowa: 0, godziny_mc: 0, wynagrodzenie: 0, rola_dzis: null, on_leave: false,
      created_at: null, updated_at: null,
    } as never;
    comp.openChangeRate(emp);
    const histReq = httpMock.expectOne((r) => r.method === 'GET' && r.url.endsWith('/employees/2/rates'));
    histReq.flush({ data: [] });

    comp.rateValue.set('55');
    comp.rateDataOd.set('2026-02-01');
    comp.saveRate();

    const req = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/employees/2/rates'));
    expect(req.request.body.stawka_godzinowa).toBe(55);
    expect(req.request.body.data_od).toBe('2026-02-01');
    req.flush({ id: 1, employee_id: 2, stawka_godzinowa: 55, data_od: '2026-02-01', data_do: null, created_at: null, updated_at: null });

    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.rateEmployee()).toBeNull();
  });

  it('setTab("settlement") pobiera rozliczenie per port i podsumowanie', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.setTab('settlement');

    const byPort = httpMock.expectOne((r) => r.method === 'GET' && r.url.endsWith('/employees/settlement/by-port'));
    byPort.flush({ month: '2026-02', period: 'all', data: [{ terminal_id: 1, terminal_nazwa: 'T1', liczba_pracownikow: 2, suma_godzin: 10, suma_wynagrodzen: 500 }] });
    const summary = httpMock.expectOne((r) => r.method === 'GET' && r.url.endsWith('/employees/summary'));
    summary.flush({ month: '2026-02', godziny_total: 10, wynagrodzenie_total: 500, godziny_1_15: 4, godziny_15_23: 6, wynagrodzenie_1_15: 200, wynagrodzenie_15_23: 300, na_urlopie: 1 });

    expect(comp.activeTab()).toBe('settlement');
    expect(comp.portRows().length).toBe(1);
    expect(comp.summary()?.na_urlopie).toBe(1);
  });

  it('setTab("invoices") pobiera faktury i brakujące', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.setTab('invoices');

    const listReq = httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/invoices`);
    listReq.flush({ data: [{ id: 1, order_id: null, numer_faktury: 'F-1', klient_nazwa: 'K', kwota_pln: 100, data_wystawienia: '2026-02-01', termin_platnosci: null, status: 'wystawiona', typ_wystawienia: 'po_zleceniu', created_at: null, updated_at: null }], total: 1, page: 1, per_page: 25 });
    const missingReq = httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/invoices/missing`);
    missingReq.flush({ data: [], total: 0, page: 1, per_page: 100 });

    expect(comp.activeTab()).toBe('invoices');
    expect(comp.invoices().length).toBe(1);
    expect(comp.invoiceTotal()).toBe(1);
  });

  it('openAddInvoice resetuje pola modalu faktury', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    comp.openAddInvoice();
    expect(comp.invoiceModalMode()).toBe('create');
    expect(comp.invNumer()).toBe('');
    expect(comp.invStatus()).toBe('wystawiona');
    expect(comp.invTyp()).toBe('po_zleceniu');
  });

  it('quickFilter filtruje listę i resetuje stronę', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance as any;
    const emp = (extra: Record<string, unknown>) => ({
      id: 1, imie: 'Jan', nazwisko: 'K', telefon: null, email: null,
      current_terminal_id: null, terminal_nazwa: null, current_sprzet_id: null, sprzet_nazwa: null,
      is_active: true, stawka_godzinowa: 0, godziny_mc: 0, wynagrodzenie: 0, rola_dzis: null, on_leave: false,
      created_at: null, updated_at: null, ...extra,
    } as never);

    comp._employees.set([
      emp({ on_leave: true }),
      emp({ terminal_nazwa: 'BCT' }),
      emp({ uprawnienie: { nazwa: 'UDT', data_waznosci: null, dni: 5, status: 'expiring' } }),
    ]);

    expect(comp.quickFilterCounts().on_leave).toBe(1);
    expect(comp.quickFilterCounts().field).toBe(1);
    expect(comp.quickFilterCounts().entitlements).toBe(1);

    comp.setQuickFilter('on_leave');
    expect(comp.quickFilter()).toBe('on_leave');
    expect(comp.filteredEmployees().length).toBe(1);
    expect(comp.filteredEmployees()[0].on_leave).toBe(true);

    comp.setQuickFilter('all');
    expect(comp.quickFilter()).toBeNull();
    expect(comp.filteredEmployees().length).toBe(3);
  });

  it('filterConfigs zawiera filtry terminala i sprzętu', () => {
    const fixture = TestBed.createComponent(EmployeesComponent);
    fixture.detectChanges();
    flushEmployeeList();
    flushTerminalOptions();

    const comp = fixture.componentInstance;
    const keys = comp.filterConfigs().map((f) => f.key);
    expect(keys).toContain('q');
    expect(keys).toContain('terminal_id');
    expect(keys).toContain('sprzet_id');
    expect(keys).toContain('is_active');
  });
});