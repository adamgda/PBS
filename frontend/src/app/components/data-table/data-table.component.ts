import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TranslatePipe } from '../../pipes/translate.pipe';

export type SortDirection = 'asc' | 'desc' | null;

export interface DataTableColumn<T> {
  key: keyof T | string;
  label: string;
  sortable?: boolean;
  width?: string;
  formatter?: (row: T) => string;
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
  imports: [CommonModule, FormsModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="overflow-x-auto bg-white rounded-lg shadow">
      @if (loading()) {
        <div class="p-8 text-center text-gray-500">
          {{ 'common.table.loading' | translate }}
        </div>
      } @else if (data().length === 0) {
        <div class="p-8 text-center text-gray-500">
          {{ 'common.table.no_data' | translate }}
        </div>
      } @else {
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
                    {{ col.formatter ? col.formatter(row) : getCellValue(row, col.key) }}
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
              <select
                class="ml-2 px-2 py-1 rounded border border-gray-200 text-sm"
                [ngModel]="perPage()"
                (ngModelChange)="onPerPageChange($event)"
              >
                <option [value]="10">10</option>
                <option [value]="25">25</option>
                <option [value]="50">50</option>
                <option [value]="100">100</option>
              </select>
            </div>
          </div>
        }
      }
    </div>
  `,
})
export class DataTableComponent<T extends Record<string, unknown>> {
  @Input({ required: true }) set columns(value: DataTableColumn<T>[]) {
    this._columns.set(value);
  }
  @Input({ required: true }) set data(value: T[]) {
    this._data.set(value);
  }
  @Input() set total(value: number) {
    this._total.set(value);
  }
  @Input() set page(value: number) {
    this._page.set(value);
  }
  @Input() set perPage(value: number) {
    this._perPage.set(value);
  }
  @Input() set loading(value: boolean) {
    this._loading.set(value);
  }
  @Input() set sortKey(value: string) {
    this._sortKey.set(value);
  }
  @Input() set sortDirection(value: SortDirection) {
    this._sortDirection.set(value);
  }
  @Input() actionsTemplate: unknown = null;

  @Output() sortChange = new EventEmitter<DataTableSortEvent>();
  @Output() pageChange = new EventEmitter<number>();
  @Output() perPageChange = new EventEmitter<number>();

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