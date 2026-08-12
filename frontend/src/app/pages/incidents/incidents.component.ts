import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { IncidentsService } from '../../services/incidents.service';
import { EquipmentService } from '../../services/equipment.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { StatusBadgeComponent } from '../../components/status-badge/status-badge.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { SelectComponent } from '../../components/select/select.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../components/autocomplete-select/autocomplete-select.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import {
  Incident,
  IncidentType,
  IncidentStatus,
  IncidentComment,
  IncidentStatusHistory,
  IncidentListParams,
  CreateIncidentRequest,
} from '../../models/incidents.model';

type ModalMode = 'create' | 'details' | null;

/**
 * Sekcja Awaria (Etap 10).
 * Uproszczony formularz zgłoszenia, lista awarii (filtrowanie typ/status),
 * widok szczegółowy (komentarze, historia statusów, czas zakończenia),
 * zmiana statusu (zgłoszona → w trakcie naprawy → naprawiona / zamknięta).
 */
@Component({
  selector: 'app-incidents',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    SvgIconComponent,
    AddButtonComponent,
    ButtonComponent,
    IconButtonComponent,
    StatusBadgeComponent,
    FormInputComponent,
    SelectComponent,
    AutocompleteSelectComponent,
    FilterBarComponent,
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './incidents.component.html',
})
export class IncidentsComponent {
  private readonly incidentsService = inject(IncidentsService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);

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

  // Modal zgłoszenia
  readonly modalMode = signal<ModalMode>(null);
  readonly saving = signal<boolean>(false);
  readonly formTyp = signal<IncidentType>('sprzet');
  readonly formEquipmentId = signal<number | null>(null);
  readonly formOpis = signal<string>('');

  // Modal szczegółów
  readonly detailsIncident = signal<Incident | null>(null);
  readonly detailsComments = signal<IncidentComment[]>([]);
  readonly detailsHistory = signal<IncidentStatusHistory[]>([]);
  readonly statusSaving = signal<boolean>(false);
  readonly newStatus = signal<IncidentStatus>('zgloszona');
  readonly newComment = signal<string>('');
  readonly commentSaving = signal<boolean>(false);

  // Opcje autocomplete
  private readonly _equipmentOptions = signal<AutocompleteOption[]>([]);
  readonly equipmentOptions = this._equipmentOptions.asReadonly();

  readonly statusOptions = [
    { value: 'zgloszona', labelKey: 'awaria.status.zgloszona' },
    { value: 'w_trakcie_naprawy', labelKey: 'awaria.status.w_trakcie_naprawy' },
    { value: 'naprawiona', labelKey: 'awaria.status.naprawiona' },
    { value: 'zamknieta', labelKey: 'awaria.status.zamknieta' },
  ];

  private readonly typeOptions = [
    { value: 'sprzet', labelKey: 'awaria.list.type_equipment' },
    { value: 'inne', labelKey: 'awaria.list.type_other' },
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
    this.loadOptions();
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

  // --- Modal zgłoszenia ---

  openCreate(): void {
    this.modalMode.set('create');
    this.formTyp.set('sprzet');
    this.formEquipmentId.set(null);
    this.formOpis.set('');
  }

  closeModal(): void {
    this.modalMode.set(null);
  }

  onTypeChange(typ: IncidentType): void {
    this.formTyp.set(typ);
    if (typ !== 'sprzet') {
      this.formEquipmentId.set(null);
    }
  }

  onEquipmentSelected(opt: AutocompleteOption | null): void {
    this.formEquipmentId.set(opt ? Number(opt.value) : null);
  }

  saveCreate(): void {
    const typ = this.formTyp();
    const opis = this.formOpis().trim();
    if (!typ) {
      this.toastService.error(this.t('awaria.messages.type_required'));
      return;
    }
    if (!opis) {
      this.toastService.error(this.t('awaria.messages.description_required'));
      return;
    }
    if (typ === 'sprzet' && this.formEquipmentId() === null) {
      this.toastService.error(this.t('awaria.messages.equipment_required'));
      return;
    }

    const payload: CreateIncidentRequest = {
      typ,
      equipment_id: typ === 'sprzet' ? this.formEquipmentId() : null,
      opis,
    };

    this.saving.set(true);
    this.incidentsService.create(payload).subscribe({
      next: () => {
        this.saving.set(false);
        this.toastService.success(this.t('awaria.messages.created.success'));
        this.closeModal();
        this.load();
      },
      error: (err) => {
        this.saving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Modal szczegółów ---

  openDetails(inc: Incident): void {
    this.modalMode.set('details');
    this.detailsIncident.set(inc);
    this.newStatus.set(inc.status);
    this.newComment.set('');
    this.loadDetails(inc.id);
  }

  closeDetails(): void {
    this.modalMode.set(null);
    this.detailsIncident.set(null);
    this.detailsComments.set([]);
    this.detailsHistory.set([]);
  }

  loadDetails(id: number): void {
    this.incidentsService.get(id).subscribe({
      next: (inc) => {
        this.detailsIncident.set(inc);
        this.detailsComments.set(inc.comments ?? []);
        this.detailsHistory.set(inc.status_history ?? []);
        this.newStatus.set(inc.status);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  changeStatus(): void {
    const inc = this.detailsIncident();
    if (!inc) return;
    const status = this.newStatus();
    if (status === inc.status) return;

    this.statusSaving.set(true);
    this.incidentsService.changeStatus(inc.id, { status }).subscribe({
      next: () => {
        this.statusSaving.set(false);
        this.toastService.success(this.t('awaria.messages.status_changed.success'));
        this.loadDetails(inc.id);
      },
      error: (err) => {
        this.statusSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  sendComment(): void {
    const inc = this.detailsIncident();
    if (!inc) return;
    const tresc = this.newComment().trim();
    if (!tresc) {
      this.toastService.error(this.t('awaria.messages.comment_required'));
      return;
    }

    this.commentSaving.set(true);
    this.incidentsService.addComment(inc.id, { tresc }).subscribe({
      next: () => {
        this.commentSaving.set(false);
        this.newComment.set('');
        this.toastService.success(this.t('awaria.messages.comment_added.success'));
        this.loadDetails(inc.id);
      },
      error: (err) => {
        this.commentSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Pomocnicze ---

  typeLabel(inc: Incident): string {
    return this.t(inc.typ === 'sprzet' ? 'awaria.list.type_equipment' : 'awaria.list.type_other');
  }

  statusLabel(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'awaria.status.zgloszona',
      w_trakcie_naprawy: 'awaria.status.w_trakcie_naprawy',
      naprawiona: 'awaria.status.naprawiona',
      zamknieta: 'awaria.status.zamknieta',
    };
    return this.t(map[status]);
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

  downtime(inc: Incident): string {
    if (!inc.data_zgloszenia) return '—';
    const start = new Date(inc.data_zgloszenia).getTime();
    const end = inc.data_zakonczenia ? new Date(inc.data_zakonczenia).getTime() : Date.now();
    const hours = Math.max(0, Math.round((end - start) / 3600000));
    return `${hours} h`;
  }

  // --- Szybkie akcje (lifecycle) ---

  readonly lifecycleSteps = [
    { status: 'zgloszona' as IncidentStatus, labelKey: 'awaria.lifecycle.reported' },
    { status: 'w_trakcie_naprawy' as IncidentStatus, labelKey: 'awaria.lifecycle.under_repair' },
    { status: 'naprawiona' as IncidentStatus, labelKey: 'awaria.lifecycle.repaired' },
    { status: 'zamknieta' as IncidentStatus, labelKey: 'awaria.lifecycle.closed' },
  ];

  private readonly STATUS_ORDER: IncidentStatus[] = ['zgloszona', 'w_trakcie_naprawy', 'naprawiona', 'zamknieta'];

  lifecycleIndex(status: IncidentStatus): number {
    return this.STATUS_ORDER.indexOf(status);
  }

  lifecycleIcon(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'check',
      w_trakcie_naprawy: 'wrench',
      naprawiona: 'check',
      zamknieta: 'close',
    };
    return map[status];
  }

  /** Skrót: ustawia status (np. „naprawiona" / „zamknieta") przez changeStatus(). */
  setStatus(status: IncidentStatus): void {
    const inc = this.detailsIncident();
    if (!inc || inc.status === status) {
      return;
    }
    this.newStatus.set(status);
    this.changeStatus();
  }

  private loadOptions(): void {
    this.equipmentService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._equipmentOptions.set(
          res.data.map((e) => ({ value: e.id, label: e.nazwa, sublabel: e.numer_seryjny ?? undefined })),
        );
      },
      error: () => this._equipmentOptions.set([]),
    });
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}