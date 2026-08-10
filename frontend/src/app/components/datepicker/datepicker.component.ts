import {
  Component,
  Input,
  Output,
  EventEmitter,
  signal,
  forwardRef,
  ChangeDetectionStrategy,
  inject,
  ChangeDetectorRef,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';
import { TranslatePipe } from '../../pipes/translate.pipe';

let nextUid = 0;

/**
 * Współdzielony wybór daty (datepicker) — opakowuje natywny `<input type="date">`
 * w spójną z resztą biblioteki szatę graficzną (ikona kalendarza wiodąca,
 * etykieta z tłumaczeń, przycisk kasowania X), implementując `ControlValueAccessor`.
 *
 * Wartość w formacie ISO `YYYY-MM-DD` (zgodna z backendem PHP / `DATE` w MySQL).
 *
 * Mobile-first: na urządzeniach dotykowych uruchamia natywny wheel/picker
 * przeglądarki, bez własnej implementacji kalendarza (mniejszy bundle, lepsza
 * ergonomia na telefonie). Na desktop pole działa jako tekstowa kontrolka daty.
 *
 * Implementuje `ControlValueAccessor` — współpracuje z formami szablonowymi
 * (`[(ngModel)]`) i reaktywnymi (`formControlName`).
 *
 * @example
 * ```html
 * <app-datepicker
 *   name="data_waznosci"
 *   labelKey="pracownicy.certificates.expiry_date"
 *   [ngModel]="docExpiry()"
 *   (ngModelChange)="docExpiry.set($event)"
 * />
 * ```
 */
@Component({
  selector: 'app-datepicker',
  standalone: true,
  imports: [CommonModule, SvgIconComponent, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => DatepickerComponent),
      multi: true,
    },
  ],
  template: `
    <div class="mb-3">
      @if (labelKey || label) {
        <label class="block text-sm font-medium text-gray-700" [attr.for]="computedId">
          {{ labelKey ? (labelKey | translate) : label }}
        </label>
      }
      <div class="relative mt-1.5">
        @if (icon) {
          <span
            class="pointer-events-none absolute left-3 top-1/2 flex -translate-y-1/2 items-center text-gray-400"
          >
            <app-svg-icon [name]="icon" />
          </span>
        }
        <input
          [id]="computedId"
          type="date"
          [attr.name]="name || null"
          [required]="required"
          [disabled]="disabledState()"
          [value]="value()"
          [min]="min || null"
          [max]="max || null"
          class="block w-full rounded-lg border border-gray-200 bg-white py-2.5 shadow-sm transition-all duration-150 hover:border-gray-300 focus:border-transparent focus:ring-2 focus:ring-pbs-secondary/60 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:hover:border-gray-200"
          [class.pl-11]="!!icon"
          [class.pl-3]="!icon"
          [class.pr-9]="canClear()"
          [class.pr-3]="!canClear()"
          (input)="onInput($event)"
          (blur)="onBlur()"
        />
        @if (canClear()) {
          <button
            type="button"
            class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            [attr.aria-label]="'common.buttons.clear' | translate"
            [attr.title]="'common.buttons.clear' | translate"
            (mousedown)="$event.preventDefault(); clear()"
          >
            <app-svg-icon name="close" size="sm" />
          </button>
        }
      </div>
    </div>
  `,
})
export class DatepickerComponent implements ControlValueAccessor {
  private readonly cdr = inject(ChangeDetectorRef);

  /** Klucz tłumaczenia etykiety (zalecane — zgodnie z konwencją 6.1.1). */
  @Input() labelKey = '';
  /** Etykieta bezpośrednia (gdy nie używamy tłumaczeń). */
  @Input() label = '';
  /** Atrybut `name` pola. */
  @Input() name = '';
  /** Nazwa ikony wiodącej z `SvgIconComponent`. Domyślnie `calendar`. Puste = brak ikony. */
  @Input() icon = 'calendar';
  /** Czy pole wymagane. */
  @Input() required = false;
  /** Jawne id pola (gdy puste — generowane automatycznie). */
  @Input() inputId = '';
  /** Minimalna data (format ISO `YYYY-MM-DD`). */
  @Input() min = '';
  /** Maksymalna data (format ISO `YYYY-MM-DD`). */
  @Input() max = '';

  /** Emitowany (bez wartości) po wyczyszczeniu pola przyciskiem X. */
  @Output() cleared = new EventEmitter<void>();

  readonly value = signal('');
  readonly disabledState = signal(false);

  /** Czy pokazać przycisk kasowania — gdy pole ma wartość i nie jest zablokowane. */
  readonly canClear = signal(false);

  private readonly _id = `app-datepicker-${nextUid++}`;
  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  get computedId(): string {
    return this.inputId || this._id;
  }

  writeValue(value: unknown): void {
    const v = value == null ? '' : String(value);
    this.value.set(v);
    this.canClear.set(v !== '' && !this.disabledState());
    this.cdr.markForCheck();
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabledState.set(isDisabled);
    this.canClear.set(this.value() !== '' && !isDisabled);
    this.cdr.markForCheck();
  }

  onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const v = target.value;
    this.value.set(v);
    this.canClear.set(v !== '' && !this.disabledState());
    this.onChange(v);
  }

  onBlur(): void {
    this.onTouched();
  }

  clear(): void {
    this.value.set('');
    this.canClear.set(false);
    this.onChange('');
    this.cleared.emit();
  }
}