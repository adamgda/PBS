/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { DashboardComponent } from './dashboard.component';
import { AuthService } from '../../services/auth.service';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

/** Stub AuthService — zwraca zalogowanego użytkownika. */
class AuthServiceStub {
  currentUser = { email: 'jan.kowalski@pbs.local' };
}

describe('DashboardComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AuthService, useClass: AuthServiceStub },
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function flushData() {
    const summaryReq = httpMock.expectOne(`${environment.apiUrl}/dashboard/summary`);
    summaryReq.flush({
      active_employees: 248,
      active_terminals: 12,
      vehicles_in_use: 86,
      active_incidents: 3,
      hours_today: 42.5,
      employees_on_leave: 5,
      monthly_wages: 20056.0,
    });

    const alertsReq = httpMock.expectOne(`${environment.apiUrl}/dashboard/alerts`);
    alertsReq.flush({
      expiring_certs: { count: 7, items: [] },
      upcoming_inspections: { count: 4, items: [] },
      unresolved_incidents: { count: 3, items: [] },
      returning_from_leave: { count: 5, items: [] },
    });
  }

  it('powinien utworzyć komponent i pobrać dane KPI oraz alerty', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const comp = fixture.componentInstance;
    expect(comp).toBeTruthy();
    expect(comp.summary()?.active_employees).toBe(248);
    expect(comp.summary()?.monthly_wages).toBe(20056.0);
    expect(comp.alertsData()?.expiring_certs.count).toBe(7);
    expect(comp.alertsData()?.unresolved_incidents.count).toBe(3);
  });

  it('kpis() mapuje podsumowanie na karty KPI', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const kpis = fixture.componentInstance.kpis();
    expect(kpis).toHaveSize(4);
    expect(kpis[0].value).toBe(248);
    expect(kpis[1].value).toBe(12);
    expect(kpis[2].value).toBe(86);
    expect(kpis[3].value).toBe(3);
  });

  it('alerts() mapuje dane na liczniki alertów', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const alerts = fixture.componentInstance.alerts();
    expect(alerts).toHaveSize(4);
    expect(alerts[0].count).toBe(7);
    expect(alerts[1].count).toBe(4);
    expect(alerts[2].count).toBe(3);
    expect(alerts[3].count).toBe(5);
  });

  it('shortcuts() zwraca skróty akcji', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const shortcuts = fixture.componentInstance.shortcuts();
    expect(shortcuts).toHaveSize(3);
    expect(shortcuts[0].route).toBe('/awaria');
    expect(shortcuts[1].route).toBe('/reporting');
    expect(shortcuts[2].route).toBe('/harmonogram');
  });

  it('userName() wywodzi imię z e-maila', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    expect(fixture.componentInstance.userName()).toBe('Jan.kowalski');
  });
});
