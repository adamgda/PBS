import {
  Component,
  Input,
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
 * Współdzielone pole formularza — podstawa biblioteki elementów UI formularzy.
 *
 * Reużywalny `<input>` z opcjonalną ikoną wiodącą (z `SvgIconComponent`),
 * etykietą i placeholderem (przez klucze tłumaczeń lub wartości bezpośrednie)
 * oraz opcjonalnym przełącznikiem widoczności hasła.
 *
 * Implementuje `ControlValueAccessor`, dzięki czemu współpracuje zarówno
 * z formami szablonowymi (`[(ngModel)]` / `[ngModel]` + `(ngModelChange)`),
 * jak i reaktywnymi (`formControlName`). DRY dla pól typu email/hasło itp.
 *
 * @example
 * ```html
 * <app-form-input
 *   type="email"
 *   name="email"
 *   icon="mail"
 *   autocomplete="email"
 *   labelKey="common.auth.email"
 *   placeholderKey="common.auth.email"
 *   [ngModel]="email()"
 *   (ngModelChange)="email.set($event)"
 * />
 * ```
 */
@Component({
  selector: 'app-form-input',
  standalone: true,
  imports: [CommonModule, SvgIconComponent, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => FormInputComponent),
      multi: true,
    },
  ],
  template: `
    <div class="mb-3">
      @if (labelKey || label) {
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300" [attr.for]="computedId">
          {{ labelKey ? (labelKey | translate) : label }}
        </label>
      }
      <div class="relative mt-1.5">
        @if (icon) {
          <span
            class="pointer-events-none absolute left-3 top-1/2 flex -translate-y-1/2 items-center text-gray-400 dark:text-slate-500"
          >
            <app-svg-icon [name]="icon" />
          </span>
        }
        <input
          [id]="computedId"
          [type]="effectiveType()"
          [attr.name]="name || null"
          [attr.autocomplete]="autocomplete || null"
          [required]="required"
          [disabled]="disabledState()"
          [value]="value()"
          [placeholder]="placeholderKey ? (placeholderKey | translate) : placeholder"
          class="block w-full rounded-lg border border-gray-200 bg-white py-2 text-sm shadow-sm transition-all duration-150 placeholder:text-gray-400 hover:border-gray-300 focus:border-transparent focus:ring-2 focus:ring-pbs-secondary/60 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:hover:border-gray-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:border-slate-600 dark:focus:ring-pbs-secondary/50 dark:disabled:bg-slate-800 dark:disabled:hover:border-slate-700"
          [class.pl-11]="!!icon"
          [class.pl-3]="!icon"
          [class.pr-11]="passwordToggle"
          [class.pr-3]="!passwordToggle"
          (input)="onInput($event)"
          (blur)="onBlur()"
        />
        @if (passwordToggle) {
          <button
            type="button"
            (click)="togglePassword()"
            class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
            [attr.aria-label]="(showPassword() ? hidePasswordLabelKey : showPasswordLabelKey) | translate"
            [attr.aria-pressed]="showPassword()"
          >
            <app-svg-icon [name]="showPassword() ? 'eye-off' : 'eye'" />
          </button>
        }
      </div>
    </div>
  `,
})
export class FormInputComponent implements ControlValueAccessor {
  private readonly cdr = inject(ChangeDetectorRef);

  /** Klucz tłumaczenia etykiety (zalecane — zgodnie z 6.1.1). */
  @Input() labelKey = '';
  /** Etykieta bezpośrednia (gdy nie używamy tłumaczeń). */
  @Input() label = '';
  /** Typ pola. Dla `password` + `passwordToggle` działa przełącznik widoczności. */
  @Input() type: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url' | 'search' = 'text';
  /** Atrybut `name` pola. */
  @Input() name = '';
  /** Klucz tłumaczenia placeholdera (zalecane). */
  @Input() placeholderKey = '';
  /** Placeholder bezpośredni. */
  @Input() placeholder = '';
  /** Nazwa ikony wiodącej z `SvgIconComponent` (np. `mail`, `lock`). Puste = brak ikony. */
  @Input() icon = '';
  /** Atrybut `autocomplete`. */
  @Input() autocomplete = '';
  /** Czy pole wymagane. */
  @Input() required = false;
  /** Jawne id pola (gdy puste — generowane automatycznie). */
  @Input() inputId = '';
  /** Włącza przycisk przełącznika widoczności hasła (typ `password`). */
  @Input() passwordToggle = false;
  /** Klucz tłumaczenia etykiety „pokaż hasło" dla a11y. */
  @Input() showPasswordLabelKey = 'common.auth.show_password';
  /** Klucz tłumaczenia etykiety „ukryj hasło" dla a11y. */
  @Input() hidePasswordLabelKey = 'common.auth.hide_password';

  readonly value = signal('');
  readonly showPassword = signal(false);
  readonly disabledState = signal(false);

  private readonly _id = `app-form-input-${nextUid++}`;
  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  get computedId(): string {
    return this.inputId || this._id;
  }

  effectiveType(): string {
    if (this.type === 'password' && this.showPassword()) {
      return 'text';
    }
    return this.type;
  }

  writeValue(value: unknown): void {
    this.value.set(value == null ? '' : String(value));
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
    this.cdr.markForCheck();
  }

  onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const v = target.value;
    this.value.set(v);
    this.onChange(v);
  }

  onBlur(): void {
    this.onTouched();
  }

  togglePassword(): void {
    this.showPassword.set(!this.showPassword());
  }
}