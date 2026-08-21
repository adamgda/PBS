/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';

import { NotificationService } from './notification.service';
import { DashboardService } from './dashboard.service';
import { DashboardAlerts, DashboardCharts } from '../models/dashboard.model';

class DashboardServiceStub {
  alerts = jasmine.createSpy('alerts');
  charts = jasmine.createSpy('charts');
}

const ALERTS: DashboardAlerts = {
  expiring_certs: { count: 2, items: [{ id: 1, nazwa: 'Cert A' }, { id: 2, nazwa: 'Cert B' }] },
  upcoming_inspections: { count: 1, items: [{ id: 3, typ_przegladu: 'Serwis olejowy' }] },
  unresolved_incidents: { count: 0, items: [] },
  returning_from_leave: { count: 1, items: [{ id: 4, imie: 'Jan', nazwisko: 'Kowalski' }] },
};

const CHARTS: DashboardCharts = {
  orders_trend: { categories: [], series: [], trend_pct: 0 },
  fleet_structure: { labels: [], series: [] },
  terminal_turnover: { categories: [], series: [] },
  activity: [
    { type: 'order', title: 'ZLE-001', time: '2026-08-21 10:00:00' },
    { type: 'incident', title: 'Awaria wózka', time: '2026-08-21 09:00:00' },
  ],
};

describe('NotificationService', () => {
  let service: NotificationService;
  let dashboard: DashboardServiceStub;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        NotificationService,
        { provide: DashboardService, useClass: DashboardServiceStub },
      ],
    });

    service = TestBed.inject(NotificationService);
    dashboard = TestBed.inject(DashboardService) as unknown as DashboardServiceStub;
  });

  it('powinien utworzyć serwis', () => {
    expect(service).toBeTruthy();
  });

  it('powinien wyliczać totalCount jako sumę liczników grup', () => {
    dashboard.alerts.and.returnValue(of(ALERTS));
    dashboard.charts.and.returnValue(of(CHARTS));
    service.load();

    expect(service.totalCount()).toBe(4);
    expect(service.alerts()).toEqual(ALERTS);
    expect(service.activity().length).toBe(2);
    expect(service.loading()).toBeFalse();
  });

  it('powinien zwracać 0 dla totalCount przy braku danych', () => {
    dashboard.alerts.and.returnValue(of(null));
    dashboard.charts.and.returnValue(of(CHARTS));
    service.load();

    expect(service.totalCount()).toBe(0);
  });

  it('nie powinien blokować alertów przy błędzie aktywności', () => {
    dashboard.alerts.and.returnValue(of(ALERTS));
    dashboard.charts.and.returnValue(throwError(() => new Error('network')));
    service.load();

    expect(service.alerts()).toEqual(ALERTS);
    expect(service.totalCount()).toBe(4);
  });
});
