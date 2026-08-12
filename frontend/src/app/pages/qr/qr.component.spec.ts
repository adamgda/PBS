import { TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { QrComponent } from './qr.component';
import { QrService } from '../../services/qr.service';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

class TranslateServiceStub {
  instant(key: string): string {
    return key;
  }
}

class ActivatedRouteStub {
  snapshot = {
    paramMap: { get: (key: string): string | null => (key === 'token' ? 'tok123' : null) },
  };
}

describe('QrComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [QrComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        QrService,
        { provide: ActivatedRoute, useClass: ActivatedRouteStub },
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function create() {
    const fixture = TestBed.createComponent(QrComponent);
    fixture.detectChanges();
    return { fixture, component: fixture.componentInstance };
  }

  it('powinien utworzyć komponent', () => {
    const { component } = create();
    expect(component).toBeTruthy();
    httpMock.expectOne(`${environment.apiUrl}/qr/tok123`).flush({
      id: 5, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1', is_active: true,
    });
  });

  it('powinien wczytać maszynę i pokazać wybór akcji', () => {
    const { fixture, component } = create();
    const req = httpMock.expectOne(`${environment.apiUrl}/qr/tok123`);
    req.flush({ id: 5, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1', is_active: true });
    fixture.detectChanges();

    expect(component.loading()).toBe(false);
    expect(component.notFound()).toBe(false);
    expect(component.machine()?.nazwa).toBe('Ford');

    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('qr.actions.incident');
    expect(el.textContent).toContain('qr.actions.daily_report');
  });

  it('powinien ustawić notFound przy 404', () => {
    const { fixture, component } = create();
    const req = httpMock.expectOne(`${environment.apiUrl}/qr/tok123`);
    req.flush({ error: 'Machine not found' }, { status: 404, statusText: 'Not Found' });
    fixture.detectChanges();

    expect(component.loading()).toBe(false);
    expect(component.notFound()).toBe(true);
    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('qr.errors.not_found_title');
  });

  it('powinien walidować opis przy zgłoszeniu awarii', () => {
    const { fixture, component } = create();
    httpMock.expectOne(`${environment.apiUrl}/qr/tok123`).flush({
      id: 5, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: null, is_active: true,
    });
    fixture.detectChanges();

    component.selectAction('incident');
    fixture.detectChanges();

    component.submitIncident();
    expect(component.error()).toBe('qr.errors.description_required');
    expect(component.submitting()).toBe(false);
  });

  it('powinien wysłać zgłoszenie awarii i pokazać numer', () => {
    const { fixture, component } = create();
    httpMock.expectOne(`${environment.apiUrl}/qr/tok123`).flush({
      id: 5, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: null, is_active: true,
    });
    fixture.detectChanges();

    component.selectAction('incident');
    component.incidentOpis.set('Silnik stuka');
    component.submitIncident();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/qr/tok123/incident`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ opis: 'Silnik stuka', kontakt: null });
    req.flush({ id: 7, numer_zgloszenia: 'AWR-000007', status: 'zgloszona' });
    fixture.detectChanges();

    expect(component.incidentDone()).toEqual({ number: 'AWR-000007' });
  });

  it('powinien wysłać raport OC i przejść w stan sukcesu', () => {
    const { fixture, component } = create();
    httpMock.expectOne(`${environment.apiUrl}/qr/tok123`).flush({
      id: 5, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: null, is_active: true,
    });
    fixture.detectChanges();

    component.selectAction('daily_report');
    component.ocPrzebieg.set('500');
    component.ocOpis.set('Sprawdzono');
    component.submitDailyReport();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/qr/tok123/daily-report`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ aktualny_przebieg: 500, przebieg_oc: 'Sprawdzono', uwagi: null });
    req.flush({ id: 3, equipment_id: 5, data_raportu: '2026-08-20', status: 'ok' });
    fixture.detectChanges();

    expect(component.reportDone()).toBe(true);
  });
});
