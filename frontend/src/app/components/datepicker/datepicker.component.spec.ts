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

  it('powinien utworzyć komponent z natywnym input type="date"', () => {
    const { el } = create();
    const input = el.querySelector<HTMLInputElement>('input[type="date"]');
    expect(input).not.toBeNull();
  });

  it('powinien mieć domyślną ikonę kalendarza', () => {
    const { comp } = create();
    expect(comp.icon).toBe('calendar');
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

  it('onInput propaguje wartość do onChange i aktualizuje canClear', () => {
    const { comp, el } = create();
    const calls: string[] = [];
    comp.registerOnChange((v) => calls.push(v));
    comp.writeValue('');

    const input = el.querySelector<HTMLInputElement>('input[type="date"]')!;
    input.value = '2027-01-15';
    input.dispatchEvent(new Event('input'));

    expect(comp.value()).toBe('2027-01-15');
    expect(comp.canClear()).toBe(true);
    expect(calls).toContain('2027-01-15');
  });

  it('setDisabledState blokuje pole i ukrywa przycisk clear', () => {
    const { comp, fixture, el } = create();
    comp.writeValue('2026-08-09');
    comp.setDisabledState(true);
    fixture.detectChanges();

    expect(comp.disabledState()).toBe(true);
    expect(comp.canClear()).toBe(false);
    const input = el.querySelector<HTMLInputElement>('input')!;
    expect(input.disabled).toBe(true);
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