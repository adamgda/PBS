import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy, forwardRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

export interface SelectOption {
  /** Wartość opcji — string lub number (zgodna z ngModel). */
  value: string | number;
  /** Surowa etykieta opcji (wyświetlana bez tłumaczenia). */
  label?: string;
  /** Klucz tłumaczeniowy etykiety opcji (jeśli podany, etykieta renderowana przez translate). */
  labelKey?: string;
}

export type SelectSize = 'sm' | 'md' | 'lg';

/**
 * Współdzielony select (`<select>` + opcje) z własną strzałką chevron.
 *
 * Implementuje `ControlValueAccessor`, więc współpracuje z `[(ngModel)]`, `[ngModel]` + `(ngModelChange)`
 * oraz `[value]` + `(valueChange)` — drop-in zamiast natywnego `<select>`.
 *
 * Natywne chrome selecta (strzałka OS/przeglądarki) jest ukryte (`appearance: none`); zamiast tego
 * renderowana jest ikona `chevron-down` z kontrolowanym odstępem od prawej krawędzi — dzięki temu
 * strzałka nie „przykleja się" do krawędzi na różnych platformach.
 *
 * Warianty rozmiaru (`size`): `sm` | `md` | `lg`. Opcje przez `options` (`{ value, label }` LUB
 * `{ value, labelKey }`). Opcjonalny placeholder (pusta opcja) przez `placeholder` / `placeholderKey`.
 *
 * Użycie:
 *   <app-select [options]="roles" size="lg" [ngModel]="modalRole()" (ngModelChange)="modalRole.set($event)" />
 *   <app-select [options]="filter.options || []" placeholder="—" [ngModel]="values()[k] || ''" (ngModelChange)="change(k, $event)" />
 */
@Component({
  selector: 'app-select',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => SelectComponent),
      multi: true,
    },
  ],
  template: `
    <div [class]="wrapperClass">
      <select
        [class]="classes"
        [disabled]="disabled"
        [ngModel]="value"
        (ngModelChange)="onChange($event)"
        (blur)="onTouched()"
      >
        @if (hasPlaceholder) {
          <option value="">{{ placeholderKey ? (placeholderKey | translate) : placeholder }}</option>
        }
        @for (opt of options; track opt.value) {
          <option [value]="opt.value">{{ opt.labelKey ? (opt.labelKey | translate) : opt.label }}</option>
        }
      </select>
      <app-svg-icon
        name="chevron-down"
        size="sm"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
        [class.opacity-40]="disabled"
      />
    </div>
  `,
  styles: [
    `
      select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
      }
    `,
  ],
})
export class SelectComponent implements ControlValueAccessor {
  /** Lista opcji. */
  @Input() options: SelectOption[] = [];
  /** Rozmiar/styl selecta. */
  @Input() size: SelectSize = 'md';
  /** Tekst pustej opcji (placeholder). */
  @Input() placeholder = '';
  /** Klucz tłumaczeniowy pustej opcji (alternatywa dla `placeholder`). */
  @Input() placeholderKey = '';
  /** Blokada selecta. */
  @Input() disabled = false;
  /** Dodatkowe klasy Tailwind (np. marginesy 'ml-2') na wrapperze — escape hatch. */
  @Input() extraClass = '';
  /** Emitowany przy zmianie wartości (alternatywa dla ngModelChange). */
  @Output() valueChange = new EventEmitter<string | number>();

  /** Bieżąca wartość (sterowana przez CVA / ngModel). */
  value: string | number | null = null;

  private _onChange: (v: string | number) => void = () => {};
  onTouched: () => void = () => {};

  get hasPlaceholder(): boolean {
    return !!this.placeholder || !!this.placeholderKey;
  }

  /** Klasy dla elementu <select> (bez extraClass — ten trafia na wrapper). */
  get classes(): string {
    switch (this.size) {
      case 'sm':
        return 'pl-2 pr-8 py-1 rounded border border-gray-200 text-sm';
      case 'lg':
        return 'block w-full rounded-lg border border-gray-200 bg-white pl-3 pr-9 py-2.5 text-sm shadow-sm focus:border-transparent focus:ring-2 focus:ring-pbs-primary';
      default:
        return 'pl-3 pr-8 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent';
    }
  }

  /** Klasy dla wrappera (pozycjonowanie chevronu + extraClass). */
  get wrapperClass(): string {
    const display = this.size === 'lg' ? 'block w-full' : 'inline-flex';
    return `relative ${display} ${this.extraClass}`.trim();
  }

  onChange(v: string | number): void {
    this.value = v;
    this._onChange(v);
    this.valueChange.emit(v);
  }

  // --- ControlValueAccessor ---

  writeValue(v: string | number | null): void {
    this.value = v;
  }

  registerOnChange(fn: (v: string | number) => void): void {
    this._onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
  }
}