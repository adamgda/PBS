/// <reference types="jasmine" />
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal, Signal } from '@angular/core';
import { provideRouter } from '@angular/router';

import { NotificationBellComponent } from './notification-bell.component';
import { NotificationService } from '../../services/notification.service';
import { TranslateService } from '../../services/translate.service';
import { DashboardAlerts, DashboardActivityItem } from '../../models/dashboard.model';

const ALERTS: DashboardAlerts = {
  expiring_certs: { count: 1, items: [{ id: 1, employee_id: 3, nazwa: 'Cert A', imie: 'Jan', nazwisko: 'Kowalski', data_waznosci: '2026-09-01' }] },
  upcoming_inspections: { count: 0, items: [] },
  unresolved_incidents: { count: 1, items: [{ id: 5, opis: 'Awaria wózka', status: 'zgloszona', data_zgloszenia: '2026-08-21 09:00:00' }] },
  returning_from_leave: { count: 0, items: [] },
};

const ACTIVITY: DashboardActivityItem[] = [
  { type: 'order', title: 'ZLE-001', time: '2026-08-21 10:00:00' },
];

class NotificationServiceStub {
  alerts: Signal<DashboardAlerts | null> = signal<DashboardAlerts | null>(ALERTS);
  activity: Signal<DashboardActivityItem[]> = signal<DashboardActivityItem[]>(ACTIVITY);
  loading = signal(false);
  totalCount = signal(2);
  load = jasmine.createSpy('load');
  openDocument = jasmine.createSpy('openDocument');
}

class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('NotificationBellComponent', () => {
  let fixture: ComponentFixture<NotificationBellComponent>;
  let comp: NotificationBellComponent;
  let notifications: NotificationServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NotificationBellComponent],
      providers: [
        provideRouter([]),
        { provide: NotificationService, useClass: NotificationServiceStub },
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NotificationBellComponent);
    comp = fixture.componentInstance;
    notifications = TestBed.inject(NotificationService) as unknown as NotificationServiceStub;
    fixture.detectChanges();
  });

  it('powinien utworzyć komponent i załadować dane', () => {
    expect(comp).toBeTruthy();
    expect(notifications.load).toHaveBeenCalled();
  });

  it('powinien otwierać i zamykać panel', () => {
    expect(comp.open()).toBeFalse();
    comp.toggle();
    expect(comp.open()).toBeTrue();
    comp.close();
    expect(comp.open()).toBeFalse();
  });

  it('powinien budować grupy alertów z licznikami i trasami', () => {
    const groups = comp.groups();
    expect(groups.length).toBe(4);
    const certs = groups.find((g) => g.key === 'powiadomienia.groups.expiring_certs');
    expect(certs?.count).toBe(1);
    expect(certs?.items[0].title).toBe('Cert A');
    expect(certs?.items[0].route).toBe('/employees');
    const incidents = groups.find((g) => g.key === 'powiadomienia.groups.unresolved_incidents');
    expect(incidents?.items[0].route).toBe('/incidents/5');
  });

  it('powinien zwracać ostatnią aktywność', () => {
    const items = comp.activityItems();
    expect(items.length).toBe(1);
    expect(items[0].title).toBe('ZLE-001');
  });

  it('Esc zamyka panel', () => {
    comp.open.set(true);
    comp.onKeydown(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(comp.open()).toBeFalse();
  });

  it('kliknięcie poza komponentem zamyka panel', () => {
    comp.open.set(true);
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(comp.open()).toBeFalse();
  });

  it('kliknięcie wewnątrz komponentu nie zamyka panelu', () => {
    comp.open.set(true);
    const host = fixture.nativeElement as HTMLElement;
    host.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(comp.open()).toBeTrue();
  });

  it('kliknięcie alertu certyfikatu zgłasza intencję otwarcia dokumentu', () => {
    const cert = comp.groups().find((g) => g.key === 'powiadomienia.groups.expiring_certs');
    const item = cert?.items[0];
    expect(item?.employeeId).toBe(3);
    comp.onAlertClick(item!);
    expect(notifications.openDocument).toHaveBeenCalledWith(3, 1);
  });
});
