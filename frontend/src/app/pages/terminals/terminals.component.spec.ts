/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { TerminalsComponent } from './terminals.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('TerminalsComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TerminalsComponent],
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

  function flushList() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
  }

  it('powinien utworzyć komponent i pobrać listę terminali', () => {
    const fixture = TestBed.createComponent(TerminalsComponent);
    fixture.detectChanges();
    flushList();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.terminals()).toEqual([]);
    expect(fixture.componentInstance.total()).toBe(0);
  });

  it('powinien otworzyć modal tworzenia z domyślnymi wartościami', () => {
    const fixture = TestBed.createComponent(TerminalsComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    expect(comp.modalMode()).toBe('create');
    expect(comp.modalNazwa()).toBe('');
    expect(comp.modalAdres()).toBe('');
    expect(comp.modalOperator()).toBe('');
    expect(comp.modalIsActive()).toBe(true);
  });

  it('powinien zablokować tworzenie przy pustej nazwie', () => {
    const fixture = TestBed.createComponent(TerminalsComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.saveModal();
    // Nie wysłano POST
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć terminal (POST /terminals)', () => {
    const fixture = TestBed.createComponent(TerminalsComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    comp.modalNazwa.set('Terminal Gdańsk');
    comp.modalAdres.set('ul. Portowa 1, Gdańsk');
    comp.modalOperator.set('Baltic Operator Sp. z o.o.');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/terminals`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.nazwa).toBe('Terminal Gdańsk');
    expect(req.request.body.operator).toBe('Baltic Operator Sp. z o.o.');

    req.flush({
      id: 4,
      nazwa: 'Terminal Gdańsk',
      adres: 'ul. Portowa 1, Gdańsk',
      operator: 'Baltic Operator Sp. z o.o.',
      telefon_operatora: null,
      email_operatora: null,
      is_active: true,
      created_at: null,
      updated_at: null,
    });

    // Po sukcesie lista przeładowana (GET /terminals)
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('statusLabel rozróżnia aktywny/nieaktywny', () => {
    const fixture = TestBed.createComponent(TerminalsComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    const active = {
      id: 1, nazwa: 'A', adres: 'ul. 1', operator: 'Op',
      telefon_operatora: null, email_operatora: null, is_active: true, created_at: null, updated_at: null,
    } as never;
    const inactive = {
      id: 2, nazwa: 'B', adres: 'ul. 2', operator: 'Op',
      telefon_operatora: null, email_operatora: null, is_active: false, created_at: null, updated_at: null,
    } as never;
    expect(comp.statusLabel(active)).toBe('terminale.status.active');
    expect(comp.statusLabel(inactive)).toBe('terminale.status.inactive');
  });
});