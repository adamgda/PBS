/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { IncidentNewComponent } from './incident-new.component';
import { TranslateService } from '../../../services/translate.service';
import { environment } from '../../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('IncidentNewComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [IncidentNewComponent],
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

  function flushEquipment() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent z domyślnymi wartościami formularza', () => {
    const fixture = TestBed.createComponent(IncidentNewComponent);
    fixture.detectChanges();
    flushEquipment();

    const comp = fixture.componentInstance;
    expect(comp).toBeTruthy();
    expect(comp.formTyp()).toBe('sprzet');
    expect(comp.formEquipmentId()).toBeNull();
    expect(comp.formOpis()).toBe('');
  });

  it('powinien zablokować zgłoszenie przy pustym opisie', () => {
    const fixture = TestBed.createComponent(IncidentNewComponent);
    fixture.detectChanges();
    flushEquipment();

    const comp = fixture.componentInstance;
    comp.saveCreate();
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien zgłosić awarię (POST /incidents)', () => {
    const fixture = TestBed.createComponent(IncidentNewComponent);
    fixture.detectChanges();
    flushEquipment();

    const comp = fixture.componentInstance;
    comp.formTyp.set('inne');
    comp.formOpis.set('Awaria światła');
    comp.saveCreate();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/incidents`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.typ).toBe('inne');
    expect(req.request.body.opis).toBe('Awaria światła');
    req.flush({
      id: 1, typ: 'inne', equipment_id: null, equipment_nazwa: null, opis: 'Awaria światła',
      status: 'zgloszona', data_zgloszenia: null, data_zakonczenia: null,
      zgloszona_przez: 1, zgloszona_przez_email: null, created_at: null, updated_at: null,
    });
  });
});
