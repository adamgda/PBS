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
});