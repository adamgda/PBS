import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';
import { SelectComponent, SelectOption } from '../select/select.component';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

export type SortDirection = 'asc' | 'desc' | null;

export interface DataTableColumn<T> {
  key: string;
  label: string;
  sortable?: boolean;
  width?: string;
  /** Minimalna szerokość kolumny (np. '120px') — zapobiega zbytniemu ściskaniu. */
  minWidth?: string;
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
  imports: [CommonModule, FormsModule, TranslatePipe, SelectComponent, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-800">
      @if (loading()) {
        <div class="flex flex-col items-center justify-center gap-3 p-10 text-gray-400 dark:text-slate-500">
          <app-svg-icon name="spinner" size="lg" class="animate-spin text-pbs-secondary" />
          <span class="text-sm">{{ 'common.table.loading' | translate }}</span>
        </div>
      } @else if (data().length === 0) {
        <div class="flex flex-col items-center justify-center gap-2 p-10 text-gray-400 dark:text-slate-500">
          <app-svg-icon name="search" size="lg" />
          <span class="text-sm">{{ 'common.table.no_data' | translate }}</span>
        </div>
      } @else {
        <!-- Desktop: tabela (przewijana tylko przy zbyt dużej szerokości) -->
        <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50/90 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              @for (col of columns(); track col.key) {
                <th
                  class="whitespace-nowrap border-b border-gray-200 px-4 py-3 font-medium dark:border-slate-800"
                  [style.width]="col.width"
                  [style.min-width]="col.minWidth"
                >
                  @if (col.sortable) {
                    <button
                      type="button"
                      class="group flex items-center gap-1 uppercase tracking-wider hover:text-pbs-primary dark:hover:text-pbs-secondary"
                      (click)="onSort(col.key)"
                    >
                      <span>{{ col.label }}</span>
                      <span class="text-[10px] opacity-60">
                        {{ sortKey() === col.key ? (sortDirection() === 'asc' ? '▲' : '▼') : '↕' }}
                      </span>
                    </button>
                  } @else {
                    <span>{{ col.label }}</span>
                  }
                </th>
              }
              @if (actionsTemplate) {
                <th class="whitespace-nowrap border-b border-gray-200 px-4 py-3 text-right font-medium dark:border-slate-800">
                  {{ 'common.table.actions' | translate }}
                </th>
              }
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-800 dark:divide-slate-800 dark:text-slate-200">
            @for (row of data(); track row) {
              <tr
                class="transition-colors hover:bg-pbs-navy-50/60 dark:hover:bg-slate-800/60"
                [class.cursor-pointer]="rowClickable"
                (click)="rowClickable && rowClick.emit(row)"
              >
                @for (col of columns(); track col.key) {
                  <td class="px-4 py-3.5" [style.min-width]="col.minWidth">
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
                  <td class="px-4 py-3.5 text-right" (click)="$event.stopPropagation()">
                    <ng-container [ngTemplateOutlet]="actionsTemplate" [ngTemplateOutletContext]="{ $implicit: row }"></ng-container>
                  </td>
                }
              </tr>
            }
          </tbody>
        </table>
        </div>

        <!-- Mobile: karty (brak przewijania poziomego) -->
        <div class="block space-y-3 p-3 md:hidden">
          @for (row of data(); track row) {
            <div
              [class.cursor-pointer]="rowClickable"
              (click)="rowClickable && rowClick.emit(row)"
            >
              @if (hasTitleColumn()) {
                <!-- Nagłówek karty: tytuł + akcje (akcje jednoznacznie powiązane z rekordem) -->
                <div class="mb-2 flex items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    @for (col of columns(); track col.key) {
                      @if (col.isTitle) {
                        <div class="break-words text-base font-semibold text-gray-900 dark:text-white">
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
                    <div class="flex shrink-0 gap-1" (click)="$event.stopPropagation()">
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
                      <span class="shrink-0 font-medium text-gray-500 dark:text-slate-400">{{ col.label }}</span>
                      <span class="break-words text-right text-gray-800 dark:text-slate-200">
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
                    <span class="shrink-0 font-medium text-gray-500 dark:text-slate-400">{{ col.label }}</span>
                    <span class="break-words text-right text-gray-800 dark:text-slate-200">
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
                  <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3 dark:border-slate-800">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-slate-500">
                      {{ 'common.table.actions' | translate }}
                    </span>
                    <div class="flex gap-1" (click)="$event.stopPropagation()">
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
          <div class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50/40 px-4 py-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-slate-500">{{ 'common.table.total' | translate }}</span>
              <span class="font-semibold text-gray-800 dark:text-slate-200">{{ total() }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="mr-1 text-gray-500 dark:text-slate-400">{{ 'common.table.page' | translate }} {{ page() }} {{ 'common.table.of' | translate }} {{ totalPages() }}</span>
              <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                [disabled]="page() === 1"
                (click)="onPageChange(page() - 1)"
                aria-label="Poprzednia strona"
              >
                <app-svg-icon name="chevron-left" size="sm" />
              </button>
              <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                [disabled]="page() === totalPages()"
                (click)="onPageChange(page() + 1)"
                aria-label="Następna strona"
              >
                <app-svg-icon name="chevron-right" size="sm" />
              </button>
              <app-select
                [options]="perPageOptions"
                size="sm"
                extraClass="ml-1"
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
  /** Czy cały wiersz/karta jest klikalny (emisja rowClick). Domyślnie wyłączone. */
  @Input() rowClickable = false;

  @Output() sortChange = new EventEmitter<DataTableSortEvent>();
  @Output() pageChange = new EventEmitter<number>();
  @Output() perPageChange = new EventEmitter<number>();
  /** Emitowany po kliknięciu wiersza (gdy rowClickable === true). */
  @Output() rowClick = new EventEmitter<T>();

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