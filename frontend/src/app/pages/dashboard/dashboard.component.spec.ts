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

    const chartsReq = httpMock.expectOne(`${environment.apiUrl}/dashboard/charts`);
    chartsReq.flush({
      orders_trend: { categories: ['10.08', '11.08'], series: [2, 4], trend_pct: 33.3 },
      fleet_structure: { labels: ['Terminale', 'Pojazdy', 'Pracownicy', 'Inny sprzęt'], series: [3, 3, 5, 3] },
      terminal_turnover: { categories: ['Terminal Gdańsk', 'Terminal Gdynia'], series: [5000, 7500] },
      activity: [
        { type: 'order', title: 'ZL-2026-001 · Baltic Operator Sp. z o.o.', time: '2026-08-11 08:00:00' },
        { type: 'incident', title: 'Nieszczelny układ hydrauliczny', time: '2026-08-11 09:00:00' },
      ],
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

  it('charts() zapisuje dane wykresów i aktywności z API', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const comp = fixture.componentInstance;
    expect(comp.charts()?.orders_trend.series).toEqual([2, 4]);
    expect(comp.charts()?.orders_trend.trend_pct).toBe(33.3);
    expect(comp.charts()?.fleet_structure.series).toEqual([3, 3, 5, 3]);
    expect(comp.charts()?.terminal_turnover.categories).toEqual(['Terminal Gdańsk', 'Terminal Gdynia']);
    expect(comp.charts()?.activity).toHaveSize(2);
  });

  it('area() buduje serie wykresu z realnych danych i areaTrend() zwraca trend', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const comp = fixture.componentInstance;
    const area = comp.area();
    expect(area.series[0]).toEqual({ name: 'Zlecenia', data: [2, 4] });
    expect(area.xaxis.categories).toEqual(['10.08', '11.08']);
    expect(comp.areaTrend()).toBe(33.3);
  });

  it('donut() i bar() używają realnych danych z API', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const comp = fixture.componentInstance;
    expect(comp.donut().series).toEqual([3, 3, 5, 3]);
    expect(comp.donut().labels).toEqual(['Terminale', 'Pojazdy', 'Pracownicy', 'Inny sprzęt']);
    expect(comp.bar().series[0].data).toEqual([5000, 7500]);
    expect(comp.bar().xaxis.categories).toEqual(['Terminal Gdańsk', 'Terminal Gdynia']);
  });

  it('activity() mapuje realne zdarzenia na oś czasu', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const activity = fixture.componentInstance.activity();
    expect(activity).toHaveSize(2);
    expect(activity[0].titleKey).toBe('dashboard.activity.item_order_created');
    expect(activity[0].title).toBe('ZL-2026-001 · Baltic Operator Sp. z o.o.');
    expect(activity[1].titleKey).toBe('dashboard.activity.item_incident_reported');
  });

  it('kpis() nie zawiera już statycznych trendów/sparklin', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    flushData();

    const kpis = fixture.componentInstance.kpis();
    expect(Object.keys(kpis[0])).not.toContain('trend');
    expect(Object.keys(kpis[0])).not.toContain('sparkline');
  });
});
