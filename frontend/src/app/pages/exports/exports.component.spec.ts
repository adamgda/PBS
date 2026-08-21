/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { ExportsComponent } from './exports.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('ExportsComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ExportsComponent],
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

  it('powinien utworzyć komponent z pięcioma zestawami eksportu', () => {
    const fixture = TestBed.createComponent(ExportsComponent);
    fixture.detectChanges();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.datasets.length).toBe(5);
  });

  it('powinien wygenerować CSV dla wybranego zestawu (GET /exports/orders)', () => {
    const fixture = TestBed.createComponent(ExportsComponent);
    fixture.detectChanges();
    const comp = fixture.componentInstance;

    comp.from.set('2026-01-01');
    comp.to.set('2026-01-31');

    // Stub API createObjectURL (niedostępne w jsdom) + blokada nawigacji przy kliknięciu.
    spyOn(URL, 'createObjectURL').and.returnValue('blob:mock');
    spyOn(URL, 'revokeObjectURL');
    spyOn(HTMLAnchorElement.prototype, 'click').and.callFake(() => {});

    comp.exportDataset(comp.datasets[0]);
    expect(comp.loadingType()).toBe('orders');

    const req = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/exports/orders`));
    expect(req.request.responseType).toBe('blob');
    expect(req.request.params.get('from')).toBe('2026-01-01');
    expect(req.request.params.get('to')).toBe('2026-01-31');
    req.flush(new Blob(['id,numer\r\n1,ZL/1\r\n'], { type: 'text/csv' }));

    expect(comp.loadingType()).toBeNull();
  });

  it('powinien zablokować eksport przy niepoprawnym zakresie dat', () => {
    const fixture = TestBed.createComponent(ExportsComponent);
    fixture.detectChanges();
    const comp = fixture.componentInstance;

    comp.from.set('2026-02-01');
    comp.to.set('2026-01-01');

    comp.exportDataset(comp.datasets[0]);
    expect(comp.loadingType()).toBeNull();

    const getReqs = httpMock.match((r) => r.method === 'GET');
    expect(getReqs.length).toBe(0);
  });
});
