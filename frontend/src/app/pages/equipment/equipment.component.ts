import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

import { EquipmentService } from '../../services/equipment.service';
import { EmployeesService } from '../../services/employees.service';
import { TerminalsService } from '../../services/terminals.service';
import { ToastService } from '../../services/toast.service';
import { ConfirmService } from '../../services/confirm.service';
import { QrService } from '../../services/qr.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { StatusBadgeComponent, StatusTone } from '../../components/status-badge/status-badge.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { DatepickerComponent } from '../../components/datepicker/datepicker.component';
import { SelectComponent } from '../../components/select/select.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../components/autocomplete-select/autocomplete-select.component';
import { TimelineComponent, TimelineEvent } from '../../components/timeline/timeline.component';
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import {
  Equipment,
  ServicePlan,
  EquipmentHistory,
  EquipmentListParams,
  CreateEquipmentRequest,
  AssignEquipmentRequest,
  CreateServicePlanRequest,
  EquipmentCategory,
} from '../../models/equipment.model';
import { QrInfo } from '../../models/qr.model';

type ModalMode = 'create' | 'edit' | 'assign' | 'details' | null;
type PlanModalMode = 'create' | 'edit' | null;

/** Konfiguracja badge'a statusu planu przeglądu (status + ton + klucz etykiety). */
interface PlanStatusConfig {
  status: string;
  tone: StatusTone;
  labelKey: string;
}

/**
 * Sekcja Sprzęt (Etap 8).
 * Lista (DataTable + filtry: nazwa, kategoria, pracownik, terminal, status),
 * dodawanie/edycja (z danymi pojazdu dla kategorii „pojazd"), szybkie przypisanie
 * pracownika/terminala, szczegóły (oś czasu + plany przeglądów z auto-oznaczaniem
 * wymagających serwisu).
 */
@Component({
  selector: 'app-equipment',
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
    DatepickerComponent,
    SelectComponent,
    FilterBarComponent,
    AutocompleteSelectComponent,
    TimelineComponent,
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './equipment.component.html',
})
export class EquipmentComponent {
  private readonly equipmentService = inject(EquipmentService);
  private readonly employeesService = inject(EmployeesService);
  private readonly terminalsService = inject(TerminalsService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly qrService = inject(QrService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly translate = inject(TranslateService);

  private readonly _equipment = signal<Equipment[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _loading = signal<boolean>(false);
  readonly _page = signal<number>(1);
  readonly _perPage = signal<number>(25);
  readonly _sortKey = signal<string>('id');
  readonly _sortDirection = signal<SortDirection>('asc');
  private readonly _filters = signal<Record<string, string>>({});

  readonly equipment = this._equipment.asReadonly();
  readonly total = this._total.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  // --- Menu akcji wiersza (dropdown, jak w Pracownikach) ---
  readonly openActionsId = signal<number | null>(null);

  toggleActions(id: number): void {
    this.openActionsId.update((cur) => (cur === id ? null : id));
  }

  closeActions(): void {
    this.openActionsId.set(null);
  }

  // Modal dodawania/edycji
  readonly modalMode = signal<ModalMode>(null);
  readonly modalEquipment = signal<Equipment | null>(null);
  readonly modalSaving = signal<boolean>(false);
  readonly modalKategoria = signal<EquipmentCategory>('inne');
  readonly modalNazwa = signal<string>('');
  readonly modalSerial = signal<string>('');
  readonly modalIsActive = signal<boolean>(true);
  readonly modalEmployeeId = signal<number | null>(null);
  readonly modalTerminalId = signal<number | null>(null);
  // Dane pojazdu
  readonly modalPrzebieg = signal<string>('0');
  readonly modalOilService = signal<string>('');
  readonly modalIncident = signal<string>('');
  readonly modalOcDate = signal<string>('');
  readonly modalOcResult = signal<string>('');

  // Modal szybkiego przypisania
  readonly assignEquipment = signal<Equipment | null>(null);
  readonly assignSaving = signal<boolean>(false);
  readonly assignEmployeeId = signal<number | null>(null);
  readonly assignTerminalId = signal<number | null>(null);

  // Modal szczegółów (oś czasu + plany)
  readonly detailsEquipment = signal<Equipment | null>(null);
  readonly detailsTimeline = signal<TimelineEvent[]>([]);
  readonly detailsPlans = signal<ServicePlan[]>([]);

  // Sekcje pod tabelą — wybrany sprzęt (oś czasu + planowanie przeglądów)
  readonly selectedEquipment = signal<Equipment | null>(null);
  readonly selectedTimeline = signal<TimelineEvent[]>([]);
  readonly selectedPlans = signal<ServicePlan[]>([]);
  readonly selectedLoading = signal<boolean>(false);

  // Modal planu przeglądu
  readonly planModalMode = signal<PlanModalMode>(null);
  readonly planSaving = signal<boolean>(false);
  readonly planEditingId = signal<number | null>(null);
  readonly planEquipmentId = signal<number | null>(null);
  readonly planTyp = signal<string>('');
  readonly planInterwalKm = signal<string>('');
  readonly planInterwalDni = signal<string>('');
  readonly planLastDone = signal<string>('');
  readonly planNextPlanned = signal<string>('');
  readonly planIsActive = signal<boolean>(true);

  // Modal kodu QR (Etap 20)
  readonly qrModalOpen = signal<boolean>(false);
  readonly qrEquipment = signal<Equipment | null>(null);
  readonly qrLoading = signal<boolean>(false);
  readonly qrNotGenerated = signal<boolean>(false);
  readonly qrError = signal<string | null>(null);
  readonly qrInfo = signal<QrInfo | null>(null);
  readonly qrSvg = signal<SafeHtml | null>(null);

  // Opcje dla autocomplete
  private readonly _employeeOptions = signal<AutocompleteOption[]>([]);
  private readonly _terminalOptions = signal<AutocompleteOption[]>([]);
  readonly employeeOptions = this._employeeOptions.asReadonly();
  readonly terminalOptions = this._terminalOptions.asReadonly();

  // --- Opcje filtrów (pracownik + terminal) ---
  /** Opcje listy rozwijanej dla filtra „Pracownik". */
  readonly employeeFilterOptions = computed<{ value: string; label: string }[]>(() =>
    this._employeeOptions().map((o) => ({ value: String(o.value), label: o.label })),
  );
  /** Opcje listy rozwijanej dla filtra „Terminal". */
  readonly terminalFilterOptions = computed<{ value: string; label: string }[]>(() =>
    this._terminalOptions().map((o) => ({ value: String(o.value), label: o.label })),
  );

  private readonly statusOptions = [
    { value: '1', labelKey: 'sprzet.status.active' },
    { value: '0', labelKey: 'sprzet.status.inactive' },
  ];

  private readonly categoryOptions = [
    { value: 'pojazd', labelKey: 'sprzet.list.category_vehicle' },
    { value: 'inne', labelKey: 'sprzet.list.category_other' },
  ];
  readonly columns = computed<DataTableColumn<Equipment>[]>(() => [
    { key: 'nazwa', label: this.t('sprzet.list.name'), sortable: true, isTitle: true },
    { key: 'kategoria', label: this.t('sprzet.list.category'), sortable: true },
    { key: 'numer_seryjny', label: this.t('sprzet.list.serial_number') },
    { key: 'ostatni_przebieg', label: this.t('sprzet.list.mileage') },
    { key: 'employee_name', label: this.t('sprzet.list.employee') },
    { key: 'terminal_nazwa', label: this.t('sprzet.list.terminal') },
    { key: 'is_active', label: this.t('sprzet.list.status'), sortable: true },
  ]);

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'nazwa', label: this.t('sprzet.filters.name'), type: 'text', placeholder: this.t('sprzet.filters.search_placeholder') },
    { key: 'kategoria', label: this.t('sprzet.filters.category'), type: 'select', options: this.categoryOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
    { key: 'numer_seryjny', label: this.t('sprzet.filters.serial_number'), type: 'text', placeholder: this.t('sprzet.filters.serial_number_placeholder') },
    { key: 'ostatni_przebieg', label: this.t('sprzet.filters.mileage'), type: 'text', placeholder: this.t('sprzet.filters.mileage_placeholder') },
    { key: 'employee_id', label: this.t('sprzet.filters.employee'), type: 'select', options: this.employeeFilterOptions() },
    { key: 'terminal_id', label: this.t('sprzet.filters.terminal'), type: 'select', options: this.terminalFilterOptions() },
    { key: 'is_active', label: this.t('sprzet.filters.status'), type: 'select', options: this.statusOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
  ]);

  constructor() {
    this.load();
    this.loadOptions();
  }

  // --- Ładowanie listy ---

  load(): void {
    this._loading.set(true);
    const params: EquipmentListParams = {
      ...this._filters(),
      sort: this._sortKey(),
      direction: this._sortDirection() === 'desc' ? 'desc' : 'asc',
      page: this._page(),
      per_page: this._perPage(),
    };
    this.equipmentService.list(params).subscribe({
      next: (res) => {
        this._equipment.set(res.data);
        this._total.set(res.total);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('sprzet.messages.load_error'));
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
    this._sortKey.set(event.key);
    this._sortDirection.set(event.direction ?? 'asc');
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
  // --- Modal: dodawanie / edycja ---

  openCreate(): void {
    this.loadOptions();
    this.modalMode.set('create');
    this.modalEquipment.set(null);
    this.modalKategoria.set('inne');
    this.modalNazwa.set('');
    this.modalSerial.set('');
    this.modalIsActive.set(true);
    this.modalEmployeeId.set(null);
    this.modalTerminalId.set(null);
    this.modalPrzebieg.set('0');
    this.modalOilService.set('');
    this.modalIncident.set('');
    this.modalOcDate.set('');
    this.modalOcResult.set('');
  }

  openEdit(eq: Equipment): void {
    this.loadOptions();
    this.modalMode.set('edit');
    this.modalEquipment.set(eq);
    this.modalKategoria.set(eq.kategoria);
    this.modalNazwa.set(eq.nazwa);
    this.modalSerial.set(eq.numer_seryjny ?? '');
    this.modalIsActive.set(eq.is_active);
    this.modalEmployeeId.set(eq.current_employee_id);
    this.modalTerminalId.set(eq.current_terminal_id);
    const vd = eq.vehicle_details;
    this.modalPrzebieg.set(vd ? String(vd.ostatni_przebieg) : '0');
    this.modalOilService.set(vd?.ostatni_serwis_olejowy ?? '');
    this.modalIncident.set(vd?.ostatnia_awaria ?? '');
    this.modalOcDate.set(vd?.data_ostatniej_oc ?? '');
    this.modalOcResult.set(vd?.wynik_ostatniej_oc ?? '');
  }

  closeModal(): void {
    this.modalMode.set(null);
    this.modalEquipment.set(null);
    this.modalSaving.set(false);
  }

  onEmployeeSelected(opt: AutocompleteOption | null): void {
    this.modalEmployeeId.set(opt ? (opt.value as number) : null);
  }

  onTerminalSelected(opt: AutocompleteOption | null): void {
    this.modalTerminalId.set(opt ? (opt.value as number) : null);
  }

  saveModal(): void {
    const nazwa = this.modalNazwa().trim();
    if (!nazwa) {
      this.toastService.error(this.t('sprzet.messages.name_required'));
      return;
    }

    const payload: CreateEquipmentRequest = {
      kategoria: this.modalKategoria(),
      nazwa,
      numer_seryjny: this.modalSerial().trim() || null,
      current_employee_id: this.modalEmployeeId(),
      current_terminal_id: this.modalTerminalId(),
      is_active: this.modalIsActive(),
    };

    if (this.modalKategoria() === 'pojazd') {
      payload.ostatni_przebieg = this.toInt(this.modalPrzebieg(), 0);
      payload.ostatni_serwis_olejowy = this.modalOilService().trim() || null;
      payload.ostatnia_awaria = this.modalIncident().trim() || null;
      payload.data_ostatniej_oc = this.modalOcDate().trim() || null;
      payload.wynik_ostatniej_oc = this.modalOcResult().trim() || null;
    }

    const editing = this.modalEquipment();
    const mode = this.modalMode();
    if (mode === 'edit' && editing) {
      this.modalSaving.set(true);
      this.equipmentService.update(editing.id, payload).subscribe({
        next: () => {
          this.modalSaving.set(false);
          this.closeModal();
          this.toastService.success(this.t('sprzet.messages.updated.success', { name: nazwa }));
          this.load();
        },
        error: (err) => {
          this.modalSaving.set(false);
          this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
        },
      });
      return;
    }

    this.modalSaving.set(true);
    this.equipmentService.create(payload).subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(this.t('sprzet.messages.created.success', { name: nazwa }));
        this.load();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Modal: szybkie przypisanie ---

  openAssign(eq: Equipment): void {
    this.loadOptions();
    this.assignEquipment.set(eq);
    this.assignEmployeeId.set(eq.current_employee_id);
    this.assignTerminalId.set(eq.current_terminal_id);
    this.modalMode.set('assign');
  }

  closeAssignModal(): void {
    this.modalMode.set(null);
    this.assignEquipment.set(null);
    this.assignSaving.set(false);
  }

  onAssignEmployeeSelected(opt: AutocompleteOption | null): void {
    this.assignEmployeeId.set(opt ? (opt.value as number) : null);
  }

  onAssignTerminalSelected(opt: AutocompleteOption | null): void {
    this.assignTerminalId.set(opt ? (opt.value as number) : null);
  }

  saveAssign(): void {
    const eq = this.assignEquipment();
    if (!eq) return;

    const payload: AssignEquipmentRequest = {
      current_employee_id: this.assignEmployeeId(),
      current_terminal_id: this.assignTerminalId(),
    };

    this.assignSaving.set(true);
    this.equipmentService.assign(eq.id, payload).subscribe({
      next: () => {
        this.assignSaving.set(false);
        this.closeAssignModal();
        this.toastService.success(this.t('sprzet.messages.assigned.success', { name: eq.nazwa }));
        this.load();
      },
      error: (err) => {
        this.assignSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Modal: szczegóły (oś czasu + plany) ---

  openDetails(eq: Equipment): void {
    this.detailsEquipment.set(eq);
    this.modalMode.set('details');
    this.loadDetails(eq.id);
  }

  closeDetails(): void {
    this.modalMode.set(null);
    this.detailsEquipment.set(null);
    this.detailsTimeline.set([]);
    this.detailsPlans.set([]);
    this.planModalMode.set(null);
  }

  loadDetails(id: number): void {
    this.equipmentService.get(id).subscribe({
      next: (eq) => {
        this.detailsEquipment.set(eq);
        this.detailsTimeline.set((eq.timeline ?? []).map(this.historyToTimelineEvent));
        this.detailsPlans.set(eq.service_plans ?? []);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Sekcje pod tabelą: historia + planowanie przeglądów (wybrany sprzęt) ---

  /** Zaznacza sprzęt i ładuje jego oś czasu oraz plany przeglądów pod tabelą. */
  selectEquipment(eq: Equipment): void {
    this.selectedEquipment.set(eq);
    this.loadSelectedDetails(eq.id);
  }

  /** Zamyka sekcje pod tabelą (czyści wybór sprzętu). */
  clearSelection(): void {
    this.selectedEquipment.set(null);
    this.selectedTimeline.set([]);
    this.selectedPlans.set([]);
    this.planModalMode.set(null);
  }

  private loadSelectedDetails(id: number): void {
    this.selectedLoading.set(true);
    this.equipmentService.get(id).subscribe({
      next: (eq) => {
        this.selectedEquipment.set(eq);
        this.selectedTimeline.set((eq.timeline ?? []).map(this.historyToTimelineEvent));
        this.selectedPlans.set(eq.service_plans ?? []);
        this.selectedLoading.set(false);
      },
      error: (err) => {
        this.selectedLoading.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  /** Odświeża dane sprzętu w widoku, który go aktualnie pokazuje (sekcja i/lub modal). */
  private refreshForEquipment(id: number): void {
    if (this.selectedEquipment()?.id === id) {
      this.loadSelectedDetails(id);
    }
    if (this.detailsEquipment()?.id === id) {
      this.loadDetails(id);
    }
  }

  /** Cykl przeglądu jako tekst, np. „co 500 km / 90 dni". */
  planCycle(plan: ServicePlan): string {
    const parts: string[] = [];
    if (plan.interwal_km !== null && plan.interwal_km !== undefined) parts.push(`${plan.interwal_km} km`);
    if (plan.interwal_dni !== null && plan.interwal_dni !== undefined) parts.push(`${plan.interwal_dni} dni`);
    return parts.length ? `co ${parts.join(' / ')}` : '—';
  }

  /** Konfiguracja statusu planu przeglądu (badge) — zgodna z mockiem: wkrótce / zaplanowano / nieaktywny. */
  planStatus(plan: ServicePlan): PlanStatusConfig {
    if (plan.needs_service) {
      return { status: 'reported', tone: 'warning', labelKey: 'sprzet.service_plans.status_upcoming' };
    }
    if (!plan.is_active) {
      return { status: 'inactive', tone: 'neutral', labelKey: 'sprzet.service_plans.status_inactive' };
    }
    return { status: 'completed', tone: 'success', labelKey: 'sprzet.service_plans.status_scheduled' };
  }

  // --- Modal: plan przeglądu (w ramach szczegółów) ---

  openPlanCreate(): void {
    this.planEquipmentId.set((this.selectedEquipment() ?? this.detailsEquipment())?.id ?? null);
    this.planModalMode.set('create');
    this.planEditingId.set(null);
    this.planTyp.set('');
    this.planInterwalKm.set('');
    this.planInterwalDni.set('');
    this.planLastDone.set('');
    this.planNextPlanned.set('');
    this.planIsActive.set(true);
  }

  openPlanEdit(plan: ServicePlan): void {
    this.planEquipmentId.set(plan.equipment_id);
    this.planModalMode.set('edit');
    this.planEditingId.set(plan.id);
    this.planTyp.set(plan.typ_przegladu);
    this.planInterwalKm.set(plan.interwal_km !== null ? String(plan.interwal_km) : '');
    this.planInterwalDni.set(plan.interwal_dni !== null ? String(plan.interwal_dni) : '');
    this.planLastDone.set(plan.data_ostatniego_wykonania ?? '');
    this.planNextPlanned.set(plan.data_nastepnego_planowanego ?? '');
    this.planIsActive.set(plan.is_active);
  }

  closePlanModal(): void {
    this.planModalMode.set(null);
    this.planEditingId.set(null);
    this.planSaving.set(false);
  }

  savePlan(): void {
    const equipmentId = this.planEquipmentId();
    if (equipmentId == null) return;

    const typ = this.planTyp().trim();
    if (!typ) {
      this.toastService.error(this.t('sprzet.messages.inspection_type_required'));
      return;
    }

    const payload: CreateServicePlanRequest = {
      typ_przegladu: typ,
      interwal_km: this.planInterwalKm().trim() ? this.toInt(this.planInterwalKm(), 0) : null,
      interwal_dni: this.planInterwalDni().trim() ? this.toInt(this.planInterwalDni(), 0) : null,
      data_ostatniego_wykonania: this.planLastDone().trim() || null,
      data_nastepnego_planowanego: this.planNextPlanned().trim() || null,
      is_active: this.planIsActive(),
    };

    const editingId = this.planEditingId();
    this.planSaving.set(true);

    const done = () => {
      this.planSaving.set(false);
      this.closePlanModal();
      this.refreshForEquipment(equipmentId);
    };
    const fail = (err: unknown): void => {
      this.planSaving.set(false);
      const e = err as { error?: { error?: string } };
      this.toastService.error(e?.error?.error || this.t('common.messages.error.generic'));
    };

    if (editingId !== null) {
      this.equipmentService.updateServicePlan(editingId, payload).subscribe({
        next: () => {
          this.toastService.success(this.t('sprzet.messages.plan_updated.success', { type: typ }));
          done();
        },
        error: fail,
      });
      return;
    }

    this.equipmentService.createServicePlan(equipmentId, payload).subscribe({
      next: () => {
        this.toastService.success(this.t('sprzet.messages.plan_created.success', { type: typ }));
        done();
      },
      error: fail,
    });
  }

  async deletePlan(plan: ServicePlan): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('sprzet.messages.delete_plan_confirm_title'),
      message: this.t('sprzet.messages.delete_plan_confirm_message'),
      danger: true,
    });
    if (!confirmed) return;

    this.equipmentService.deleteServicePlan(plan.id).subscribe({
      next: () => {
        this.toastService.success(this.t('sprzet.messages.plan_deleted.success'));
        this.refreshForEquipment(plan.equipment_id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Usuwanie sprzętu (ConfirmDialog) ---

  async deleteEquipment(eq: Equipment): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('sprzet.messages.delete_confirm_title'),
      message: this.t('sprzet.messages.delete_confirm_message', { name: eq.nazwa }),
      danger: true,
    });
    if (!confirmed) return;

    this.equipmentService.delete(eq.id).subscribe({
      next: () => {
        this.toastService.success(this.t('sprzet.messages.deleted.success', { name: eq.nazwa }));
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Kod QR maszyny (Etap 20) ---

  openQr(eq: Equipment): void {
    this.qrEquipment.set(eq);
    this.qrInfo.set(null);
    this.qrSvg.set(null);
    this.qrNotGenerated.set(false);
    this.qrError.set(null);
    this.qrModalOpen.set(true);
    this.loadQrInfo(eq.id, true);
  }

  closeQr(): void {
    this.qrModalOpen.set(false);
    this.qrEquipment.set(null);
  }

  private loadQrInfo(equipmentId: number, autoGenerate = false): void {
    this.qrLoading.set(true);
    this.qrError.set(null);
    this.qrInfo.set(null);
    this.qrSvg.set(null);

    this.qrService.getQrInfo(equipmentId).subscribe({
      next: (info) => {
        this.qrLoading.set(false);
        this.qrNotGenerated.set(false);
        this.qrInfo.set(info);
        this.qrSvg.set(this.sanitizer.bypassSecurityTrustHtml(info.qr_svg));
      },
      error: (err) => {
        this.qrLoading.set(false);
        if (err?.status === 409) {
          if (autoGenerate) {
            // Brak tokena — wygeneruj automatycznie przy pierwszym otwarciu.
            this.autoGenerateToken(equipmentId);
          } else {
            this.qrNotGenerated.set(true);
          }
        } else {
          this.qrError.set(err?.error?.error || this.t('common.messages.error.generic'));
        }
      },
    });
  }

  private autoGenerateToken(equipmentId: number): void {
    this.qrLoading.set(true);
    this.qrService.generateToken(equipmentId).subscribe({
      next: () => this.loadQrInfo(equipmentId, false),
      error: (err) => {
        this.qrLoading.set(false);
        this.qrError.set(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  generateQr(eq: Equipment): void {
    this.qrService.generateToken(eq.id).subscribe({
      next: () => {
        this.toastService.success(this.t('sprzet.qr.regenerated.success'));
        this.loadQrInfo(eq.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  async regenerateQr(eq: Equipment): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('sprzet.qr.regenerate_confirm_title'),
      message: this.t('sprzet.qr.regenerate_confirm_message'),
      danger: true,
    });
    if (!confirmed) return;

    this.generateQr(eq);
  }

  printQr(): void {
    window.print();
  }

  // --- Pomocnicze ---

  statusLabel(eq: Equipment): string {
    return this.t(eq.is_active ? 'sprzet.status.active' : 'sprzet.status.inactive');
  }

  categoryLabel(eq: Equipment): string {
    return this.t(eq.kategoria === 'pojazd' ? 'sprzet.list.category_vehicle' : 'sprzet.list.category_other');
  }

  timelineTypeLabel(typ: string): string {
    const map: Record<string, string> = {
      przebieg: 'sprzet.timeline.type_mileage',
      serwis: 'sprzet.timeline.type_service',
      awaria: 'sprzet.timeline.type_incident',
      przypisanie: 'sprzet.timeline.type_assignment',
      inne: 'sprzet.timeline.type_other',
    };
    return this.t(map[typ] ?? 'sprzet.timeline.type_other');
  }

  private historyToTimelineEvent = (h: EquipmentHistory): TimelineEvent => ({
    id: h.id,
    type: h.typ,
    description: h.opis,
    date: h.data ?? '',
    icon: this.timelineTypeLabel(h.typ).charAt(0).toUpperCase(),
  });

  private loadOptions(): void {
    this.employeesService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._employeeOptions.set(
          res.data.map((e) => ({ value: e.id, label: `${e.imie} ${e.nazwisko}`, sublabel: e.email ?? undefined })),
        );
      },
      error: () => this._employeeOptions.set([]),
    });
    this.terminalsService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._terminalOptions.set(res.data.map((t) => ({ value: t.id, label: t.nazwa, sublabel: t.operator })));
      },
      error: () => this._terminalOptions.set([]),
    });
  }

  private toInt(value: string, def: number): number {
    const n = parseInt(value, 10);
    return Number.isNaN(n) ? def : n;
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}