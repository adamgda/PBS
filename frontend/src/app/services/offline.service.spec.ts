/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { HttpRequest } from '@angular/common/http';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { OfflineService } from './offline.service';

describe('OfflineService (PWA / background sync)', () => {
  let service: OfflineService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    localStorage.removeItem('pbs_offline_queue');
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), OfflineService],
    });
    service = TestBed.inject(OfflineService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.removeItem('pbs_offline_queue');
  });

  it('enqueue() dodaje żądanie do kolejki i zapisuje w localStorage', () => {
    const req = new HttpRequest('POST', '/api/awarie', { opis: 'Test' });
    service.enqueue(req);

    expect(service.queue().length).toBe(1);
    expect(service.queue()[0].method).toBe('POST');
    expect(service.queue()[0].url).toBe('/api/awarie');
    expect(localStorage.getItem('pbs_offline_queue')).toContain('/api/awarie');
  });

  it('isOffline() odzwierciedla stan online/offline', () => {
    service['_online'].set(true);
    expect(service.isOffline()).toBe(false);
    service['_online'].set(false);
    expect(service.isOffline()).toBe(true);
  });

  it('processQueue() wysyła żądania z kolejki i ją opróżnia po sukcesie', async () => {
    service.enqueue(new HttpRequest('POST', '/api/awarie', { opis: 'A' }));
    service.enqueue(new HttpRequest('PUT', '/api/awarie/2', { opis: 'B' }));

    const promise = service.processQueue();

    const req1 = httpMock.expectOne('/api/awarie');
    expect(req1.request.method).toBe('POST');
    req1.flush({ id: 1 });

    // Pozwól pętli przejść do kolejnego żądania (mikro-zadanie)
    await Promise.resolve();

    const req2 = httpMock.expectOne('/api/awarie/2');
    expect(req2.request.method).toBe('PUT');
    req2.flush({ id: 2 });

    await promise;
    expect(service.queue().length).toBe(0);
  });

  it('processQueue() zostawia w kolejce żądania, które nie przeszły', async () => {
    service.enqueue(new HttpRequest('POST', '/api/awarie', { opis: 'A' }));

    const promise = service.processQueue();
    httpMock.expectOne('/api/awarie').flush(
      { error: 'błąd sieci' },
      { status: 0, statusText: 'Unknown Error' },
    );

    await promise;
    expect(service.queue().length).toBe(1);
  });

  it('processQueue() nie robi nic, gdy offline', () => {
    service['_online'].set(false);
    service.enqueue(new HttpRequest('POST', '/api/awarie', { opis: 'A' }));

    service.processQueue();
    httpMock.expectNone('/api/awarie');
    expect(service.queue().length).toBe(1);
  });
});
