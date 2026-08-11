/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { EquipmentComponent } from './equipment.component';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('EquipmentComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EquipmentComponent],
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

  function flushList() {
    const req = httpMock.expectOne(
      (r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`),
    );
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
    // Konstruktor ładuje też opcje pracownika/terminala (filtry + autocomplete).
    flushOptions();
  }

  /** Obsługuje żądania opcji (GET /employees + /terminals) wysyłane przez loadOptions(). */
  function flushOptions() {
    const empReq = httpMock.match((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/employees`));
    for (const r of empReq) r.flush({ data: [], total: 0, page: 1, per_page: 100 });
    const termReq = httpMock.match((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/terminals`));
    for (const r of termReq) r.flush({ data: [], total: 0, page: 1, per_page: 100 });
  }

  it('powinien utworzyć komponent i pobrać listę sprzętu', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();
    expect(fixture.componentInstance).toBeTruthy();
    expect(fixture.componentInstance.equipment()).toEqual([]);
    expect(fixture.componentInstance.total()).toBe(0);
  });

  it('powinien otworzyć modal tworzenia z domyślnymi wartościami', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    flushOptions();
    expect(comp.modalMode()).toBe('create');
    expect(comp.modalNazwa()).toBe('');
    expect(comp.modalKategoria()).toBe('inne');
    expect(comp.modalIsActive()).toBe(true);
  });

  it('powinien zablokować tworzenie przy pustej nazwie', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    flushOptions();
    comp.saveModal();
    // Nie wysłano POST
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });
  it('powinien utworzyć sprzęt (POST /equipment)', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    flushOptions();
    comp.modalNazwa.set('Wózek widłowy');
    comp.modalKategoria.set('inne');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/equipment`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.nazwa).toBe('Wózek widłowy');
    expect(req.request.body.kategoria).toBe('inne');

    req.flush({
      id: 10, kategoria: 'inne', nazwa: 'Wózek widłowy', numer_seryjny: null,
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: true, created_at: null, updated_at: null,
    });

    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('powinien utworzyć pojazd z danymi vehicle_details', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    comp.openCreate();
    flushOptions();
    comp.modalKategoria.set('pojazd');
    comp.modalNazwa.set('Ford Transit');
    comp.modalPrzebieg.set('125000');
    comp.saveModal();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/equipment`);
    expect(req.request.body.kategoria).toBe('pojazd');
    expect(req.request.body.ostatni_przebieg).toBe(125000);

    req.flush({
      id: 11, kategoria: 'pojazd', nazwa: 'Ford Transit', numer_seryjny: null,
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: true, created_at: null, updated_at: null,
    });

    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/equipment`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(comp.modalMode()).toBeNull();
  });

  it('categoryLabel i statusLabel rozróżniają wartości', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    const pojazd = {
      id: 1, kategoria: 'pojazd', nazwa: 'A', numer_seryjny: null,
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: true, created_at: null, updated_at: null,
    } as never;
    const inne = {
      id: 2, kategoria: 'inne', nazwa: 'B', numer_seryjny: null,
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: false, created_at: null, updated_at: null,
    } as never;
    expect(comp.categoryLabel(pojazd)).toBe('sprzet.list.category_vehicle');
    expect(comp.categoryLabel(inne)).toBe('sprzet.list.category_other');
    expect(comp.statusLabel(pojazd)).toBe('sprzet.status.active');
    expect(comp.statusLabel(inne)).toBe('sprzet.status.inactive');
  });

  it('powinien otworzyć modal szczegółów i pobrać pełne dane', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    const eq = {
      id: 1, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1',
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: true, created_at: null, updated_at: null,
    } as never;
    comp.openDetails(eq);

    const req = httpMock.expectOne(`${environment.apiUrl}/equipment/1`);
    req.flush({
      id: 1, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1',
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: 1000, is_active: true,
      vehicle_details: { equipment_id: 1, ostatni_przebieg: 1000, ostatni_serwis_olejowy: null, ostatnia_awaria: null, data_ostatniej_oc: null, wynik_ostatniej_oc: null },
      service_plans: [{ id: 3, equipment_id: 1, typ_przegladu: 'olejowy', interwal_km: 15000, interwal_dni: null, data_ostatniego_wykonania: null, data_nastepnego_planowanego: null, is_active: true, needs_service: false }],
      timeline: [{ id: 1, equipment_id: 1, typ: 'przypisanie', opis: 'Utworzono', data: '2026-01-01', created_by: null }],
      created_at: null, updated_at: null,
    });

    expect(comp.detailsPlans().length).toBe(1);
    expect(comp.detailsTimeline().length).toBe(1);
    expect(comp.detailsEquipment()?.vehicle_details?.ostatni_przebieg).toBe(1000);
  });

  it('powinien zaznaczyć sprzęt i pobrać oś czasu + plany do sekcji pod tabelą', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    const eq = {
      id: 1, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1',
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: null, is_active: true, created_at: null, updated_at: null,
    } as never;
    comp.selectEquipment(eq);

    const req = httpMock.expectOne(`${environment.apiUrl}/equipment/1`);
    req.flush({
      id: 1, kategoria: 'pojazd', nazwa: 'Ford', numer_seryjny: 'FT-1',
      current_employee_id: null, employee_name: null, current_terminal_id: null, terminal_nazwa: null,
      ostatni_przebieg: 1000, is_active: true,
      vehicle_details: { equipment_id: 1, ostatni_przebieg: 1000, ostatni_serwis_olejowy: null, ostatnia_awaria: null, data_ostatniej_oc: null, wynik_ostatniej_oc: null },
      service_plans: [{ id: 3, equipment_id: 1, typ_przegladu: 'olejowy', interwal_km: 500, interwal_dni: 90, data_ostatniego_wykonania: null, data_nastepnego_planowanego: null, is_active: true, needs_service: true }],
      timeline: [{ id: 1, equipment_id: 1, typ: 'przypisanie', opis: 'Utworzono', data: '2026-01-01', created_by: null }],
      created_at: null, updated_at: null,
    });

    expect(comp.selectedEquipment()?.id).toBe(1);
    expect(comp.selectedPlans().length).toBe(1);
    expect(comp.selectedTimeline().length).toBe(1);
    expect(comp.planCycle(comp.selectedPlans()[0])).toBe('co 500 km / 90 dni');
    expect(comp.planStatus(comp.selectedPlans()[0]).labelKey).toBe('sprzet.service_plans.status_upcoming');

    comp.clearSelection();
    expect(comp.selectedEquipment()).toBeNull();
  });

  it('planStatus rozróżnia statusy planów przeglądu', () => {
    const fixture = TestBed.createComponent(EquipmentComponent);
    fixture.detectChanges();
    flushList();

    const comp = fixture.componentInstance;
    const needsService = {
      id: 1, equipment_id: 1, typ_przegladu: 'olejowy', interwal_km: 500, interwal_dni: 90,
      data_ostatniego_wykonania: null, data_nastepnego_planowanego: null, is_active: true, needs_service: true,
    } as never;
    const scheduled = {
      id: 2, equipment_id: 1, typ_przegladu: 'UDT', interwal_km: null, interwal_dni: 365,
      data_ostatniego_wykonania: null, data_nastepnego_planowanego: null, is_active: true, needs_service: false,
    } as never;
    const inactive = {
      id: 3, equipment_id: 1, typ_przegladu: 'techniczny', interwal_km: null, interwal_dni: null,
      data_ostatniego_wykonania: null, data_nastepnego_planowanego: null, is_active: false, needs_service: false,
    } as never;

    expect(comp.planStatus(needsService).labelKey).toBe('sprzet.service_plans.status_upcoming');
    expect(comp.planStatus(needsService).tone).toBe('warning');
    expect(comp.planStatus(scheduled).labelKey).toBe('sprzet.service_plans.status_scheduled');
    expect(comp.planStatus(scheduled).tone).toBe('success');
    expect(comp.planStatus(inactive).labelKey).toBe('sprzet.service_plans.status_inactive');
    expect(comp.planStatus(inactive).tone).toBe('neutral');
  });
});