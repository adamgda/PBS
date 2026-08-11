import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy, inject, ViewChild, ElementRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

export interface AutocompleteOption {
  value: string | number;
  label: string;
  sublabel?: string;
}

/**
 * Select z autocomplete — wymóg dokumentacji: wszystkie listy rozwijane muszą mieć autocomplete.
 * Obsługa wyszukiwania lokalnego lub przez API (emituj query do parenta).
 *
 * Posiada przycisk kasowania (X) pokazywany gdy wybrano wartość lub wpisano tekst —
 * emituje `selectionChange(null)` oraz `cleared`, by parent mógł wyczyścić selekcję.
 *
 * Panel rozwijany jest renderowany z `position: fixed` i współrzędnymi wyliczanymi
 * z `getBoundingClientRect()` pola wejściowego (pętla rAF w trakcie otwarcia), dzięki
 * czemu unika obcinania przez `overflow` przodków (np. modale z `overflow-y-auto`).
 * Brak transformed-ancestora w layoutzie PBS → `fixed` pozycjonuje względem viewportu.
 */
@Component({
  selector: 'app-autocomplete-select',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [':host { display: block; }'],
  template: `
    <div class="relative">
      <input
        #inputEl
        type="text"
        class="w-full px-3 py-2 pr-9 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
        [placeholder]="placeholder()"
        [ngModel]="searchText()"
        (ngModelChange)="onSearch($event)"
        (focus)="onFocus()"
        (blur)="onBlur()"
        autocomplete="off"
        role="combobox"
        [attr.aria-expanded]="isOpen()"
        aria-autocomplete="list"
      />

      @if (canClear()) {
        <button
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 grid h-6 w-6 place-items-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
          [attr.aria-label]="'common.buttons.clear' | translate"
          [attr.title]="'common.buttons.clear' | translate"
          (mousedown)="$event.preventDefault(); clear()"
        >
          <app-svg-icon name="close" size="sm" />
        </button>
      }

      @if (isOpen() && filteredOptions().length > 0) {
        <div
          class="fixed z-[70] mt-1 bg-white border border-gray-200 rounded-md shadow-lg overflow-auto dark:bg-slate-900 dark:border-slate-700"
          [style.top.px]="panelTop()"
          [style.left.px]="panelLeft()"
          [style.width.px]="panelWidth()"
          [style.maxHeight.px]="panelMaxHeight()"
          role="listbox"
        >
          @for (opt of filteredOptions(); track opt.value) {
            <button
              type="button"
              class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 transition-colors flex flex-col dark:hover:bg-slate-800"
              role="option"
              [class.bg-blue-50]="opt.value === selectedValue()"
              [class.dark:bg-blue-500]="opt.value === selectedValue()"
              (mousedown)="$event.preventDefault(); selectOption(opt)"
            >
              <span class="font-medium text-gray-900 dark:text-slate-200">{{ opt.label }}</span>
              @if (opt.sublabel) {
                <span class="text-xs text-gray-500 dark:text-slate-400">{{ opt.sublabel }}</span>
              }
            </button>
          }
        </div>
      }
    </div>
  `,
})
export class AutocompleteSelectComponent implements OnDestroy {
  @Input('options') set optionsInput(value: AutocompleteOption[]) {
    this._options.set(value);
  }
  @Input('selectedValue') set selectedValueInput(value: string | number | null) {
    this._selectedValue.set(value);
    this.updateSearchText();
  }
  @Input('placeholder') set placeholderInput(value: string) {
    this._placeholder.set(value);
  }
  @Input() debounceMs = 300;

  /**
   * Emitowany przy wyborze opcji (z wartością) lub przy wyczyszczeniu (z `null`).
   * Parent powinien obsługiwać `null` jako brak selekcji.
   */
  @Output() selectionChange = new EventEmitter<AutocompleteOption | null>();
  @Output() searchChange = new EventEmitter<string>();
  /** Emitowany (bez wartości) po wyczyszczeniu selekcji przyciskiem X. */
  @Output() cleared = new EventEmitter<void>();

  private readonly _options = signal<AutocompleteOption[]>([]);
  private readonly _selectedValue = signal<string | number | null>(null);
  private readonly _placeholder = signal<string>('');
  private readonly _searchText = signal<string>('');
  private readonly _isOpen = signal<boolean>(false);

  readonly options = this._options.asReadonly();
  readonly selectedValue = this._selectedValue.asReadonly();
  readonly placeholder = this._placeholder.asReadonly();
  readonly searchText = this._searchText.asReadonly();
  readonly isOpen = this._isOpen.asReadonly();

  /** Czy pokazać przycisk kasowania — gdy wybrano wartość lub wpisano tekst. */
  readonly canClear = computed(() => this._selectedValue() !== null || this._searchText() !== '');

  readonly filteredOptions = computed(() => {
    const query = this._searchText().toLowerCase();
    if (!query) return this._options();
    return this._options().filter(
      (opt) =>
        opt.label.toLowerCase().includes(query) ||
        (opt.sublabel?.toLowerCase().includes(query) ?? false),
    );
  });

  private debounceTimer: ReturnType<typeof setTimeout> | null = null;

  /** Referencja do pola wejściowego — źródło współrzędnych panelu. */
  @ViewChild('inputEl') inputEl?: ElementRef<HTMLInputElement>;

  /** Współrzędne panelu (px) wyliczane z pola wejściowego. */
  private readonly _panelTop = signal<number>(0);
  private readonly _panelLeft = signal<number>(0);
  private readonly _panelWidth = signal<number>(0);
  private readonly _panelMaxHeight = signal<number>(240);
  readonly panelTop = this._panelTop.asReadonly();
  readonly panelLeft = this._panelLeft.asReadonly();
  readonly panelWidth = this._panelWidth.asReadonly();
  readonly panelMaxHeight = this._panelMaxHeight.asReadonly();

  /** Identyfikator pętli rAF śledzącej pozycję pola (scroll/resize/modal-scroll). */
  private rafId: number | null = null;

  ngOnDestroy(): void {
    this.stopTracking();
    if (this.debounceTimer) clearTimeout(this.debounceTimer);
  }

  onSearch(value: string): void {
    this._searchText.set(value);
    // Wpisywanie tekstu odznacza bieżącą selekcję (wartość nie pasuje już do etykiety).
    this._selectedValue.set(null);
    this._isOpen.set(true);
    this.startTracking();
    if (this.debounceTimer) clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(() => {
      this.searchChange.emit(value);
    }, this.debounceMs);
  }

  onFocus(): void {
    this._isOpen.set(true);
    this.startTracking();
  }

  onBlur(): void {
    // Opóźnienie, aby kliknięcie opcji zostało zarejestrowane
    setTimeout(() => {
      this._isOpen.set(false);
      this.stopTracking();
    }, 200);
  }

  selectOption(opt: AutocompleteOption): void {
    this._selectedValue.set(opt.value);
    this._searchText.set(opt.label);
    this._isOpen.set(false);
    this.stopTracking();
    this.selectionChange.emit(opt);
  }

  clear(): void {
    if (this.debounceTimer) clearTimeout(this.debounceTimer);
    this._selectedValue.set(null);
    this._searchText.set('');
    this._isOpen.set(false);
    this.stopTracking();
    this.selectionChange.emit(null);
    this.cleared.emit();
  }

  /** Uruchamia pętlę rAF odświeżającą współrzędne panelu dopóki jest otwarty. */
  private startTracking(): void {
    if (this.rafId !== null) return;
    const tick = (): void => {
      this.updatePanelPosition();
      this.rafId = requestAnimationFrame(tick);
    };
    this.rafId = requestAnimationFrame(tick);
  }

  private stopTracking(): void {
    if (this.rafId !== null) {
      cancelAnimationFrame(this.rafId);
      this.rafId = null;
    }
  }

  /**
   * Wylicza pozycję panelu względem viewportu z `getBoundingClientRect()` pola.
   * Jeśli poniżej pola brakuje miejsca, panel jest otwierany nad polem.
   */
  private updatePanelPosition(): void {
    const el = this.inputEl?.nativeElement;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const gap = 4;
    const maxPanel = 240;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const showAbove = spaceBelow < 100 && rect.top > 100;
    this._panelLeft.set(Math.max(8, rect.left));
    this._panelWidth.set(rect.width);
    if (showAbove) {
      this._panelTop.set(Math.max(8, rect.top - gap - Math.min(maxPanel, rect.top - gap)));
      this._panelMaxHeight.set(Math.min(maxPanel, rect.top - gap));
    } else {
      this._panelTop.set(rect.bottom + gap);
      this._panelMaxHeight.set(Math.min(maxPanel, Math.max(80, spaceBelow)));
    }
  }

  private updateSearchText(): void {
    const value = this._selectedValue();
    if (value === null) {
      this._searchText.set('');
      return;
    }
    const opt = this._options().find((o) => o.value === value);
    this._searchText.set(opt?.label ?? '');
  }
}