/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { FormsModule } from '@angular/forms';

import { DatepickerComponent } from './datepicker.component';
import { TranslateService } from '../../services/translate.service';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

/** Porównuje, czy dwa `Date` dotyczą tego samego dnia (lokalnie). */
function sameDayForTest(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

describe('DatepickerComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DatepickerComponent, FormsModule],
      providers: [
        provideRouter([]),
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
  });

  function create() {
    const fixture = TestBed.createComponent(DatepickerComponent);
    const comp = fixture.componentInstance;
    fixture.detectChanges();
    return { fixture, comp, el: fixture.nativeElement as HTMLElement };
  }

  it('powinien utworzyć komponent z własnym triggerem (bez natywnego input type="date")', () => {
    const { el } = create();
    const native = el.querySelector<HTMLInputElement>('input[type="date"]');
    expect(native).toBeNull();
    const trigger = el.querySelector<HTMLButtonElement>('button[type="button"]');
    expect(trigger).not.toBeNull();
  });

  it('powinien mieć domyślną ikonę kalendarza', () => {
    const { comp } = create();
    expect(comp.icon).toBe('calendar');
  });

  it('powinien otwierać i zamykać kalendarz przez toggle()', () => {
    const { comp, fixture } = create();
    expect(comp.open()).toBe(false);

    comp.toggle();
    fixture.detectChanges();
    expect(comp.open()).toBe(true);

    comp.toggle();
    fixture.detectChanges();
    expect(comp.open()).toBe(false);
  });

  it('selectDay ustawia wartość ISO, wywołuje onChange i zamyka kalendarz', () => {
    const { comp, fixture } = create();
    const calls: string[] = [];
    comp.registerOnChange((v) => calls.push(v));
    comp.toggle();
    fixture.detectChanges();

    comp.selectDay(new Date(2026, 7, 9));
    expect(comp.value()).toBe('2026-08-09');
    expect(calls).toContain('2026-08-09');
    expect(comp.open()).toBe(false);
    expect(comp.canClear()).toBe(true);
  });

  it('min/max wyłączają dni poza zakresem', () => {
    const { comp, fixture } = create();
    comp.min = '2026-01-01';
    comp.max = '2026-01-31';
    comp.viewYear.set(2026);
    comp.viewMonth.set(0);
    fixture.detectChanges();

    const cells = comp.cells();
    const before = cells.find((c) => sameDayForTest(c.date, new Date(2025, 11, 31)));
    const inside = cells.find((c) => sameDayForTest(c.date, new Date(2026, 0, 15)));
    const after = cells.find((c) => sameDayForTest(c.date, new Date(2026, 1, 1)));
    expect(before?.disabled).toBe(true);
    expect(inside?.disabled).toBe(false);
    expect(after?.disabled).toBe(true);
  });

  it('goToday wybiera dzisiejszą datę, gdy jest w zakresie min/max', () => {
    const { comp, fixture } = create();
    const calls: string[] = [];
    comp.registerOnChange((v) => calls.push(v));
    comp.min = '2000-01-01';
    comp.max = '2100-01-01';

    comp.goToday();
    fixture.detectChanges();

    expect(comp.value()).toBeTruthy();
    expect(calls.length).toBe(1);
  });

  it('writeValue ustawia wartość i pokazuje przycisk clear', () => {
    const { comp } = create();
    comp.writeValue('2026-08-09');
    expect(comp.value()).toBe('2026-08-09');
    expect(comp.canClear()).toBe(true);
  });

  it('writeValue z null/undefined czyści pole i ukrywa przycisk clear', () => {
    const { comp } = create();
    comp.writeValue('2026-08-09');
    comp.writeValue(null);
    expect(comp.value()).toBe('');
    expect(comp.canClear()).toBe(false);
  });

  it('clear czyści wartość, emituje onChange i cleared', () => {
    const { comp } = create();
    const calls: string[] = [];
    let cleared = false;
    comp.registerOnChange((v) => calls.push(v));
    comp.cleared.subscribe(() => (cleared = true));
    comp.writeValue('2026-08-09');

    comp.clear();

    expect(comp.value()).toBe('');
    expect(comp.canClear()).toBe(false);
    expect(calls).toContain('');
    expect(cleared).toBe(true);
  });

  it('setDisabledState blokuje trigger i ukrywa przycisk clear', () => {
    const { comp, fixture, el } = create();
    comp.writeValue('2026-08-09');
    comp.setDisabledState(true);
    fixture.detectChanges();

    expect(comp.disabledState()).toBe(true);
    expect(comp.canClear()).toBe(false);
    const trigger = el.querySelector<HTMLButtonElement>('button[type="button"]')!;
    expect(trigger.disabled).toBe(true);
  });

  it('powinien renderować etykietę z labelKey', () => {
    const { el } = create();
    const fixture = TestBed.createComponent(DatepickerComponent);
    fixture.componentInstance.labelKey = 'pracownicy.certificates.expiry_date';
    fixture.detectChanges();
    const label = (fixture.nativeElement as HTMLElement).querySelector('label');
    expect(label).not.toBeNull();
    expect(label!.textContent!.trim()).toContain('pracownicy.certificates.expiry_date');
  });
});