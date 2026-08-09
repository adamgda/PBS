/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';

import { FilterBarComponent, FilterConfig } from './filter-bar.component';
import { TranslateService } from '../../services/translate.service';

class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('FilterBarComponent', () => {
  let filters: FilterConfig[];

  beforeEach(async () => {
    filters = [
      { key: 'nazwa', label: 'Nazwa', type: 'text', placeholder: 'Szukaj' },
      { key: 'is_active', label: 'Status', type: 'select', options: [{ value: '1', label: 'Aktywny' }] },
    ];

    await TestBed.configureTestingModule({
      imports: [FilterBarComponent],
      providers: [{ provide: TranslateService, useClass: TranslateServiceStub }],
    }).compileComponents();
  });

  function create() {
    const fixture = TestBed.createComponent(FilterBarComponent);
    const comp = fixture.componentInstance;
    comp.filtersInput = filters;
    fixture.detectChanges();
    return { fixture, comp, el: fixture.nativeElement as HTMLElement };
  }

  it('powinien startować zwinięty na mobile', () => {
    const { comp } = create();
    expect(comp.mobileExpanded()).toBe(false);
  });

  it('toggleMobile powinien przełączać stan rozwinięcia', () => {
    const { comp } = create();
    comp.toggleMobile();
    expect(comp.mobileExpanded()).toBe(true);
    comp.toggleMobile();
    expect(comp.mobileExpanded()).toBe(false);
  });

  it('activeFilterCount powinien liczyć niepuste filtry', () => {
    const { comp } = create();
    expect(comp.activeFilterCount()).toBe(0);
    comp.onFilterChange('nazwa', 'Gda');
    expect(comp.activeFilterCount()).toBe(1);
    comp.onFilterChange('is_active', '1');
    expect(comp.activeFilterCount()).toBe(2);
    // pusta wartość nie liczy się
    comp.onFilterChange('nazwa', '   ');
    expect(comp.activeFilterCount()).toBe(1);
  });

  it('onApply powinien emitować filtry i zwijać panel', () => {
    const { comp } = create();
    comp.toggleMobile();
    expect(comp.mobileExpanded()).toBe(true);

    let emitted: Record<string, string> | null = null;
    comp.filterApply.subscribe((v) => (emitted = v));
    comp.onFilterChange('nazwa', 'Gda');
    comp.onApply();

    expect(emitted!).toEqual({ nazwa: 'Gda' });
    expect(comp.mobileExpanded()).toBe(false);
  });

  it('onClear powinien resetować filtry i licznik', () => {
    const { comp } = create();
    comp.onFilterChange('nazwa', 'Gda');
    expect(comp.activeFilterCount()).toBe(1);

    let cleared = false;
    comp.filterClear.subscribe(() => (cleared = true));
    comp.onClear();

    expect(cleared).toBe(true);
    expect(comp.activeFilterCount()).toBe(0);
  });

  it('powinien renderować nagłówek-toggle z licznikiem aktywnych filtrów', () => {
    const { fixture, comp, el } = create();
    const toggle = el.querySelector('button[aria-controls="filter-bar-body"]');
    expect(toggle).not.toBeNull();
    const badgeSelector = 'button[aria-controls="filter-bar-body"] .rounded-full';

    // brak badge przy 0 aktywnych
    expect(el.querySelector(badgeSelector)).toBeNull();

    comp.onFilterChange('nazwa', 'Gda');
    fixture.detectChanges();
    const badge = el.querySelector(badgeSelector);
    expect(badge).not.toBeNull();
    expect(badge!.textContent!.trim()).toBe('1');
  });

  it('wciśnięcie Enter w polu tekstowym powinno wywołać filterApply', () => {
    const { fixture, comp, el } = create();
    comp.onFilterChange('nazwa', 'Gda');
    fixture.detectChanges();

    let applyCount = 0;
    comp.filterApply.subscribe(() => applyCount++);

    const input = el.querySelector('input[type="text"]') as HTMLInputElement;
    input.value = 'Gda';
    // symulacja ngModelChange + Enter (jak w realnej interakcji)
    comp.onFilterChange('nazwa', 'Gda');
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

    expect(applyCount).toBe(1);
    expect(comp.mobileExpanded()).toBe(false);
  });
});