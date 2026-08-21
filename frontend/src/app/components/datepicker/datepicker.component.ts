import {
  Component,
  Input,
  Output,
  EventEmitter,
  signal,
  computed,
  forwardRef,
  ChangeDetectionStrategy,
  inject,
  ChangeDetectorRef,
  ElementRef,
  ViewChild,
  HostListener,
  OnDestroy,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';
import { TranslatePipe } from '../../pipes/translate.pipe';

let nextUid = 0;

/** Pojedyncza komórka kalendarza. */
interface CalendarCell {
  date: Date;
  day: number;
  inMonth: boolean;
  isToday: boolean;
  isSelected: boolean;
  disabled: boolean;
}

/** Parsuje `YYYY-MM-DD` do lokalnego `Date` (bez przesunięć strefy czasowej). */
function parseISO(value: string): Date {
  const [y, m, d] = value.split('-').map(Number);
  return new Date(y, (m || 1) - 1, d || 1);
}

/** Formatuje lokalny `Date` do `YYYY-MM-DD`. */
function toISO(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/** Czy dwa `Date` są tego samego dnia (lokalnie). */
function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/**
 * Własny, w pełni renderowany w DOM wybór daty (datepicker).
 *
 * Zastępuje natywny `<input type="date">`, którego popup/calendrar przeglądarki
 * nie da się osadzić w modalu i potrafi „wystawać” poza jego obrys (zwłaszcza
 * na urządzeniach mobilnych). Ten komponent rysuje kalendarz w DOM i pozycjonuje
 * go przez `position: fixed`, z automatycznym otwarciem w dół / w górę oraz
 * clampingiem do widocznego obszaru — więc zawsze mieści się na ekranie i w modalu.
 *
 * Zachowuje pełne API `ControlValueAccessor` (współpracuje z `[(ngModel)]`
 * i `formControlName`). Wartość w formacie ISO `YYYY-MM-DD` (zgodna z backendem).
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
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300" [attr.for]="computedId">
          {{ labelKey ? (labelKey | translate) : label }}
        </label>
      }
      <div class="relative mt-1.5">
        @if (icon) {
          <span
            class="pointer-events-none absolute left-3 top-1/2 flex -translate-y-1/2 items-center text-gray-400 dark:text-slate-500"
          >
            <app-svg-icon [name]="icon" size="sm" />
          </span>
        }
        <button
          #triggerEl
          [id]="computedId"
          type="button"
          class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white py-2 text-sm shadow-sm transition-all duration-150 hover:border-gray-300 focus:border-transparent focus:ring-2 focus:ring-pbs-secondary/60 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:hover:border-gray-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-slate-600 dark:focus:ring-pbs-secondary/50 dark:disabled:bg-slate-800 dark:disabled:hover:border-slate-700"
          [class.pl-11]="!!icon"
          [class.pl-3]="!icon"
          [class.pr-9]="canClear()"
          [class.pr-3]="!canClear()"
          [disabled]="disabledState()"
          [attr.aria-haspopup]="'dialog'"
          [attr.aria-expanded]="open()"
          [attr.aria-label]="ariaLabel"
          (click)="toggle()"
        >
          <span
            class="truncate"
            [class.text-gray-400]="!value() && !placeholder"
            [class.dark:text-slate-500]="!value() && !placeholder"
            [class.text-gray-700]="!!value() || !!placeholder"
            [class.dark:text-slate-200]="!!value() || !!placeholder"
          >
            {{ displayValue() }}
          </span>
          <app-svg-icon
            name="chevron-down"
            size="sm"
            class="ml-auto shrink-0 text-gray-400 dark:text-slate-500"
          />
        </button>
        @if (canClear()) {
          <button
            type="button"
            class="absolute right-8 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
            [attr.aria-label]="'common.buttons.clear' | translate"
            [attr.title]="'common.buttons.clear' | translate"
            (mousedown)="$event.preventDefault(); clear()"
          >
            <app-svg-icon name="close" size="sm" />
          </button>
        }
      </div>

      @if (open()) {
        <div
          class="fixed z-[70] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
          [style.top.px]="panelTop()"
          [style.left.px]="panelLeft()"
          [style.width.px]="panelWidth()"
          role="dialog"
          aria-modal="false"
          (mousedown)="$event.preventDefault()"
        >
          <div class="p-3">
            <!-- Nawigacja miesiąca -->
            <div class="mb-2 flex items-center justify-between">
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800"
                (click)="prevMonth()"
                [attr.aria-label]="'Poprzedni miesiąc'"
              >
                <app-svg-icon name="chevron-left" size="sm" />
              </button>
              <div class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ monthLabel() }}</div>
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800"
                (click)="nextMonth()"
                [attr.aria-label]="'Następny miesiąc'"
              >
                <app-svg-icon name="chevron-right" size="sm" />
              </button>
            </div>

            <!-- Nawigacja roku -->
            <div class="mb-2 flex items-center justify-center gap-3 text-sm">
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800"
                (click)="prevYear()"
                [attr.aria-label]="'Poprzedni rok'"
              >‹</button>
              <span class="w-16 text-center font-medium text-gray-800 dark:text-slate-200">{{ viewYear() }}</span>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800"
                (click)="nextYear()"
                [attr.aria-label]="'Następny rok'"
              >›</button>
            </div>

            <!-- Nagłówki dni tygodnia -->
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-400 dark:text-slate-500">
              @for (wd of weekdays(); track $index) {
                <div>{{ wd }}</div>
              }
            </div>

            <!-- Siatka dni -->
            <div class="mt-1 grid grid-cols-7 gap-1">
              @for (c of cells(); track c.date.getTime()) {
                <button
                  type="button"
                  class="grid h-9 w-full place-items-center rounded-md text-sm transition-colors"
                  [class.text-gray-400]="!c.inMonth && !c.isSelected"
                  [class.dark:text-slate-500]="!c.inMonth && !c.isSelected"
                  [class.text-gray-700]="c.inMonth && !c.isSelected && !c.disabled"
                  [class.dark:text-slate-300]="c.inMonth && !c.isSelected && !c.disabled"
                  [class.text-gray-300]="c.disabled"
                  [class.dark:text-slate-600]="c.disabled"
                  [class.font-semibold]="c.isToday && !c.isSelected"
                  [class.text-pbs-primary]="c.isToday && !c.isSelected"
                  [class.dark:text-pbs-secondary]="c.isToday && !c.isSelected"
                  [class.bg-pbs-primary]="c.isSelected"
                  [class.text-white]="c.isSelected"
                  [class.hover:bg-gray-100]="!c.isSelected && !c.disabled"
                  [class.dark:hover:bg-slate-800]="!c.isSelected && !c.disabled"
                  [class.cursor-not-allowed]="c.disabled"
                  [disabled]="c.disabled"
                  (click)="selectDay(c.date)"
                >{{ c.day }}</button>
              }
            </div>

            <!-- Stopka -->
            <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-slate-800">
              <button
                type="button"
                class="text-xs font-medium text-pbs-primary hover:underline dark:text-pbs-secondary"
                (click)="goToday()"
              >Dzisiaj</button>
              <button
                type="button"
                class="text-xs text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300"
                (click)="closeCalendar()"
              >Zamknij</button>
            </div>
          </div>
        </div>
      }
    </div>
  `,
})
export class DatepickerComponent implements ControlValueAccessor, OnDestroy {
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly hostRef = inject(ElementRef<HTMLElement>);

  @ViewChild('triggerEl') triggerEl?: ElementRef<HTMLButtonElement>;

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
  /** Placeholder wyświetlany, gdy pole jest puste. */
  @Input() placeholder = '';
  /** Minimalna data (format ISO `YYYY-MM-DD`). */
  @Input() set min(v: string) {
    this._min.set(v || '');
  }
  get min(): string {
    return this._min();
  }
  /** Maksymalna data (format ISO `YYYY-MM-DD`). */
  @Input() set max(v: string) {
    this._max.set(v || '');
  }
  get max(): string {
    return this._max();
  }
  /** Lokalizacja dla nazw miesięcy/dni (domyślnie `pl-PL`). */
  @Input() set locale(v: string) {
    this._locale.set(v || 'pl-PL');
  }
  get locale(): string {
    return this._locale();
  }

  /** Emitowany (bez wartości) po wyczyszczeniu pola przyciskiem X. */
  @Output() cleared = new EventEmitter<void>();

  readonly value = signal('');
  readonly disabledState = signal(false);
  readonly canClear = signal(false);
  readonly open = signal(false);

  readonly viewYear = signal<number>(new Date().getFullYear());
  readonly viewMonth = signal<number>(new Date().getMonth());

  readonly panelTop = signal(0);
  readonly panelLeft = signal(0);
  readonly panelWidth = signal(280);

  private readonly _min = signal('');
  private readonly _max = signal('');
  private readonly _locale = signal('pl-PL');

  private readonly _id = `app-datepicker-${nextUid++}`;
  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  get computedId(): string {
    return this.inputId || this._id;
  }

  get ariaLabel(): string {
    return (this.labelKey ? this.labelKey : this.label) || 'Wybierz datę';
  }

  /** Wybrana data (lokalny `Date`) na podstawie wartości ISO. */
  readonly selectedDate = computed<Date | null>(() =>
    this.value() ? parseISO(this.value()) : null,
  );

  /** Tekst wyświetlany w polu: sformatowana data lub placeholder. */
  readonly displayValue = computed<string>(() => {
    const v = this.value();
    if (!v) return this.placeholder;
    const d = parseISO(v);
    if (isNaN(d.getTime())) return this.placeholder;
    return new Intl.DateTimeFormat(this._locale(), {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(d);
  });

  /** Nazwa miesiąca + rok, np. „Sierpień 2026”. */
  readonly monthLabel = computed<string>(() => {
    const f = new Intl.DateTimeFormat(this._locale(), { month: 'long' });
    const m = f.format(new Date(this.viewYear(), this.viewMonth(), 1));
    return m.charAt(0).toUpperCase() + m.slice(1);
  });

  /** Krótkie nazwy dni tygodnia (tydzień zaczyna się od poniedziałku). */
  readonly weekdays = computed<string[]>(() => {
    const f = new Intl.DateTimeFormat(this._locale(), { weekday: 'short' });
    const base = new Date(2021, 0, 4); // poniedziałek
    return Array.from({ length: 7 }, (_, i) =>
      f.format(new Date(base.getFullYear(), base.getMonth(), base.getDate() + i)),
    );
  });

  /** Siatka kalendarza (6 tygodni po 7 dni) z metadanymi komórek. */
  readonly cells = computed<CalendarCell[]>(() => {
    const y = this.viewYear();
    const m = this.viewMonth();
    const first = new Date(y, m, 1);
    const offset = (first.getDay() + 6) % 7; // poniedziałek = 0
    const selected = this.selectedDate();
    const today = new Date();
    const minD = this._min() ? parseISO(this._min()) : null;
    const maxD = this._max() ? parseISO(this._max()) : null;

    const result: CalendarCell[] = [];
    for (let i = 0; i < 42; i++) {
      const d = new Date(y, m, 1 - offset + i);
      const disabled = !!((minD && d < minD) || (maxD && d > maxD));
      result.push({
        date: d,
        day: d.getDate(),
        inMonth: d.getMonth() === m,
        isToday: sameDay(d, today),
        isSelected: !!selected && sameDay(d, selected),
        disabled,
      });
    }
    return result;
  });

  constructor() {
    // Domyślny rok/miesiąc widoku = aktualny.
    const now = new Date();
    this.viewYear.set(now.getFullYear());
    this.viewMonth.set(now.getMonth());
  }

  // --- Sterowanie kalendarzem ---

  toggle(): void {
    if (this.disabledState()) return;
    if (this.open()) {
      this.closeCalendar();
    } else {
      this.openCalendar();
    }
  }

  openCalendar(): void {
    if (this.disabledState()) return;
    const sel = this.selectedDate();
    const base = sel ?? new Date();
    this.viewYear.set(base.getFullYear());
    this.viewMonth.set(base.getMonth());
    this.open.set(true);
    this.cdr.markForCheck();
    requestAnimationFrame(() => this.reposition());
    window.addEventListener('scroll', this.onViewportChange, true);
    window.addEventListener('resize', this.onViewportChange);
  }

  closeCalendar(): void {
    this.open.set(false);
    this.cdr.markForCheck();
    window.removeEventListener('scroll', this.onViewportChange, true);
    window.removeEventListener('resize', this.onViewportChange);
  }

  /** Pozycjonuje panel przez `position: fixed`, w dół lub w górę, z clampingiem do viewportu. */
  private reposition(): void {
    const el = this.triggerEl?.nativeElement;
    if (!el || !this.open()) return;
    const r = el.getBoundingClientRect();

    const panelH = 360;
    const spaceBelow = window.innerHeight - r.bottom;
    const spaceAbove = r.top;
    const openUp = spaceBelow < panelH + 8 && spaceAbove > spaceBelow;

    const top = openUp ? Math.max(8, r.top - panelH - 8) : r.bottom + 4;
    const width = Math.min(Math.max(r.width, 280), window.innerWidth - 16);
    let left = r.left;
    if (left + width > window.innerWidth - 8) left = window.innerWidth - width - 8;
    if (left < 8) left = 8;

    this.panelTop.set(Math.max(8, top));
    this.panelLeft.set(left);
    this.panelWidth.set(width);
    this.cdr.markForCheck();
  }

  private readonly onViewportChange = (): void => this.reposition();

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    if (this.open() && !this.hostRef.nativeElement.contains(event.target as Node)) {
      this.closeCalendar();
    }
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.open()) this.closeCalendar();
  }

  // --- Nawigacja ---

  prevMonth(): void {
    if (this.viewMonth() === 0) {
      this.viewYear.update((y) => y - 1);
      this.viewMonth.set(11);
    } else {
      this.viewMonth.update((m) => m - 1);
    }
  }

  nextMonth(): void {
    if (this.viewMonth() === 11) {
      this.viewYear.update((y) => y + 1);
      this.viewMonth.set(0);
    } else {
      this.viewMonth.update((m) => m + 1);
    }
  }

  prevYear(): void {
    this.viewYear.update((y) => y - 1);
  }

  nextYear(): void {
    this.viewYear.update((y) => y + 1);
  }

  goToday(): void {
    const today = new Date();
    this.viewYear.set(today.getFullYear());
    this.viewMonth.set(today.getMonth());
    const minD = this._min() ? parseISO(this._min()) : null;
    const maxD = this._max() ? parseISO(this._max()) : null;
    if ((minD && today < minD) || (maxD && today > maxD)) return;
    this.selectDay(today);
  }

  // --- Wybór ---

  selectDay(date: Date): void {
    if (this.disabledState()) return;
    const iso = toISO(date);
    this.value.set(iso);
    this.canClear.set(true);
    this.onChange(iso);
    this.onTouched();
    this.closeCalendar();
  }

  clear(): void {
    this.value.set('');
    this.canClear.set(false);
    this.onChange('');
    this.cleared.emit();
    this.closeCalendar();
  }

  // --- ControlValueAccessor ---

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
    if (isDisabled) this.closeCalendar();
    this.cdr.markForCheck();
  }

  ngOnDestroy(): void {
    window.removeEventListener('scroll', this.onViewportChange, true);
    window.removeEventListener('resize', this.onViewportChange);
  }
}
