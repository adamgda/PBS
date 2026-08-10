/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { OrdersComponent } from './orders.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('OrdersComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [OrdersComponent],
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

  function flushAllInitial() {
    // load() → GET /orders, loadOptions() → terminals + employees + equipment
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/orders`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent i pobrać zlecenia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.orders()).toEqual([]);
  });

  it('powinien otworzyć modal tworzenia z domyślnymi wartościami', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.openCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.formNumer()).toBe('');
    expect(comp.formKlient()).toBe('');
    expect(comp.formTerminalId()).toBeNull();
    expect(comp.formStatus()).toBe('nowe');
  });

  it('powinien zablokować tworzenie przy pustym numerze', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.saveModal();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć zlecenie (POST /orders)', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.formNumer.set('ZL-TEST-1');
    comp.formKlient.set('Klient Test');
    comp.formTerminalId.set(1);
    comp.formStart.set('2026-01-05T08:00');
    comp.formEnd.set('2026-01-05T16:00');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/orders`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.numer_zlecenia).toBe('ZL-TEST-1');
    expect(req.request.body.terminal_id).toBe(1);
    req.flush({
      id: 1, numer_zlecenia: 'ZL-TEST-1', klient_nazwa: 'Klient Test', terminal_id: 1, terminal_nazwa: null,
      data_rozpoczecia: '2026-01-05 08:00:00', data_zakonczenia: '2026-01-05 16:00:00', zakres_prac: '',
      wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null,
    });

    // Po sukcesie lista przeładowana (GET /orders)
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/orders`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 100 });

    expect(comp.modalMode()).toBeNull();
  });

  it('statusColor mapuje status na kolor', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    expect(comp.statusColor('nowe')).toBe('#3b82f6');
    expect(comp.statusColor('w_realizacji')).toBe('#f59e0b');
    expect(comp.statusColor('zakonczone')).toBe('#22c55e');
  });

  it('kopiowanie tygodnia wysyła POST /orders/copy-week', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.openCopyWeek();
    expect(comp.modalMode()).toBe('copyWeek');
    comp.doCopyWeek();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/orders/copy-week`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.source_week_start).toBeDefined();
    expect(req.request.body.target_week_start).toBeDefined();
    req.flush({ copied: 2 });

    // Po sukcesie przeładowanie listy
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/orders`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 100 });

    expect(comp.modalMode()).toBeNull();
  });

  // --- Etap 7a: wybór roli przy przypisywaniu pracownika ---

  it('assignEmployee wysyła rolę w payloadzie', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    // Ustaw zlecenie i pracownika bezpośrednio przez sygnały (pomijamy otwarcie detali).
    comp.assignOrder.set({ id: 5, numer_zlecenia: 'ZL-005', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null } as never);
    comp.assignEmployeeId.set(3);
    comp.assignRole.set('operator');
    comp.saveAssignEmployee();

    const req = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/orders/5/assign-employee'));
    expect(req.request.body.employee_id).toBe(3);
    expect(req.request.body.rola).toBe('operator');
    req.flush({ order_id: 5, employee_id: 3, rola: 'operator', godziny: null, assigned: true });

    // Po sukcesie przeładowanie detali (GET /orders/5)
    const detailReq = httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/5`);
    detailReq.flush({ id: 5, numer_zlecenia: 'ZL-005', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null, employees: [], equipment: [] });

    expect(comp.assignRole()).toBeNull();
  });

  // --- Rozbudowa: godziny, szybkie przypisywanie, przypisania przy tworzeniu ---

  it('assignEmployee wysyła godziny w payloadzie', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.assignOrder.set({ id: 7, numer_zlecenia: 'ZL-007', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null } as never);
    comp.assignEmployeeId.set(4);
    comp.assignRole.set('brygadzista');
    comp.assignGodziny.set('8');
    comp.saveAssignEmployee();

    const req = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/orders/7/assign-employee'));
    expect(req.request.body.employee_id).toBe(4);
    expect(req.request.body.rola).toBe('brygadzista');
    expect(req.request.body.godziny).toBe(8);
    req.flush({ order_id: 7, employee_id: 4, rola: 'brygadzista', godziny: 8, assigned: true });

    const detailReq = httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/7`);
    detailReq.flush({ id: 7, numer_zlecenia: 'ZL-007', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null, employees: [], equipment: [] });

    expect(comp.assignGodziny()).toBe('');
  });

  it('quickAssignEmployee przypisuje pracownika do wybranego zlecenia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.selectedOrder.set({ id: 9, numer_zlecenia: 'ZL-009', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null, employees: [], equipment: [] } as never);
    comp.quickAssignEmployee({ id: 11, imie: 'Jan', nazwisko: 'Kruk', rola_dzis: 'operator' } as never);

    const req = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/orders/9/assign-employee'));
    expect(req.request.body.employee_id).toBe(11);
    expect(req.request.body.rola).toBe('operator');
    req.flush({ assigned: true });

    httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/9`).flush({ id: 9, numer_zlecenia: 'ZL-009', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T', data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null, employees: [], equipment: [] });
  });

  it('przypisania oczekujące są aplikowane po utworzeniu zlecenia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.formNumer.set('ZL-PENDING');
    comp.formKlient.set('Klient');
    comp.formTerminalId.set(1);
    comp.formStart.set('2026-02-02T08:00');
    comp.formEnd.set('2026-02-02T16:00');
    // Dodaj przypisania oczekujące
    comp.onPendingEmployeeSelected({ value: 21, label: 'Anna Kowal' });
    comp.onPendingEquipmentSelected({ value: 31, label: 'RS-02' });
    comp.saveModal();

    // POST /orders
    const postReq = httpMock.expectOne((r) => r.method === 'POST' && r.url === `${environment.apiUrl}/orders`);
    postReq.flush({ id: 42, numer_zlecenia: 'ZL-PENDING', klient_nazwa: 'Klient', terminal_id: 1, terminal_nazwa: null, data_rozpoczecia: '2026-02-02 08:00:00', data_zakonczenia: '2026-02-02 16:00:00', zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null });

    // Aplikacja przypisań: assign-employee + assign-equipment
    const empReq = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/orders/42/assign-employee'));
    expect(empReq.request.body.employee_id).toBe(21);
    empReq.flush({ assigned: true });
    const eqReq = httpMock.expectOne((r) => r.method === 'POST' && r.url.endsWith('/orders/42/assign-equipment'));
    expect(eqReq.request.body.equipment_id).toBe(31);
    eqReq.flush({ assigned: true });

    // Przeładowanie listy
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/orders`)).flush({ data: [], total: 0, page: 1, per_page: 100 });

    expect(comp.pendingEmployees()).toEqual([]);
    expect(comp.pendingEquipment()).toEqual([]);
    expect(comp.modalMode()).toBeNull();
  });

  it('wagesSummary sumuje godziny i wynagrodzenia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.selectedOrder.set({
      id: 1, numer_zlecenia: 'ZL-1', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'T',
      data_rozpoczecia: null, data_zakonczenia: null, zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null,
      employees: [
        { id: 1, order_id: 1, employee_id: 1, employee_name: 'A', employee_email: null, rola: 'operator', godziny: 8, stawka_godzinowa: 45, wynagrodzenie: 360 },
        { id: 2, order_id: 1, employee_id: 2, employee_name: 'B', employee_email: null, rola: 'brygadzista', godziny: 8, stawka_godzinowa: 42, wynagrodzenie: 336 },
      ],
      equipment: [],
    } as never);

    expect(comp.wagesSummary().godziny).toBe(16);
    expect(comp.wagesSummary().wynagrodzenie).toBe(696);
  });

  // --- Siatka tygodniowa ---

  it('weekColumns zwraca 7 dni z etykietami', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.weekStart.set('2026-06-15'); // poniedziałek
    const cols = comp.weekColumns();
    expect(cols.length).toBe(7);
    expect(cols[0].label).toContain('Pon');
    expect(cols[0].date).toBe('2026-06-15');
    expect(cols[6].label).toContain('Nd');
    expect(cols[6].date).toBe('2026-06-21');
  });

  it('shiftRows przypisuje zlecenie do odpowiedniej zmiany i dnia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.weekStart.set('2026-06-15');
    (comp as any)._orders.set([
      { id: 1, numer_zlecenia: 'ZL-A', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'BCT', data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: 'rozładunek', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null },
      { id: 2, numer_zlecenia: 'ZL-B', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'DCT', data_rozpoczecia: '2026-06-17 15:00:00', data_zakonczenia: '2026-06-17 23:00:00', zakres_prac: 'wagony', wartosc_pln: 0, status: 'w_realizacji', created_at: null, updated_at: null },
    ] as never);

    const rows = comp.shiftRows();
    // Środa (index 2) — pierwsze zlecenie w zmianie 06–14, drugie w 14–22
    const morning = rows.find((r) => r.key === '06-14')!;
    expect(morning.cells[2].orders.length).toBe(1);
    expect(morning.cells[2].orders[0].numer_zlecenia).toBe('ZL-A');
    const afternoon = rows.find((r) => r.key === '14-22')!;
    expect(afternoon.cells[2].orders.length).toBe(1);
    expect(afternoon.cells[2].orders[0].numer_zlecenia).toBe('ZL-B');
    // Nocna zmiana pusta dla środy
    const night = rows.find((r) => r.key === '22-06')!;
    expect(night.cells[2].orders.length).toBe(0);
  });

  it('shiftHandover wykrywa przejście między zmianami', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.selectedOrder.set({ id: 1, numer_zlecenia: 'ZL', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'BCT', data_rozpoczecia: '2026-06-17 06:00:00', data_zakonczenia: '2026-06-17 22:00:00', zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null } as never);
    const handover = comp.shiftHandover();
    expect(handover).not.toBeNull();
    expect(handover!.from).toBe('06–14');
    expect(handover!.to).toBe('14–22');
  });

  it('nawigacja tygodnia przesuwa weekStart o 7 dni', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    comp.weekStart.set('2026-06-15');
    comp.nextPeriod();
    expect(comp.weekStart()).toBe('2026-06-22');
    comp.prevPeriod();
    expect(comp.weekStart()).toBe('2026-06-15');
  });

  it('weekLabelOf formatuje etykietę tygodnia (jak na mocku)', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    // Numer tygodnia ISO dla 15.06.2026 (poniedziałek) = 25, jak na mocku
    expect((comp as any).isoWeek(new Date('2026-06-15'))).toBe(25);
    // Stub tłumaczeń zwraca klucz — etykieta nie jest pusta i obejmuje oba dni zakresu
    expect(comp.weekLabelOf('2026-06-15')).toBeTruthy();
    expect(comp.weekLabelOf('')).toBe('');
  });

  it('setView przełącza widok (w tym "day")', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    expect(comp.viewMode()).toBe('week');
    comp.setView('day');
    expect(comp.viewMode()).toBe('day');
    comp.setView('month');
    expect(comp.viewMode()).toBe('month');
  });
});