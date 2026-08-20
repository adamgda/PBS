import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { IncidentsService } from '../../services/incidents.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { StatusBadgeComponent } from '../../components/status-badge/status-badge.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import { Incident, IncidentStatus, IncidentListParams } from '../../models/incidents.model';

/**
 * Sekcja Awaria (Etap 10).
 * Lista awarii (filtrowanie typ/status/źródło) z przejściem do podstron:
 * /incidents/new (zgłoszenie) oraz /incidents/:id (szczegóły).
 */
@Component({
  selector: 'app-incidents',
  standalone: true,
  imports: [
    CommonModule,
    TranslatePipe,
    SvgIconComponent,
    AddButtonComponent,
    StatusBadgeComponent,
    FilterBarComponent,
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './incidents.component.html',
})
export class IncidentsComponent {
  private readonly incidentsService = inject(IncidentsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly router = inject(Router);

  private readonly _incidents = signal<Incident[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _loading = signal<boolean>(false);
  readonly _page = signal<number>(1);
  readonly _perPage = signal<number>(25);
  readonly _sortKey = signal<string>('id');
  readonly _sortDirection = signal<SortDirection>('asc');
  readonly _filters = signal<Record<string, string>>({});

  readonly incidents = this._incidents.asReadonly();
  readonly total = this._total.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  private readonly typeOptions = [
    { value: 'sprzet', labelKey: 'awaria.list.type_equipment' },
    { value: 'inne', labelKey: 'awaria.list.type_other' },
  ];

  private readonly statusOptions = [
    { value: 'zgloszona', labelKey: 'awaria.status.zgloszona' },
    { value: 'w_trakcie_naprawy', labelKey: 'awaria.status.w_trakcie_naprawy' },
    { value: 'naprawiona', labelKey: 'awaria.status.naprawiona' },
    { value: 'zamknieta', labelKey: 'awaria.status.zamknieta' },
  ];

  private readonly zrodloOptions = [
    { value: 'panel', labelKey: 'awaria.source.panel' },
    { value: 'qr', labelKey: 'awaria.source.qr' },
  ];

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'typ', label: this.t('awaria.filters.type'), type: 'select', options: this.typeOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
    { key: 'status', label: this.t('awaria.filters.status'), type: 'select', options: this.statusOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
    { key: 'zrodlo', label: this.t('awaria.filters.source'), type: 'select', options: this.zrodloOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
  ]);

  readonly columns = computed<DataTableColumn<Incident>[]>(() => [
    { key: 'typ', label: this.t('awaria.list.type'), sortable: true, isTitle: true },
    { key: 'opis', label: this.t('awaria.list.description') },
    { key: 'equipment_nazwa', label: this.t('awaria.list.equipment') },
    { key: 'status', label: this.t('awaria.list.status'), sortable: true },
    { key: 'zrodlo', label: this.t('awaria.list.source') },
    { key: 'data_zgloszenia', label: this.t('awaria.list.reported_date'), sortable: true },
    { key: 'data_zakonczenia', label: this.t('awaria.list.closed_date') },
  ]);

  constructor() {
    this.load();
  }

  // --- Lista ---

  load(): void {
    this._loading.set(true);
    const params: IncidentListParams = {
      ...this._filters(),
      sort: this._sortKey(),
      direction: (this._sortDirection() ?? 'asc') as 'asc' | 'desc',
      page: this._page(),
      per_page: this._perPage(),
    };
    this.incidentsService.list(params).subscribe({
      next: (res) => {
        this._incidents.set(res.data);
        this._total.set(res.total);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('awaria.messages.load_error'));
      },
    });
  }

  onFilterApply(values: Record<string, string>): void {
    this._filters.set(values);
    this._page.set(1);
    this.load();
  }

  onFilterClear(): void {
    this._filters.set({});
    this._page.set(1);
    this.load();
  }

  onSort(event: DataTableSortEvent): void {
    if (event.direction === null) {
      this._sortKey.set('id');
      this._sortDirection.set('asc');
    } else {
      this._sortKey.set(event.key);
      this._sortDirection.set(event.direction);
    }
    this.load();
  }

  onPageChange(p: number): void {
    this._page.set(p);
    this.load();
  }

  onPerPageChange(pp: number): void {
    this._perPage.set(pp);
    this._page.set(1);
    this.load();
  }

  // --- Nawigacja do podstron ---

  openCreate(): void {
    this.router.navigate(['/incidents/new']);
  }

  openDetails(inc: Incident): void {
    this.router.navigate(['/incidents', inc.id]);
  }

  // --- Pomocnicze ---

  typeLabel(inc: Incident): string {
    return this.t(inc.typ === 'sprzet' ? 'awaria.list.type_equipment' : 'awaria.list.type_other');
  }

  statusBadgeStatus(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'reported',
      w_trakcie_naprawy: 'under_repair',
      naprawiona: 'repaired',
      zamknieta: 'closed',
    };
    return map[status];
  }

  statusBadgeKey(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'awaria.lifecycle.reported',
      w_trakcie_naprawy: 'awaria.lifecycle.under_repair',
      naprawiona: 'awaria.lifecycle.repaired',
      zamknieta: 'awaria.lifecycle.closed',
    };
    return map[status];
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}

