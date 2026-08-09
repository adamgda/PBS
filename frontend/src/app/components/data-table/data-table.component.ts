import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';
import { SelectComponent, SelectOption } from '../select/select.component';

export type SortDirection = 'asc' | 'desc' | null;

export interface DataTableColumn<T> {
  key: string;
  label: string;
  sortable?: boolean;
  width?: string;
  formatter?: (row: T) => string;
  /** Kolumna renderowana jako nagłówek karty w widoku mobilnym (bez etykiety, pogrubiona). */
  isTitle?: boolean;
}

export interface DataTableSortEvent {
  key: string;
  direction: SortDirection;
}

/**
 * Uniwersalna tabela danych z sortowaniem, paginacją i wskaźnikiem ładowania.
 * Obsługa filtrów przez FilterBarComponent (zewnętrzny).
 */
@Component({
  selector: 'app-data-table',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SelectComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white rounded-lg shadow overflow-hidden">
      @if (loading()) {
        <div class="p-8 text-center text-gray-500">
          {{ 'common.table.loading' | translate }}
        </div>
      } @else if (data().length === 0) {
        <div class="p-8 text-center text-gray-500">
          {{ 'common.table.no_data' | translate }}
        </div>
      } @else {
        <!-- Desktop: tabela (przewijana tylko przy zbyt dużej szerokości) -->
        <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 text-gray-700 text-sm">
            <tr>
              @for (col of columns(); track col.key) {
                <th
                  class="px-4 py-3 text-left font-medium"
                  [style.width]="col.width"
                >
                  @if (col.sortable) {
                    <button
                      type="button"
                      class="flex items-center gap-1 hover:text-pbs-primary"
                      (click)="onSort(col.key)"
                    >
                      <span>{{ col.label }}</span>
                      @if (sortKey() === col.key) {
                        <span>{{ sortDirection() === 'asc' ? '▲' : '▼' }}</span>
                      }
                    </button>
                  } @else {
                    <span>{{ col.label }}</span>
                  }
                </th>
              }
              @if (actionsTemplate) {
                <th class="px-4 py-3 text-right font-medium">
                  {{ 'common.table.actions' | translate }}
                </th>
              }
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-800">
            @for (row of data(); track row) {
              <tr class="hover:bg-gray-50 transition-colors">
                @for (col of columns(); track col.key) {
                  <td class="px-4 py-3">
                    @if (cellTemplates[col.key]; as tpl) {
                      <ng-container
                        [ngTemplateOutlet]="tpl"
                        [ngTemplateOutletContext]="{ $implicit: row, value: getCellValue(row, col.key) }"
                      ></ng-container>
                    } @else {
                      {{ col.formatter ? col.formatter(row) : getCellValue(row, col.key) }}
                    }
                  </td>
                }
                @if (actionsTemplate) {
                  <td class="px-4 py-3 text-right">
                    <ng-container [ngTemplateOutlet]="actionsTemplate" [ngTemplateOutletContext]="{ $implicit: row }"></ng-container>
                  </td>
                }
              </tr>
            }
          </tbody>
        </table>
        </div>

        <!-- Mobile: karty (brak przewijania poziomego) -->
        <div class="block md:hidden divide-y divide-gray-100">
          @for (row of data(); track row) {
            <div class="p-4">
              @if (hasTitleColumn()) {
                <!-- Nagłówek karty: tytuł + akcje (akcje jednoznacznie powiązane z rekordem) -->
                <div class="mb-2 flex items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    @for (col of columns(); track col.key) {
                      @if (col.isTitle) {
                        <div class="break-words text-base font-semibold text-gray-900">
                          @if (cellTemplates[col.key]; as tpl) {
                            <ng-container
                              [ngTemplateOutlet]="tpl"
                              [ngTemplateOutletContext]="{ $implicit: row, value: getCellValue(row, col.key) }"
                            ></ng-container>
                          } @else {
                            {{ col.formatter ? col.formatter(row) : getCellValue(row, col.key) }}
                          }
                        </div>
                      }
                    }
                  </div>
                  @if (actionsTemplate) {
                    <div class="flex shrink-0 gap-1">
                      <ng-container
                        [ngTemplateOutlet]="actionsTemplate"
                        [ngTemplateOutletContext]="{ $implicit: row }"
                      ></ng-container>
                    </div>
                  }
                </div>
                <!-- Pozostałe kolumny jako pary klucz-wartość -->
                @for (col of columns(); track col.key) {
                  @if (!col.isTitle) {
                    <div class="flex justify-between gap-3 py-1 text-sm">
                      <span class="shrink-0 font-medium text-gray-500">{{ col.label }}</span>
                      <span class="break-words text-right text-gray-800">
                        @if (cellTemplates[col.key]; as tpl) {
                          <ng-container
                            [ngTemplateOutlet]="tpl"
                            [ngTemplateOutletContext]="{ $implicit: row, value: getCellValue(row, col.key) }"
                          ></ng-container>
                        } @else {
                          {{ col.formatter ? col.formatter(row) : getCellValue(row, col.key) }}
                        }
                      </span>
                    </div>
                  }
                }
              } @else {
                <!-- Brak kolumny tytułowej: wszystkie kolumny jako pary klucz-wartość -->
                @for (col of columns(); track col.key) {
                  <div class="flex justify-between gap-3 py-1 text-sm">
                    <span class="shrink-0 font-medium text-gray-500">{{ col.label }}</span>
                    <span class="break-words text-right text-gray-800">
                      @if (cellTemplates[col.key]; as tpl) {
                        <ng-container
                          [ngTemplateOutlet]="tpl"
                          [ngTemplateOutletContext]="{ $implicit: row, value: getCellValue(row, col.key) }"
                        ></ng-container>
                      } @else {
                        {{ col.formatter ? col.formatter(row) : getCellValue(row, col.key) }}
                      }
                    </span>
                  </div>
                }
                @if (actionsTemplate) {
                  <!-- Jawna etykieta „Akcje" — klarowne powiązanie z rekordem -->
                  <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
                      {{ 'common.table.actions' | translate }}
                    </span>
                    <div class="flex gap-1">
                      <ng-container
                        [ngTemplateOutlet]="actionsTemplate"
                        [ngTemplateOutletContext]="{ $implicit: row }"
                      ></ng-container>
                    </div>
                  </div>
                }
              }
            </div>
          }
        </div>

        <!-- Paginacja -->
        @if (total() > 0) {
          <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-sm text-gray-600">
            <div>
              {{ 'common.table.total' | translate }}: {{ total() }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1 rounded border border-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                [disabled]="page() === 1"
                (click)="onPageChange(page() - 1)"
              >
                ←
              </button>
              <span>{{ page() }} / {{ totalPages() }}</span>
              <button
                type="button"
                class="px-3 py-1 rounded border border-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                [disabled]="page() === totalPages()"
                (click)="onPageChange(page() + 1)"
              >
                →
              </button>
              <app-select
                [options]="perPageOptions"
                size="sm"
                extraClass="ml-2"
                [ngModel]="perPage()"
                (ngModelChange)="onPerPageChange($event)"
              />
            </div>
          </div>
        }
      }
    </div>
  `,
})
export class DataTableComponent<T extends object> {
  @Input({ required: true, alias: 'columns' }) set columnsInput(value: DataTableColumn<T>[]) {
    this._columns.set(value);
  }
  @Input({ required: true, alias: 'data' }) set dataInput(value: T[]) {
    this._data.set(value);
  }
  @Input({ alias: 'total' }) set totalInput(value: number) {
    this._total.set(value);
  }
  @Input({ alias: 'page' }) set pageInput(value: number) {
    this._page.set(value);
  }
  @Input({ alias: 'perPage' }) set perPageInput(value: number) {
    this._perPage.set(value);
  }
  @Input({ alias: 'loading' }) set loadingInput(value: boolean) {
    this._loading.set(value);
  }
  @Input({ alias: 'sortKey' }) set sortKeyInput(value: string) {
    this._sortKey.set(value);
  }
  @Input({ alias: 'sortDirection' }) set sortDirectionInput(value: SortDirection) {
    this._sortDirection.set(value);
  }
  @Input() actionsTemplate: TemplateRef<{ $implicit: T }> | null = null;
  /** Opcjonalne szablony komórek per kolumna (klucz = column.key). Umożliwiają render
   *  komponentów (np. StatusBadgeComponent) wewnątrz komórki zamiast tekstu/formattera. */
  @Input() cellTemplates: Record<string, TemplateRef<{ $implicit: T; value: unknown }>> = {};

  @Output() sortChange = new EventEmitter<DataTableSortEvent>();
  @Output() pageChange = new EventEmitter<number>();
  @Output() perPageChange = new EventEmitter<number>();

  /** Opcje wyboru rozmiaru strony (perPage). */
  readonly perPageOptions: SelectOption[] = [
    { value: 10, label: '10' },
    { value: 25, label: '25' },
    { value: 50, label: '50' },
    { value: 100, label: '100' },
  ];

  private readonly _columns = signal<DataTableColumn<T>[]>([]);
  private readonly _data = signal<T[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _page = signal<number>(1);
  private readonly _perPage = signal<number>(25);
  private readonly _loading = signal<boolean>(false);
  private readonly _sortKey = signal<string>('');
  private readonly _sortDirection = signal<SortDirection>(null);

  readonly columns = this._columns.asReadonly();
  readonly data = this._data.asReadonly();
  readonly total = this._total.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  readonly totalPages = computed(() => Math.ceil(this._total() / this._perPage()) || 1);

  /** Czy któraś kolumna jest oznaczona jako tytuł karty mobilnej (isTitle). */
  readonly hasTitleColumn = computed(() => this._columns().some((c) => c.isTitle === true));

  onSort(key: string): void {
    let direction: SortDirection = 'asc';
    if (this._sortKey() === key) {
      if (this._sortDirection() === 'asc') direction = 'desc';
      else if (this._sortDirection() === 'desc') direction = null;
    }
    this._sortKey.set(key);
    this._sortDirection.set(direction);
    this.sortChange.emit({ key, direction });
  }

  onPageChange(newPage: number): void {
    if (newPage < 1 || newPage > this.totalPages()) return;
    this._page.set(newPage);
    this.pageChange.emit(newPage);
  }

  onPerPageChange(newPerPage: number): void {
    this._perPage.set(newPerPage);
    this._page.set(1);
    this.perPageChange.emit(newPerPage);
  }

  getCellValue(row: T, key: string): unknown {
    return row[key as keyof T];
  }
}