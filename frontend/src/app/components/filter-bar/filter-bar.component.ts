import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { ButtonComponent } from '../button/button.component';
import { SelectComponent } from '../select/select.component';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';
import { TranslatePipe } from '../../pipes/translate.pipe';

export interface FilterConfig {
  key: string;
  label: string;
  type: 'text' | 'select' | 'date';
  options?: { value: string; label: string }[];
  placeholder?: string;
}

/**
 * Panel filtrów dla list — komponent współdzielony.
 * Emituje zmiany filtrów do komponentu nadrzędnego.
 *
 * Responsywność: na desktop filtry zawsze rozwinięte (płaski rząd).
 * Na mobile zwijane — nagłówek z ikoną lejka, licznikiem aktywnych filtrów
 * i chevronem; rozwinięcie pokazuje pola i przyciski.
 */
@Component({
  selector: 'app-filter-bar',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonComponent, SelectComponent, SvgIconComponent, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mb-4 rounded-lg bg-white p-4 shadow">
      <!-- Mobile: zwijany nagłówek (desktop: ukryty) -->
      <button
        type="button"
        class="flex w-full items-center justify-between gap-2 text-left md:hidden"
        (click)="toggleMobile()"
        [attr.aria-expanded]="mobileExpanded()"
        [attr.aria-controls]="'filter-bar-body'"
      >
        <span class="flex items-center gap-2">
          <app-svg-icon name="filter" size="sm" />
          <span class="text-sm font-semibold text-gray-700">{{ 'common.buttons.filter' | translate }}</span>
          @if (activeFilterCount() > 0) {
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-pbs-primary px-1.5 text-xs font-semibold text-white">
              {{ activeFilterCount() }}
            </span>
          }
        </span>
        <app-svg-icon
          name="chevron-down"
          size="sm"
          class="transition-transform"
          [class.rotate-180]="mobileExpanded()"
        />
      </button>

      <!-- Ciało filtrów: desktop zawsze widoczne, mobile po rozwinięciu -->
      <div
        id="filter-bar-body"
        class="mt-3 md:mt-0 md:block"
        [class.hidden]="!mobileExpanded()"
      >
        <div class="flex flex-wrap items-end gap-3">
          @for (filter of filters(); track filter.key) {
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-600">{{ filter.label }}</label>

              @switch (filter.type) {
                @case ('text') {
                  <input
                    type="text"
                    class="rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-pbs-primary"
                    [placeholder]="filter.placeholder || ''"
                    [ngModel]="filterValues()[filter.key] || ''"
                    (ngModelChange)="onFilterChange(filter.key, $event)"
                    (keydown.enter)="onApply()"
                  />
                }
                @case ('select') {
                  <app-select
                    [options]="filter.options || []"
                    placeholder="—"
                    [ngModel]="filterValues()[filter.key] || ''"
                    (ngModelChange)="onFilterChange(filter.key, $event)"
                  />
                }
                @case ('date') {
                  <input
                    type="date"
                    class="rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-pbs-primary"
                    [ngModel]="filterValues()[filter.key] || ''"
                    (ngModelChange)="onFilterChange(filter.key, $event)"
                    (keydown.enter)="onApply()"
                  />
                }
              }
            </div>
          }

          <div class="ml-auto flex gap-2">
            <app-button [label]="'common.buttons.clear'" variant="secondary" (clicked)="onClear()" />
            <app-button [label]="'common.buttons.filter'" variant="primary" (clicked)="onApply()" />
          </div>
        </div>
      </div>
    </div>
  `,
})
export class FilterBarComponent {
  @Input({ required: true, alias: 'filters' }) set filtersInput(value: FilterConfig[]) {
    this._filters.set(value);
  }
  @Input({ alias: 'values' }) set valuesInput(value: Record<string, string>) {
    this._values.set(value);
  }

  @Output() filterChange = new EventEmitter<Record<string, string>>();
  @Output() filterApply = new EventEmitter<Record<string, string>>();
  @Output() filterClear = new EventEmitter<void>();

  private readonly _filters = signal<FilterConfig[]>([]);
  private readonly _values = signal<Record<string, string>>({});
  /** Stan rozwinięcia panelu na mobile (desktop zawsze rozwinięty przez CSS). */
  private readonly _mobileExpanded = signal<boolean>(false);

  readonly filters = this._filters.asReadonly();
  readonly filterValues = this._values.asReadonly();
  readonly mobileExpanded = this._mobileExpanded.asReadonly();

  /** Liczba aktywnych (niepustych) filtrów — wskaźnik na nagłówku mobile. */
  readonly activeFilterCount = computed(() => {
    const values = this._values();
    let count = 0;
    for (const v of Object.values(values)) {
      if (typeof v === 'string' && v.trim() !== '') {
        count++;
      }
    }
    return count;
  });

  toggleMobile(): void {
    this._mobileExpanded.update((v) => !v);
  }

  onFilterChange(key: string, value: string): void {
    const current = this._values();
    const updated = { ...current, [key]: value };
    this._values.set(updated);
    this.filterChange.emit(updated);
  }

  onApply(): void {
    this.filterApply.emit(this._values());
    // Po zastosowaniu filtrów chowamy panel na mobile (oszczędność miejsca).
    this._mobileExpanded.set(false);
  }

  onClear(): void {
    this._values.set({});
    this.filterClear.emit();
    this.filterApply.emit({});
  }
}