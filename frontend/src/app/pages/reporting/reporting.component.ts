import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { ReportsService } from '../../services/reports.service';
import { TerminalsService } from '../../services/terminals.service';
import { EquipmentService } from '../../services/equipment.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import {
  AutocompleteSelectComponent,
  AutocompleteOption,
} from '../../components/autocomplete-select/autocomplete-select.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import { TerminalReport, VehicleReport } from '../../models/report.model';

type ReportTab = 'terminal' | 'vehicle';
type ModalMode = 'create' | 'edit' | null;

/**
 * Sekcja Raportowanie (Etap 11).
 * Przełącznik typu raportu (terminal / pojazd), lista (DataTable + filtry),
 * tworzenie i edycja raportów. Raport terminalowy pokazuje auto-dane z harmonogramu.
 */
@Component({
  selector: 'app-reporting',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    SvgIconComponent,
    AddButtonComponent,
    ButtonComponent,
    IconButtonComponent,
    FormInputComponent,
    AutocompleteSelectComponent,
    FilterBarComponent,
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './reporting.component.html',
})
export class ReportingComponent {
  private readonly reportsService = inject(ReportsService);
  private readonly terminalsService = inject(TerminalsService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);

  readonly activeTab = signal<ReportTab>('terminal');

  // --- Stan listy raportów terminalowych ---
  private readonly _terminalReports = signal<TerminalReport[]>([]);
  private readonly _terminalTotal = signal<number>(0);
  private readonly _terminalLoading = signal<boolean>(false);
  readonly _terminalPage = signal<number>(1);
  readonly _terminalPerPage = signal<number>(25);
  readonly _terminalSortKey = signal<string>('id');
  readonly _terminalSortDirection = signal<SortDirection>('asc');
  private readonly _terminalFilters = signal<Record<string, string>>({});

  readonly terminalReports = this._terminalReports.asReadonly();
  readonly terminalTotal = this._terminalTotal.asReadonly();
  readonly terminalLoading = this._terminalLoading.asReadonly();
  readonly terminalPage = this._terminalPage.asReadonly();
  readonly terminalPerPage = this._terminalPerPage.asReadonly();
  readonly terminalSortKey = this._terminalSortKey.asReadonly();
  readonly terminalSortDirection = this._terminalSortDirection.asReadonly();

  // --- Stan listy raportów pojazdowych ---
  private readonly _vehicleReports = signal<VehicleReport[]>([]);
  private readonly _vehicleTotal = signal<number>(0);
  private readonly _vehicleLoading = signal<boolean>(false);
  readonly _vehiclePage = signal<number>(1);
  readonly _vehiclePerPage = signal<number>(25);
  readonly _vehicleSortKey = signal<string>('id');
  readonly _vehicleSortDirection = signal<SortDirection>('asc');
  private readonly _vehicleFilters = signal<Record<string, string>>({});

  readonly vehicleReports = this._vehicleReports.asReadonly();
  readonly vehicleTotal = this._vehicleTotal.asReadonly();
  readonly vehicleLoading = this._vehicleLoading.asReadonly();
  readonly vehiclePage = this._vehiclePage.asReadonly();
  readonly vehiclePerPage = this._vehiclePerPage.asReadonly();
  readonly vehicleSortKey = this._vehicleSortKey.asReadonly();
  readonly vehicleSortDirection = this._vehicleSortDirection.asReadonly();

  // --- Opcje autocomplete ---
  private readonly _terminalOptions = signal<AutocompleteOption[]>([]);
  private readonly _equipmentOptions = signal<AutocompleteOption[]>([]);
  readonly terminalOptions = this._terminalOptions.asReadonly();
  readonly equipmentOptions = this._equipmentOptions.asReadonly();

  // --- Stan modala ---
  readonly modalMode = signal<ModalMode>(null);
  readonly modalSaving = signal<boolean>(false);
  readonly modalReport = signal<TerminalReport | VehicleReport | null>(null);

  // Pola formularza terminala
  readonly modalTerminalId = signal<number | null>(null);
  readonly modalDate = signal<string>('');
  readonly modalOpis = signal<string>('');
  readonly modalUwagi = signal<string>('');

  // Pola formularza pojazdu
  readonly modalEquipmentId = signal<number | null>(null);
  readonly modalPrzebieg = signal<string>('');
  readonly modalPrzebiegOc = signal<string>('');

  /** Auto-dane z harmonogramu (tylko edycja raportu terminalowego). */
  readonly modalAutoData = computed(() => {
    const r = this.modalReport();
    return r && 'auto_data' in r ? r.auto_data : undefined;
  });

  readonly terminalColumns = computed<DataTableColumn<TerminalReport>[]>(() => [
    { key: 'data_raportu', label: this.t('reporting.list.date'), sortable: true, isTitle: true },
    { key: 'terminal_nazwa', label: this.t('reporting.list.terminal'), sortable: true },
    { key: 'opis', label: this.t('reporting.list.opis') },
    { key: 'utworzony_przez_email', label: this.t('reporting.list.author') },
  ]);

  readonly vehicleColumns = computed<DataTableColumn<VehicleReport>[]>(() => [
    { key: 'data_raportu', label: this.t('reporting.list.date'), sortable: true, isTitle: true },
    { key: 'equipment_nazwa', label: this.t('reporting.list.equipment'), sortable: true },
    { key: 'aktualny_przebieg', label: this.t('reporting.list.przebieg'), sortable: true },
    { key: 'przebieg_oc', label: this.t('reporting.list.przebieg_oc') },
    { key: 'zrodlo', label: this.t('reporting.list.source') },
    { key: 'utworzony_przez_email', label: this.t('reporting.list.author') },
  ]);

  readonly terminalFilterConfigs = computed<FilterConfig[]>(() => [
    {
      key: 'terminal_id',
      label: this.t('reporting.filters.terminal'),
      type: 'select',
      options: this._terminalOptions().map((o) => ({ value: String(o.value), label: o.label })),
    },
    { key: 'date_from', label: this.t('reporting.filters.date_from'), type: 'date' },
    { key: 'date_to', label: this.t('reporting.filters.date_to'), type: 'date' },
  ]);

  readonly vehicleFilterConfigs = computed<FilterConfig[]>(() => [
    {
      key: 'equipment_id',
      label: this.t('reporting.filters.equipment'),
      type: 'select',
      options: this._equipmentOptions().map((o) => ({ value: String(o.value), label: o.label })),
    },
    { key: 'date_from', label: this.t('reporting.filters.date_from'), type: 'date' },
    { key: 'date_to', label: this.t('reporting.filters.date_to'), type: 'date' },
    {
      key: 'zrodlo',
      label: this.t('reporting.filters.source'),
      type: 'select',
      options: [
        { value: 'panel', label: this.t('reporting.source.panel') },
        { value: 'qr', label: this.t('reporting.source.qr') },
      ],
    },
  ]);

  constructor() {
    this.loadOptions();
    this.loadTerminalReports();
  }

  setActiveTab(tab: ReportTab): void {
    this.activeTab.set(tab);
    if (tab === 'terminal') {
      this.loadTerminalReports();
    } else {
      this.loadVehicleReports();
    }
  }


  // --- Wczytywanie list ---

  private loadTerminalReports(): void {
    this._terminalLoading.set(true);
    this.reportsService
      .listTerminalReports({
        ...this._terminalFilters(),
        sort: this._terminalSortKey(),
        direction: this._terminalSortDirection() ?? 'asc',
        page: this._terminalPage(),
        per_page: this._terminalPerPage(),
      })
      .subscribe({
        next: (res) => {
          this._terminalReports.set(res.data);
          this._terminalTotal.set(res.total);
          this._terminalLoading.set(false);
        },
        error: () => {
          this._terminalReports.set([]);
          this._terminalTotal.set(0);
          this._terminalLoading.set(false);
        },
      });
  }

  private loadVehicleReports(): void {
    this._vehicleLoading.set(true);
    this.reportsService
      .listVehicleReports({
        ...this._vehicleFilters(),
        sort: this._vehicleSortKey(),
        direction: this._vehicleSortDirection() ?? 'asc',
        page: this._vehiclePage(),
        per_page: this._vehiclePerPage(),
      })
      .subscribe({
        next: (res) => {
          this._vehicleReports.set(res.data);
          this._vehicleTotal.set(res.total);
          this._vehicleLoading.set(false);
        },
        error: () => {
          this._vehicleReports.set([]);
          this._vehicleTotal.set(0);
          this._vehicleLoading.set(false);
        },
      });
  }

  private loadOptions(): void {
    this.terminalsService.list({ per_page: 100 }).subscribe({
      next: (res) =>
        this._terminalOptions.set(
          res.data.map((t) => ({ value: t.id, label: t.nazwa, sublabel: t.operator ?? undefined })),
        ),
      error: () => this._terminalOptions.set([]),
    });
    this.equipmentService.list({ per_page: 100, kategoria: 'pojazd' }).subscribe({
      next: (res) =>
        this._equipmentOptions.set(
          res.data.map((e) => ({ value: e.id, label: e.nazwa, sublabel: e.numer_seryjny ?? undefined })),
        ),
      error: () => this._equipmentOptions.set([]),
    });
  }


  // --- Filtry / sortowanie / paginacja (terminal) ---

  onTerminalFilterApply(filters: Record<string, string>): void {
    this._terminalFilters.set(filters);
    this._terminalPage.set(1);
    this.loadTerminalReports();
  }

  onTerminalFilterClear(): void {
    this._terminalFilters.set({});
    this._terminalPage.set(1);
    this.loadTerminalReports();
  }

  onTerminalSort(e: DataTableSortEvent): void {
    this._terminalSortKey.set(e.key);
    this._terminalSortDirection.set(e.direction);
    this.loadTerminalReports();
  }

  onTerminalPageChange(p: number): void {
    this._terminalPage.set(p);
    this.loadTerminalReports();
  }

  onTerminalPerPageChange(p: number): void {
    this._terminalPerPage.set(p);
    this._terminalPage.set(1);
    this.loadTerminalReports();
  }

  // --- Filtry / sortowanie / paginacja (pojazd) ---

  onVehicleFilterApply(filters: Record<string, string>): void {
    this._vehicleFilters.set(filters);
    this._vehiclePage.set(1);
    this.loadVehicleReports();
  }

  onVehicleFilterClear(): void {
    this._vehicleFilters.set({});
    this._vehiclePage.set(1);
    this.loadVehicleReports();
  }

  onVehicleSort(e: DataTableSortEvent): void {
    this._vehicleSortKey.set(e.key);
    this._vehicleSortDirection.set(e.direction);
    this.loadVehicleReports();
  }

  onVehiclePageChange(p: number): void {
    this._vehiclePage.set(p);
    this.loadVehicleReports();
  }

  onVehiclePerPageChange(p: number): void {
    this._vehiclePerPage.set(p);
    this._vehiclePage.set(1);
    this.loadVehicleReports();
  }


  // --- Modal ---

  openCreate(): void {
    this.modalMode.set('create');
    this.modalReport.set(null);
    this.modalTerminalId.set(null);
    this.modalEquipmentId.set(null);
    this.modalDate.set(new Date().toISOString().slice(0, 10));
    this.modalOpis.set('');
    this.modalUwagi.set('');
    this.modalPrzebieg.set('');
    this.modalPrzebiegOc.set('');
  }

  openEdit(report: TerminalReport | VehicleReport): void {
    this.modalMode.set('edit');
    this.modalReport.set(report);
    this.modalDate.set(report.data_raportu ?? '');
    this.modalOpis.set('opis' in report ? report.opis : '');
    this.modalUwagi.set(report.uwagi ?? '');
    if ('terminal_id' in report) {
      this.modalTerminalId.set(report.terminal_id);
      this.modalEquipmentId.set(null);
      this.modalPrzebieg.set('');
      this.modalPrzebiegOc.set('');
      // Pobierz szczegóły z auto-danymi z harmonogramu.
      this.reportsService.getTerminalReport(report.id).subscribe({
        next: (full) => this.modalReport.set(full),
        error: () => {},
      });
    } else {
      this.modalEquipmentId.set(report.equipment_id);
      this.modalTerminalId.set(null);
      this.modalPrzebieg.set(String(report.aktualny_przebieg));
      this.modalPrzebiegOc.set(report.przebieg_oc);
    }
  }

  closeModal(): void {
    this.modalMode.set(null);
    this.modalReport.set(null);
    this.modalSaving.set(false);
  }

  saveModal(): void {
    const mode = this.modalMode();
    if (mode === null) return;

    if (this.activeTab() === 'terminal') {
      this.saveTerminal(mode);
    } else {
      this.saveVehicle(mode);
    }
  }

  private saveTerminal(mode: ModalMode): void {
    const terminalId = this.modalTerminalId();
    const date = this.modalDate().trim();
    const opis = this.modalOpis().trim();
    if (!terminalId) {
      this.toastService.error(this.t('reporting.messages.terminal_required'));
      return;
    }
    if (!date) {
      this.toastService.error(this.t('reporting.messages.date_required'));
      return;
    }
    if (!opis) {
      this.toastService.error(this.t('reporting.messages.opis_required'));
      return;
    }

    const payload = {
      terminal_id: terminalId,
      data_raportu: date,
      opis,
      uwagi: this.modalUwagi().trim() || null,
    };
    const editing = this.modalReport() as TerminalReport | null;
    this.modalSaving.set(true);
    const request =
      mode === 'edit' && editing
        ? this.reportsService.updateTerminalReport(editing.id, payload)
        : this.reportsService.createTerminalReport(payload);
    request.subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(
          this.t(mode === 'edit' ? 'reporting.messages.updated.success' : 'reporting.messages.created.success'),
        );
        this.loadTerminalReports();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  private saveVehicle(mode: ModalMode): void {
    const equipmentId = this.modalEquipmentId();
    const date = this.modalDate().trim();
    const przebieg = Number(this.modalPrzebieg());
    const przebiegOc = this.modalPrzebiegOc().trim();
    if (!equipmentId) {
      this.toastService.error(this.t('reporting.messages.equipment_required'));
      return;
    }
    if (!date) {
      this.toastService.error(this.t('reporting.messages.date_required'));
      return;
    }
    if (this.modalPrzebieg().trim() === '' || Number.isNaN(przebieg) || przebieg < 0) {
      this.toastService.error(this.t('reporting.messages.przebieg_required'));
      return;
    }
    if (!przebiegOc) {
      this.toastService.error(this.t('reporting.messages.przebieg_oc_required'));
      return;
    }

    const payload = {
      equipment_id: equipmentId,
      data_raportu: date,
      aktualny_przebieg: przebieg,
      przebieg_oc: przebiegOc,
      uwagi: this.modalUwagi().trim() || null,
    };
    const editing = this.modalReport() as VehicleReport | null;
    this.modalSaving.set(true);
    const request =
      mode === 'edit' && editing
        ? this.reportsService.updateVehicleReport(editing.id, payload)
        : this.reportsService.createVehicleReport(payload);
    request.subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(
          this.t(mode === 'edit' ? 'reporting.messages.updated.success' : 'reporting.messages.created.success'),
        );
        this.loadVehicleReports();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Autocomplete ---

  onTerminalSelected(opt: AutocompleteOption | null): void {
    this.modalTerminalId.set(opt ? Number(opt.value) : null);
  }

  onEquipmentSelected(opt: AutocompleteOption | null): void {
    this.modalEquipmentId.set(opt ? Number(opt.value) : null);
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}

