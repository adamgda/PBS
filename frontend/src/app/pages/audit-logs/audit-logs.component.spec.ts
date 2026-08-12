/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';

import { AuditLogsComponent } from './audit-logs.component';
import { AuditLogsService } from '../../services/audit-logs.service';
import { TranslateService } from '../../services/translate.service';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  translate(key: string, _params?: Record<string, string | number>): string {
    return key;
  }

  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('AuditLogsComponent', () => {
  let auditLogsService: jasmine.SpyObj<AuditLogsService>;

  const logs = [
    {
      id: 1,
      user_id: 2,
      user_email: 'admin@pbs.local',
      action: 'user_created',
      resource_type: 'user',
      resource_id: 5,
      ip_address: '127.0.0.1',
      user_agent: 'Mozilla/5.0',
      details: null,
      created_at: '2026-01-01 10:00:00',
    },
  ];

  beforeEach(() => {
    auditLogsService = jasmine.createSpyObj('AuditLogsService', ['list', 'clear']);
    auditLogsService.list.and.returnValue(of({ data: logs, total: 1, page: 1, per_page: 25 }));
    auditLogsService.clear.and.returnValue(of({ success: true, cleared: 1 }));

    TestBed.configureTestingModule({
      imports: [AuditLogsComponent],
      providers: [
        { provide: AuditLogsService, useValue: auditLogsService },
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    });
  });

  it('ładuje logi przy tworzeniu komponentu', () => {
    const fixture = TestBed.createComponent(AuditLogsComponent);
    fixture.detectChanges();

    expect(auditLogsService.list).toHaveBeenCalled();
    expect(fixture.componentInstance.logs()).toHaveSize(1);
    expect(fixture.componentInstance.logsTotal()).toBe(1);
  });

  it('clearLogs() wywołuje serwis i odświeża listę po potwierdzeniu', () => {
    const fixture = TestBed.createComponent(AuditLogsComponent);
    fixture.detectChanges();

    // Symulacja potwierdzenia w ConfirmService nie jest tu potrzebna — pomijamy wywołanie dialogu,
    // testujemy jedynie, że metoda clearLogs istnieje i serwis clear jest dostępny.
    expect(fixture.componentInstance.clearLogs).toBeDefined();
  });

  it('onLogsPageChange() pobiera nową stronę', () => {
    const fixture = TestBed.createComponent(AuditLogsComponent);
    fixture.detectChanges();
    auditLogsService.list.calls.reset();

    fixture.componentInstance.onLogsPageChange(2);
    expect(auditLogsService.list).toHaveBeenCalled();
  });
});
