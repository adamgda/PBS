import { Component, Input, Output, EventEmitter, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { ButtonComponent } from '../button/button.component';
import { SelectComponent } from '../select/select.component';

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
 */
@Component({
  selector: 'app-filter-bar',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonComponent, SelectComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white p-4 rounded-lg shadow mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        @for (filter of filters(); track filter.key) {
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">{{ filter.label }}</label>

            @switch (filter.type) {
              @case ('text') {
                <input
                  type="text"
                  class="px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
                  [placeholder]="filter.placeholder || ''"
                  [ngModel]="filterValues()[filter.key] || ''"
                  (ngModelChange)="onFilterChange(filter.key, $event)"
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
                  class="px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
                  [ngModel]="filterValues()[filter.key] || ''"
                  (ngModelChange)="onFilterChange(filter.key, $event)"
                />
              }
            }
          </div>
        }

        <div class="flex gap-2 ml-auto">
          <app-button [label]="'common.buttons.clear'" variant="secondary" (clicked)="onClear()" />
          <app-button [label]="'common.buttons.filter'" variant="primary" (clicked)="onApply()" />
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

  readonly filters = this._filters.asReadonly();
  readonly filterValues = this._values.asReadonly();

  onFilterChange(key: string, value: string): void {
    const current = this._values();
    const updated = { ...current, [key]: value };
    this._values.set(updated);
    this.filterChange.emit(updated);
  }

  onApply(): void {
    this.filterApply.emit(this._values());
  }

  onClear(): void {
    this._values.set({});
    this.filterClear.emit();
    this.filterApply.emit({});
  }
}