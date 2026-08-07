import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';

export interface AutocompleteOption {
  value: string | number;
  label: string;
  sublabel?: string;
}

/**
 * Select z autocomplete — wymóg dokumentacji: wszystkie listy rozwijane muszą mieć autocomplete.
 * Obsługa wyszukiwania lokalnego lub przez API (emituj query do parenta).
 */
@Component({
  selector: 'app-autocomplete-select',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="relative">
      <input
        type="text"
        class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
        [placeholder]="placeholder()"
        [ngModel]="searchText()"
        (ngModelChange)="onSearch($event)"
        (focus)="onFocus()"
        (blur)="onBlur()"
        autocomplete="off"
        role="combobox"
        aria-expanded="isOpen()"
        aria-autocomplete="list"
      />

      @if (isOpen() && filteredOptions().length > 0) {
        <div
          class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto"
          role="listbox"
        >
          @for (opt of filteredOptions(); track opt.value) {
            <button
              type="button"
              class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 transition-colors flex flex-col"
              role="option"
              [class.bg-blue-50]="opt.value === selectedValue()"
              (mousedown)="$event.preventDefault(); selectOption(opt)"
            >
              <span class="font-medium text-gray-900">{{ opt.label }}</span>
              @if (opt.sublabel) {
                <span class="text-xs text-gray-500">{{ opt.sublabel }}</span>
              }
            </button>
          }
        </div>
      }
    </div>
  `,
})
export class AutocompleteSelectComponent {
  @Input({ required: true }) set options(value: AutocompleteOption[]) {
    this._options.set(value);
  }
  @Input() set selectedValue(value: string | number | null) {
    this._selectedValue.set(value);
    this.updateSearchText();
  }
  @Input() set placeholder(value: string) {
    this._placeholder.set(value);
  }
  @Input() debounceMs = 300;

  @Output() selectionChange = new EventEmitter<AutocompleteOption>();
  @Output() searchChange = new EventEmitter<string>();

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

  onSearch(value: string): void {
    this._searchText.set(value);
    this._isOpen.set(true);
    if (this.debounceTimer) clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(() => {
      this.searchChange.emit(value);
    }, this.debounceMs);
  }

  onFocus(): void {
    this._isOpen.set(true);
  }

  onBlur(): void {
    // Opóźnienie, aby kliknięcie opcji zostało zarejestrowane
    setTimeout(() => this._isOpen.set(false), 200);
  }

  selectOption(opt: AutocompleteOption): void {
    this._selectedValue.set(opt.value);
    this._searchText.set(opt.label);
    this._isOpen.set(false);
    this.selectionChange.emit(opt);
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