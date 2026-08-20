/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { Router, ActivatedRoute } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { of } from 'rxjs';

import { OrderNewComponent } from './order-new.component';
import { TranslateService } from '../../../services/translate.service';
import { environment } from '../../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('OrderNewComponent', () => {
  let httpMock: HttpTestingController;
  let routeId: string | null = null;
  const navigate = jasmine.createSpy('navigate');

  beforeEach(async () => {
    routeId = null;
    navigate.calls.reset();
    await TestBed.configureTestingModule({
      imports: [OrderNewComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: TranslateService, useClass: TranslateServiceStub },
        { provide: Router, useValue: { navigate } },
        { provide: ActivatedRoute, useValue: { paramMap: of({ get: () => routeId } as any) } },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function flushOptions() {
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
    httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`)).flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('bez parametru id działa w trybie tworzenia', () => {
    const fixture = TestBed.createComponent(OrderNewComponent);
    fixture.detectChanges();
    flushOptions();
    const comp = fixture.componentInstance;
    expect(comp.editingId()).toBeNull();
    expect(comp.loading()).toBe(false);
  });

  it('z parametrem id wczytuje zlecenie i wypełnia formularz', () => {
    routeId = '5';
    const fixture = TestBed.createComponent(OrderNewComponent);
    fixture.detectChanges();

    httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/5`).flush({
      id: 5, numer_zlecenia: 'ZL-EDYCJA', klient_nazwa: 'Klient', terminal_id: 3, terminal_nazwa: 'BCT',
      data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: 'rozładunek',
      wartosc_pln: 1234, status: 'w_realizacji', created_at: null, updated_at: null, employees: [], equipment: [],
    });
    flushOptions();

    const comp = fixture.componentInstance;
    expect(comp.editingId()).toBe(5);
    expect(comp.formNumer()).toBe('ZL-EDYCJA');
    expect(comp.formKlient()).toBe('Klient');
    expect(comp.formTerminalId()).toBe(3);
    expect(comp.formStatus()).toBe('w_realizacji');
    expect(comp.loading()).toBe(false);
  });

  it('zapis w trybie edycji wysyła PUT /orders/:id i wraca do /schedule', () => {
    routeId = '5';
    const fixture = TestBed.createComponent(OrderNewComponent);
    fixture.detectChanges();

    httpMock.expectOne((r) => r.method === 'GET' && r.url === `${environment.apiUrl}/orders/5`).flush({
      id: 5, numer_zlecenia: 'ZL-EDYCJA', klient_nazwa: 'Klient', terminal_id: 3, terminal_nazwa: 'BCT',
      data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: 'rozładunek',
      wartosc_pln: 1234, status: 'w_realizacji', created_at: null, updated_at: null, employees: [], equipment: [],
    });
    flushOptions();

    const comp = fixture.componentInstance;
    comp.save();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/orders/5`);
    expect(req.request.method).toBe('PUT');
    expect(req.request.body.numer_zlecenia).toBe('ZL-EDYCJA');
    expect(req.request.body.status).toBe('w_realizacji');
    req.flush({ id: 5, numer_zlecenia: 'ZL-EDYCJA', klient_nazwa: 'Klient', terminal_id: 3, terminal_nazwa: 'BCT', data_rozpoczecia: '2026-06-17 08:00:00', data_zakonczenia: '2026-06-17 16:00:00', zakres_prac: 'rozładunek', wartosc_pln: 1234, status: 'w_realizacji', created_at: null, updated_at: null, employees: [], equipment: [] });

    expect(navigate).toHaveBeenCalledWith(['/schedule']);
  });
});
