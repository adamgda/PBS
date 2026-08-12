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
    // load() → GET /orders, loadOptions() → GET /employees
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/orders`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent i pobrać zlecenia', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.orders()).toEqual([]);
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

  it('klik na kalendarz nie otwiera modala edycji, tylko zaznacza zlecenie', () => {
    const fixture = TestBed.createComponent(OrdersComponent);
    fixture.detectChanges();
    flushAllInitial();

    const comp = fixture.componentInstance;
    (comp as any)._orders.set([
      { id: 1, numer_zlecenia: 'ZL-1', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'BCT', data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null } as never,
    ]);
    comp.onCalendarEventClick({ id: 1, title: '', date: '' });

    expect(comp.modalMode()).toBeNull();
    expect(comp.selectedOrder()?.id).toBe(1);

    // loadDetails() → GET /orders/1
    httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/1`).flush({
      id: 1, numer_zlecenia: 'ZL-1', klient_nazwa: 'K', terminal_id: 1, terminal_nazwa: 'BCT', data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: '', wartosc_pln: 0, status: 'nowe', created_at: null, updated_at: null, employees: [], equipment: [],
    } as never);

    expect(comp.selectedOrder()?.numer_zlecenia).toBe('ZL-1');
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