/// <reference types="jasmine" />
import 'fake-indexeddb/auto';
import { TestBed } from '@angular/core/testing';

import { IndexedDbService } from './indexed-db.service';

describe('IndexedDbService (PWA / offline-first)', () => {
  let service: IndexedDbService;

  beforeEach(async () => {
    TestBed.configureTestingModule({ providers: [IndexedDbService] });
    service = TestBed.inject(IndexedDbService);
    // Izolacja między testami: fake-indexeddb współdzieli bazę 'pbs-db' w obrębie
    // jednego przebiegu, więc po inicjalizacji czyścimy wszystkie store'y,
    // aby każdy test zaczynał od pustego stanu (bez wycieku danych między testami).
    await service.init();
    await Promise.all(['incidents_drafts', 'critical_data', 'user_notes'].map((s) => service.clear(s)));
  });

  it('init() tworzy bazy danych i object stores', async () => {
    await service.init();
    // Po inicjalizacji metody operacyjne działają bez jawnego init
    const drafts = await service.getAll('incidents_drafts');
    const critical = await service.getAll('critical_data');
    expect(drafts).toEqual([]);
    expect(critical).toEqual([]);
  });

  it('put()/get() zapisuje i odczytuje rekord', async () => {
    await service.init();
    await service.put('user_notes', { id: 1, tresc: 'Notatka' });
    const note = await service.get<{ id: number; tresc: string }>('user_notes', 1);
    expect(note).toEqual({ id: 1, tresc: 'Notatka' });
  });

  it('getAll() zwraca wszystkie rekordy', async () => {
    await service.init();
    await service.put('critical_data', { key: 'a', value: 1 });
    await service.put('critical_data', { key: 'b', value: 2 });

    const all = await service.getAll<{ key: string; value: number }>('critical_data');
    expect(all).toHaveSize(2);
  });

  it('delete() usuwa rekord', async () => {
    await service.init();
    await service.put('user_notes', { id: 7, tresc: 'Do usunięcia' });
    await service.delete('user_notes', 7);

    const note = await service.get('user_notes', 7);
    expect(note).toBeUndefined();
  });

  it('clear() czyści store', async () => {
    await service.init();
    await service.put('critical_data', { key: 'x', value: 5 });
    await service.clear('critical_data');

    const all = await service.getAll('critical_data');
    expect(all).toEqual([]);
  });
});
