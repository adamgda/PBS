import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { EmployeesService } from '../../services/employees.service';
import { TerminalsService } from '../../services/terminals.service';
import { EquipmentService } from '../../services/equipment.service';
import { InvoicesService } from '../../services/invoices.service';
import { ToastService } from '../../services/toast.service';
import { ConfirmService } from '../../services/confirm.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { StatusBadgeComponent } from '../../components/status-badge/status-badge.component';
import { PhoneLinkComponent } from '../../components/phone-link/phone-link.component';
import { EmailLinkComponent } from '../../components/email-link/email-link.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../components/autocomplete-select/autocomplete-select.component';
import { DatepickerComponent } from '../../components/datepicker/datepicker.component';
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import {
  Employee,
  EmployeeDocument,
  EmployeeListParams,
  CreateEmployeeRequest,
  AssignEmployeeRequest,
  EmployeeRate,
  EmployeeVacation,
  VacationType,
  SettlementPeriod,
  SettlementByPortRow,
  EmployeeSummary,
} from '../../models/employee.model';
import { Invoice, InvoiceStatus, InvoiceTypWystawienia, MissingInvoiceRow } from '../../models/invoice.model';

type ModalMode = 'create' | 'edit' | null;
type DocModalMode = 'create' | 'edit' | null;

/** Klucz szybkiego filtra (chipsy wg mocka pracownicy.html). */
type QuickFilterKey = 'field' | 'available' | 'entitlements' | 'on_leave';

/**
 * Sekcja Pracownicy (Etap 7).
 * Lista (DataTable + filtry: imię, nazwisko, terminal, sprzęt, status),
 * dodawanie/edycja (z przypisaniem terminala/sprzętu), szybkie przypisanie,
 * zarządzanie dokumentami (certyfikaty/uprawnienia z detekcją wygaśnięcia),
 * anonimizacja RODO przy usuwaniu.
 */
@Component({
  selector: 'app-employees',
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
    PhoneLinkComponent,
    EmailLinkComponent,
    FormInputComponent,
    FilterBarComponent,
    AutocompleteSelectComponent,
    DatepickerComponent,
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './employees.component.html',
})
export class EmployeesComponent {
  private readonly employeesService = inject(EmployeesService);
  private readonly terminalsService = inject(TerminalsService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly invoicesService = inject(InvoicesService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly translate = inject(TranslateService);

  private readonly _employees = signal<Employee[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _loading = signal<boolean>(false);
  readonly _page = signal<number>(1);
  readonly _perPage = signal<number>(25);
  readonly _sortKey = signal<string>('id');
  readonly _sortDirection = signal<SortDirection>('asc');
  private readonly _filters = signal<Record<string, string>>({});

  readonly employees = this._employees.asReadonly();
  readonly total = this._total.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  // --- Modal pracownika (dodawanie/edycja) ---
  readonly modalMode = signal<ModalMode>(null);
  readonly modalEmployee = signal<Employee | null>(null);
  readonly modalSaving = signal<boolean>(false);
  readonly modalImie = signal<string>('');
  readonly modalNazwisko = signal<string>('');
  readonly modalTelefon = signal<string>('');
  readonly modalEmail = signal<string>('');
  readonly modalTerminalId = signal<number | null>(null);
  readonly modalSprzetId = signal<number | null>(null);
  readonly modalIsActive = signal<boolean>(true);

  // --- Modal szybkiego przypisania ---
  readonly assignMode = signal<boolean>(false);
  readonly assignEmployee = signal<Employee | null>(null);
  readonly assignTerminalId = signal<number | null>(null);
  readonly assignSprzetId = signal<number | null>(null);
  readonly assignSaving = signal<boolean>(false);

  // --- Panel dokumentów (certyfikaty i uprawnienia) ---
  readonly docsEmployee = signal<Employee | null>(null);
  private readonly _documents = signal<EmployeeDocument[]>([]);
  private readonly _docsLoading = signal<boolean>(false);
  readonly documents = this._documents.asReadonly();
  readonly docsLoading = this._docsLoading.asReadonly();

  readonly docModalMode = signal<DocModalMode>(null);
  readonly docEditing = signal<EmployeeDocument | null>(null);
  readonly docSaving = signal<boolean>(false);
  readonly docNazwa = signal<string>('');
  readonly docNumer = signal<string>('');
  readonly docIssue = signal<string>('');
  readonly docExpiry = signal<string>('');
  readonly docFile = signal<File | null>(null);
  readonly docFileName = signal<string>('');

  // --- Opcje autocomplete ---
  private readonly _terminalOptions = signal<AutocompleteOption[]>([]);
  readonly terminalOptions = this._terminalOptions.asReadonly();
  private readonly _sprzetOptions = signal<AutocompleteOption[]>([]);
  readonly sprzetOptions = this._sprzetOptions.asReadonly();

  // --- Opcje filtrów (terminal + sprzęt) ---
  private readonly _equipmentFilterOptions = signal<AutocompleteOption[]>([]);
  /** Opcje listy rozwijanej dla filtra „Terminal". */
  readonly terminalFilterOptions = computed<{ value: string; label: string }[]>(() =>
    this._terminalOptions().map((o) => ({ value: String(o.value), label: o.label })),
  );
  /** Opcje listy rozwijanej dla filtra „Sprzęt". */
  readonly equipmentFilterOptions = computed<{ value: string; label: string }[]>(() =>
    this._equipmentFilterOptions().map((o) => ({ value: String(o.value), label: o.label })),
  );

  // --- Etap 7a: stawki, urlopy, rozliczenia, KPI, faktury ---
  readonly activeTab = signal<'list' | 'settlement' | 'invoices'>('list');

  // Modal „Zmień stawkę"
  readonly rateEmployee = signal<Employee | null>(null);
  readonly rateValue = signal<string>('');
  readonly rateDataOd = signal<string>('');
  readonly rateSaving = signal<boolean>(false);
  private readonly _rateHistory = signal<EmployeeRate[]>([]);
  readonly rateHistory = this._rateHistory.asReadonly();

  // Panel urlopów
  readonly vacationsEmployee = signal<Employee | null>(null);
  private readonly _vacations = signal<EmployeeVacation[]>([]);
  readonly vacations = this._vacations.asReadonly();
  readonly vacDataOd = signal<string>('');
  readonly vacDataDo = signal<string>('');
  readonly vacTyp = signal<VacationType>('wypoczynkowy');
  readonly vacSaving = signal<boolean>(false);

  // Rozliczenie per port + KPI
  readonly settlementMonth = signal<string>(this.currentMonth());
  readonly settlementPeriod = signal<SettlementPeriod>('all');
  private readonly _portRows = signal<SettlementByPortRow[]>([]);
  readonly portRows = this._portRows.asReadonly();
  private readonly _summary = signal<EmployeeSummary | null>(null);
  readonly summary = this._summary.asReadonly();
  private readonly _summaryLoading = signal<boolean>(false);
  readonly summaryLoading = this._summaryLoading.asReadonly();
  readonly settlementLoading = signal<boolean>(false);

  // Faktury
  private readonly _invoices = signal<Invoice[]>([]);
  readonly invoices = this._invoices.asReadonly();
  private readonly _invoiceTotal = signal<number>(0);
  readonly invoiceTotal = this._invoiceTotal.asReadonly();
  readonly invoicePage = signal<number>(1);
  readonly invoicePerPage = signal<number>(25);
  readonly invoicesLoading = signal<boolean>(false);
  private readonly _missingInvoices = signal<MissingInvoiceRow[]>([]);
  readonly missingInvoices = this._missingInvoices.asReadonly();

  // Modal faktury
  readonly invoiceModalMode = signal<'create' | 'edit' | null>(null);
  readonly invoiceEditing = signal<Invoice | null>(null);
  readonly invoiceSaving = signal<boolean>(false);
  readonly invNumer = signal<string>('');
  readonly invKlient = signal<string>('');
  readonly invKwota = signal<string>('');
  readonly invDataWystawienia = signal<string>('');
  readonly invTerminPlatnosci = signal<string>('');
  readonly invStatus = signal<InvoiceStatus>('wystawiona');
  readonly invTyp = signal<InvoiceTypWystawienia>('po_zleceniu');
  readonly invOrderId = signal<string>('');

  private readonly statusOptions = [
    { value: '1', labelKey: 'pracownicy.status.active' },
    { value: '0', labelKey: 'pracownicy.status.inactive' },
  ];

  readonly columns = computed<DataTableColumn<Employee>[]>(() => [
    { key: 'name', label: this.t('pracownicy.list.name'), isTitle: true, formatter: (e) => `${e.imie} ${e.nazwisko}` },
    { key: 'telefon', label: this.t('pracownicy.list.phone') },
    { key: 'email', label: this.t('pracownicy.list.email'), minWidth: '150px' },
    { key: 'terminal_nazwa', label: this.t('pracownicy.list.terminal') },
    { key: 'sprzet_nazwa', label: this.t('pracownicy.list.equipment') },
    { key: 'stawka_godzinowa', label: this.t('pracownicy.list.hourly_rate'), minWidth: '110px' },
    { key: 'godziny_mc', label: this.t('pracownicy.list.hours_worked'), minWidth: '90px' },
    { key: 'wynagrodzenie', label: this.t('pracownicy.list.wage'), minWidth: '120px' },
    { key: 'rola_dzis', label: this.t('pracownicy.list.role_today') },
    { key: 'is_active', label: this.t('pracownicy.list.status'), sortable: true },
  ]);

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'q', label: this.t('pracownicy.filters.name'), type: 'text', placeholder: this.t('pracownicy.filters.search_name_placeholder') },
    { key: 'terminal_id', label: this.t('pracownicy.filters.terminal'), type: 'select', options: this.terminalFilterOptions() },
    { key: 'sprzet_id', label: this.t('pracownicy.filters.equipment'), type: 'select', options: this.equipmentFilterOptions() },
    { key: 'is_active', label: this.t('pracownicy.filters.status'), type: 'select', options: this.statusOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
  ]);

  // --- Chipsy szybkich filtrów (mock: Wszyscy / W terenie / Dostępni / Uprawnienia / Na urlopie) ---
  readonly quickFilter = signal<QuickFilterKey | null>(null);

  /** Definicje chipsów (klucz + klucz tłumaczenia etykiety). */
  readonly quickFilterChips: { key: QuickFilterKey; label: string }[] = [
    { key: 'field', label: 'pracownicy.quick_filters.field' },
    { key: 'available', label: 'pracownicy.quick_filters.available' },
    { key: 'entitlements', label: 'pracownicy.quick_filters.entitlements' },
    { key: 'on_leave', label: 'pracownicy.quick_filters.on_leave' },
  ];

  /** Lista przefiltrowana klient-side przez aktywny chip. */
  readonly filteredEmployees = computed<Employee[]>(() => {
    const q = this.quickFilter();
    const list = this._employees();
    if (!q) return list;
    return list.filter((e) => this.matchesQuickFilter(e, q));
  });

  /** Liczniki do chipsów — liczone z aktualnie załadowanej strony listy. */
  readonly quickFilterCounts = computed<Record<QuickFilterKey | 'all', number>>(() => {
    const list = this._employees();
    return {
      all: list.length,
      field: list.filter((e) => !!(e.terminal_nazwa || e.sprzet_nazwa)).length,
      available: list.filter((e) => e.is_active && !e.on_leave).length,
      entitlements: list.filter((e) => e.uprawnienie?.status === 'expired' || e.uprawnienie?.status === 'expiring').length,
      on_leave: list.filter((e) => e.on_leave).length,
    };
  });

  setQuickFilter(key: QuickFilterKey | 'all' | null): void {
    this.quickFilter.set(key === 'all' || key === null ? null : key);
    this._page.set(1);
  }

  private matchesQuickFilter(e: Employee, key: QuickFilterKey): boolean {
    switch (key) {
      case 'field':
        return !!(e.terminal_nazwa || e.sprzet_nazwa);
      case 'available':
        return e.is_active && !e.on_leave;
      case 'entitlements':
        return e.uprawnienie?.status === 'expired' || e.uprawnienie?.status === 'expiring';
      case 'on_leave':
        return e.on_leave;
      default:
        return true;
    }
  }

  // --- Menu akcji wiersza (dropdown, oszczędność miejsca) ---
  readonly openActionsId = signal<number | null>(null);

  toggleActions(id: number): void {
    this.openActionsId.update((cur) => (cur === id ? null : id));
  }

  closeActions(): void {
    this.openActionsId.set(null);
  }

  constructor() {
    this.load();
    this.loadTerminalOptions();
    this.loadEquipmentFilterOptions();
    this.loadSummary();
  }

  // --- Ładowanie listy ---

  load(): void {
    this._loading.set(true);
    const params: EmployeeListParams = {
      ...this._filters(),
      sort: this._sortKey(),
      direction: this._sortDirection() === 'desc' ? 'desc' : 'asc',
      page: this._page(),
      per_page: this._perPage(),
    };
    this.employeesService.list(params).subscribe({
      next: (res) => {
        this._employees.set(res.data);
        this._total.set(res.total);
        this._loading.set(false);
        this.refreshSprzetOptions(res.data);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('pracownicy.messages.load_error'));
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

  onPageChange(page: number): void {
    this._page.set(page);
    this.load();
  }

  onPerPageChange(perPage: number): void {
    this._perPage.set(perPage);
    this._page.set(1);
    this.load();
  }

  // --- Modal pracownika (dodawanie/edycja) ---

  openCreate(): void {
    this.modalMode.set('create');
    this.modalEmployee.set(null);
    this.modalImie.set('');
    this.modalNazwisko.set('');
    this.modalTelefon.set('');
    this.modalEmail.set('');
    this.modalTerminalId.set(null);
    this.modalSprzetId.set(null);
    this.modalIsActive.set(true);
  }

  openEdit(employee: Employee): void {
    this.modalMode.set('edit');
    this.modalEmployee.set(employee);
    this.modalImie.set(employee.imie);
    this.modalNazwisko.set(employee.nazwisko);
    this.modalTelefon.set(employee.telefon ?? '');
    this.modalEmail.set(employee.email ?? '');
    this.modalTerminalId.set(employee.current_terminal_id);
    this.modalSprzetId.set(employee.current_sprzet_id);
    this.modalIsActive.set(employee.is_active);
  }

  closeModal(): void {
    this.modalMode.set(null);
    this.modalEmployee.set(null);
    this.modalSaving.set(false);
  }

  onTerminalSelect(opt: AutocompleteOption | null): void {
    this.modalTerminalId.set(opt === null ? null : (typeof opt.value === 'number' ? opt.value : Number(opt.value)));
  }

  onSprzetSelect(opt: AutocompleteOption | null): void {
    this.modalSprzetId.set(opt === null ? null : (typeof opt.value === 'number' ? opt.value : Number(opt.value)));
  }

  saveModal(): void {
    const mode = this.modalMode();
    const editing = this.modalEmployee();

    const imie = this.modalImie().trim();
    if (!imie) {
      this.toastService.error(this.t('pracownicy.messages.first_name_required'));
      return;
    }
    const nazwisko = this.modalNazwisko().trim();
    if (!nazwisko) {
      this.toastService.error(this.t('pracownicy.messages.last_name_required'));
      return;
    }

    // E-mail jest wymagany przy tworzeniu nowego pracownika — na ten adres
    // wysyłany jest link do ustawienia hasła (konto użytkownika z ograniczonym dostępem).
    if (mode !== 'edit') {
      const email = this.modalEmail().trim();
      if (!email) {
        this.toastService.error(this.t('pracownicy.messages.email_required'));
        return;
      }
      if (!this.isValidEmail(email)) {
        this.toastService.error(this.t('pracownicy.messages.email_invalid'));
        return;
      }
    }

    const payload: CreateEmployeeRequest = {
      imie,
      nazwisko,
      telefon: this.modalTelefon().trim() || null,
      email: this.modalEmail().trim() || null,
      current_terminal_id: this.modalTerminalId(),
      current_sprzet_id: this.modalSprzetId(),
      is_active: this.modalIsActive(),
    };

    if (mode === 'edit' && editing) {
      this.modalSaving.set(true);
      this.employeesService.update(editing.id, payload).subscribe({
        next: () => {
          this.modalSaving.set(false);
          this.closeModal();
          this.toastService.success(this.t('pracownicy.messages.updated.success', { name: `${imie} ${nazwisko}` }));
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
    this.employeesService.create(payload).subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(this.t('pracownicy.messages.created.success', { name: `${imie} ${nazwisko}` }));
        this.toastService.info(this.t('pracownicy.messages.created.invite_sent'));
        this.load();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Usuwanie (anonimizacja RODO) ---

  async deleteEmployee(employee: Employee): Promise<void> {
    const name = `${employee.imie} ${employee.nazwisko}`;
    const confirmed = await this.confirmService.confirm({
      title: this.t('pracownicy.messages.delete_confirm_title'),
      message: this.t('pracownicy.messages.delete_confirm_message', { name }),
      danger: true,
    });
    if (!confirmed) return;

    this.employeesService.delete(employee.id).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.deleted.success', { name }));
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Szybkie przypisanie ---

  openAssign(employee: Employee): void {
    this.assignMode.set(true);
    this.assignEmployee.set(employee);
    this.assignTerminalId.set(employee.current_terminal_id);
    this.assignSprzetId.set(employee.current_sprzet_id);
  }

  closeAssign(): void {
    this.assignMode.set(false);
    this.assignEmployee.set(null);
    this.assignSaving.set(false);
  }

  onAssignTerminalSelect(opt: AutocompleteOption | null): void {
    this.assignTerminalId.set(opt === null ? null : (typeof opt.value === 'number' ? opt.value : Number(opt.value)));
  }

  onAssignSprzetSelect(opt: AutocompleteOption | null): void {
    this.assignSprzetId.set(opt === null ? null : (typeof opt.value === 'number' ? opt.value : Number(opt.value)));
  }

  saveAssign(): void {
    const employee = this.assignEmployee();
    if (!employee) return;
    const payload: AssignEmployeeRequest = {
      current_terminal_id: this.assignTerminalId(),
      current_sprzet_id: this.assignSprzetId(),
    };
    this.assignSaving.set(true);
    this.employeesService.assign(employee.id, payload).subscribe({
      next: () => {
        this.assignSaving.set(false);
        this.closeAssign();
        this.toastService.success(this.t('pracownicy.messages.assigned.success', { name: `${employee.imie} ${employee.nazwisko}` }));
        this.load();
      },
      error: (err) => {
        this.assignSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Panel dokumentów (certyfikaty i uprawnienia) ---

  openDocuments(employee: Employee): void {
    this.docsEmployee.set(employee);
    this.loadDocuments(employee.id);
  }

  closeDocuments(): void {
    this.docsEmployee.set(null);
    this._documents.set([]);
    this.closeDocModal();
  }

  loadDocuments(employeeId: number): void {
    this._docsLoading.set(true);
    this.employeesService.listDocuments(employeeId).subscribe({
      next: (res) => {
        this._documents.set(res.data);
        this._docsLoading.set(false);
      },
      error: () => {
        this._docsLoading.set(false);
        this.toastService.error(this.t('common.messages.error.generic'));
      },
    });
  }

  openAddDoc(): void {
    this.docModalMode.set('create');
    this.docEditing.set(null);
    this.docNazwa.set('');
    this.docNumer.set('');
    this.docIssue.set('');
    this.docExpiry.set('');
    this.docFile.set(null);
    this.docFileName.set('');
  }

  openEditDoc(doc: EmployeeDocument): void {
    this.docModalMode.set('edit');
    this.docEditing.set(doc);
    this.docNazwa.set(doc.nazwa);
    this.docNumer.set(doc.numer_dokumentu ?? '');
    this.docIssue.set(doc.data_wydania ?? '');
    this.docExpiry.set(doc.data_waznosci ?? '');
    this.docFile.set(null);
    this.docFileName.set(doc.plik ?? '');
  }

  closeDocModal(): void {
    this.docModalMode.set(null);
    this.docEditing.set(null);
    this.docSaving.set(false);
  }

  onDocFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.docFile.set(input.files[0]);
      this.docFileName.set(input.files[0].name);
    }
  }

  saveDocModal(): void {
    const employee = this.docsEmployee();
    if (!employee) return;
    const nazwa = this.docNazwa().trim();
    if (!nazwa) {
      this.toastService.error(this.t('pracownicy.messages.document_name_required'));
      return;
    }

    const editing = this.docEditing();
    this.docSaving.set(true);
    if (editing) {
      this.employeesService
        .updateDocument(editing.id, {
          nazwa,
          numer_dokumentu: this.docNumer().trim() || null,
          data_wydania: this.docIssue() || null,
          data_waznosci: this.docExpiry() || null,
        })
        .subscribe({
          next: () => {
            this.docSaving.set(false);
            this.closeDocModal();
            this.toastService.success(this.t('pracownicy.messages.document_updated.success', { name: nazwa }));
            this.loadDocuments(employee.id);
          },
          error: (err) => {
            this.docSaving.set(false);
            this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
          },
        });
      return;
    }

    this.employeesService
      .createDocument(
        employee.id,
        {
          nazwa,
          numer_dokumentu: this.docNumer().trim() || null,
          data_wydania: this.docIssue() || null,
          data_waznosci: this.docExpiry() || null,
        },
        this.docFile(),
      )
      .subscribe({
        next: () => {
          this.docSaving.set(false);
          this.closeDocModal();
          this.toastService.success(this.t('pracownicy.messages.document_created.success', { name: nazwa }));
          this.loadDocuments(employee.id);
        },
        error: (err) => {
          this.docSaving.set(false);
          this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
        },
      });
  }

  async deleteDoc(doc: EmployeeDocument): Promise<void> {
    const employee = this.docsEmployee();
    if (!employee) return;
    const confirmed = await this.confirmService.confirm({
      title: this.t('pracownicy.messages.delete_document_confirm_title'),
      message: this.t('pracownicy.messages.delete_document_confirm_message', { name: doc.nazwa }),
      danger: true,
    });
    if (!confirmed) return;

    this.employeesService.deleteDocument(doc.id).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.document_deleted.success', { name: doc.nazwa }));
        this.loadDocuments(employee.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Etap 7a: stawki, urlopy, rozliczenia, KPI, faktury ---

  private currentMonth(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  setTab(tab: 'list' | 'settlement' | 'invoices'): void {
    this.activeTab.set(tab);
    if (tab === 'list') {
      this.loadSummary();
    } else if (tab === 'settlement') {
      this.loadSettlement();
      this.loadSummary();
    } else if (tab === 'invoices') {
      this.loadInvoices();
      this.loadMissingInvoices();
    }
  }

  // --- Stawki ---

  openChangeRate(employee: Employee): void {
    this.rateEmployee.set(employee);
    this.rateValue.set(employee.stawka_godzinowa ? String(employee.stawka_godzinowa) : '');
    this.rateDataOd.set(this.currentMonth() + '-01');
    this.rateSaving.set(false);
    this.loadRateHistory(employee.id);
  }

  closeRateModal(): void {
    this.rateEmployee.set(null);
    this._rateHistory.set([]);
    this.rateSaving.set(false);
  }

  loadRateHistory(employeeId: number): void {
    this.employeesService.listRates(employeeId).subscribe({
      next: (res) => this._rateHistory.set(res.data),
      error: () => this._rateHistory.set([]),
    });
  }

  saveRate(): void {
    const employee = this.rateEmployee();
    if (!employee) return;
    const stawka = parseFloat(this.rateValue().replace(',', '.'));
    if (!stawka || stawka <= 0) {
      this.toastService.error(this.t('pracownicy.messages.rate_invalid'));
      return;
    }
    if (!this.rateDataOd()) {
      this.toastService.error(this.t('pracownicy.messages.rate_date_required'));
      return;
    }
    this.rateSaving.set(true);
    this.employeesService.createRate(employee.id, { stawka_godzinowa: stawka, data_od: this.rateDataOd() }).subscribe({
      next: () => {
        this.rateSaving.set(false);
        this.closeRateModal();
        this.toastService.success(this.t('pracownicy.messages.rate_saved', { name: `${employee.imie} ${employee.nazwisko}` }));
        this.load();
      },
      error: (err) => {
        this.rateSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Urlopy ---

  openVacations(employee: Employee): void {
    this.vacationsEmployee.set(employee);
    this.vacDataOd.set('');
    this.vacDataDo.set('');
    this.vacTyp.set('wypoczynkowy');
    this.loadVacations(employee.id);
  }

  closeVacations(): void {
    this.vacationsEmployee.set(null);
    this._vacations.set([]);
  }

  loadVacations(employeeId: number): void {
    this.employeesService.listVacations(employeeId).subscribe({
      next: (res) => this._vacations.set(res.data),
      error: () => this._vacations.set([]),
    });
  }

  saveVacation(): void {
    const employee = this.vacationsEmployee();
    if (!employee) return;
    if (!this.vacDataOd() || !this.vacDataDo()) {
      this.toastService.error(this.t('pracownicy.messages.vacation_dates_required'));
      return;
    }
    this.vacSaving.set(true);
    this.employeesService
      .createVacation(employee.id, { data_od: this.vacDataOd(), data_do: this.vacDataDo(), typ: this.vacTyp() })
      .subscribe({
        next: () => {
          this.vacSaving.set(false);
          this.vacDataOd.set('');
          this.vacDataDo.set('');
          this.toastService.success(this.t('pracownicy.messages.vacation_added'));
          this.loadVacations(employee.id);
          this.load();
        },
        error: (err) => {
          this.vacSaving.set(false);
          this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
        },
      });
  }

  changeVacationStatus(vac: EmployeeVacation, status: EmployeeVacation['status']): void {
    this.employeesService.updateVacationStatus(vac.id, status).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.vacation_status_updated'));
        const employee = this.vacationsEmployee();
        if (employee) this.loadVacations(employee.id);
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  async deleteVacation(vac: EmployeeVacation): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('pracownicy.messages.vacation_delete_confirm_title'),
      message: this.t('pracownicy.messages.vacation_delete_confirm_message', { range: `${vac.data_od} – ${vac.data_do}` }),
      danger: true,
    });
    if (!confirmed) return;
    this.employeesService.deleteVacation(vac.id).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.vacation_deleted'));
        const employee = this.vacationsEmployee();
        if (employee) this.loadVacations(employee.id);
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }
  // --- Rozliczenie per port + KPI ---

  loadSettlement(): void {
    this.settlementLoading.set(true);
    this.employeesService.settlementByPort(this.settlementMonth(), this.settlementPeriod()).subscribe({
      next: (res) => {
        this._portRows.set(res.data);
        this.settlementLoading.set(false);
      },
      error: () => {
        this.settlementLoading.set(false);
        this._portRows.set([]);
      },
    });
  }

  loadSummary(): void {
    this._summaryLoading.set(true);
    this.employeesService.summary(this.settlementMonth()).subscribe({
      next: (res) => {
        this._summary.set(res);
        this._summaryLoading.set(false);
      },
      error: () => {
        this._summary.set(null);
        this._summaryLoading.set(false);
      },
    });
  }

  changeSettlementPeriod(period: SettlementPeriod): void {
    this.settlementPeriod.set(period);
    this.loadSettlement();
  }

  changeSettlementMonth(month: string): void {
    this.settlementMonth.set(month);
    this.loadSettlement();
    this.loadSummary();
  }

  // --- Faktury ---

  loadInvoices(): void {
    this.invoicesLoading.set(true);
    this.invoicesService
      .list({ page: this.invoicePage(), per_page: this.invoicePerPage(), sort: 'data_wystawienia', direction: 'desc' })
      .subscribe({
        next: (res) => {
          this._invoices.set(res.data);
          this._invoiceTotal.set(res.total);
          this.invoicesLoading.set(false);
        },
        error: () => {
          this.invoicesLoading.set(false);
          this._invoices.set([]);
        },
      });
  }

  loadMissingInvoices(): void {
    this.invoicesService.missing(1, 100).subscribe({
      next: (res) => this._missingInvoices.set(res.data),
      error: () => this._missingInvoices.set([]),
    });
  }

  openAddInvoice(): void {
    this.invoiceModalMode.set('create');
    this.invoiceEditing.set(null);
    this.invNumer.set('');
    this.invKlient.set('');
    this.invKwota.set('');
    this.invDataWystawienia.set(this.currentMonth() + '-01');
    this.invTerminPlatnosci.set('');
    this.invStatus.set('wystawiona');
    this.invTyp.set('po_zleceniu');
    this.invOrderId.set('');
  }

  openEditInvoice(invoice: Invoice): void {
    this.invoiceModalMode.set('edit');
    this.invoiceEditing.set(invoice);
    this.invNumer.set(invoice.numer_faktury);
    this.invKlient.set(invoice.klient_nazwa);
    this.invKwota.set(String(invoice.kwota_pln));
    this.invDataWystawienia.set(invoice.data_wystawienia ?? '');
    this.invTerminPlatnosci.set(invoice.termin_platnosci ?? '');
    this.invStatus.set(invoice.status);
    this.invTyp.set(invoice.typ_wystawienia);
    this.invOrderId.set(invoice.order_id !== null ? String(invoice.order_id) : '');
  }

  closeInvoiceModal(): void {
    this.invoiceModalMode.set(null);
    this.invoiceEditing.set(null);
    this.invoiceSaving.set(false);
  }

  saveInvoiceModal(): void {
    const numer = this.invNumer().trim();
    const klient = this.invKlient().trim();
    if (!numer || !klient || !this.invDataWystawienia()) {
      this.toastService.error(this.t('pracownicy.messages.invoice_required'));
      return;
    }
    const kwota = parseFloat(this.invKwota().replace(',', '.')) || 0;
    const payload = {
      numer_faktury: numer,
      klient_nazwa: klient,
      kwota_pln: kwota,
      data_wystawienia: this.invDataWystawienia(),
      termin_platnosci: this.invTerminPlatnosci() || null,
      status: this.invStatus(),
      typ_wystawienia: this.invTyp(),
      order_id: this.invOrderId() ? Number(this.invOrderId()) : null,
    };
    this.invoiceSaving.set(true);
    const editing = this.invoiceEditing();
    const done = () => {
      this.invoiceSaving.set(false);
      this.closeInvoiceModal();
      this.toastService.success(this.t(editing ? 'pracownicy.messages.invoice_updated' : 'pracownicy.messages.invoice_created', { numer }));
      this.loadInvoices();
      this.loadMissingInvoices();
    };
    const fail = (err: { error?: { error?: string } }): void => {
      this.invoiceSaving.set(false);
      this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
    };
    if (editing) {
      this.invoicesService.update(editing.id, payload).subscribe({ next: done, error: fail });
    } else {
      this.invoicesService.create(payload).subscribe({ next: done, error: fail });
    }
  }

  changeInvoiceStatus(invoice: Invoice, status: InvoiceStatus): void {
    this.invoicesService.updateStatus(invoice.id, status).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.invoice_status_updated'));
        this.loadInvoices();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  async deleteInvoice(invoice: Invoice): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('pracownicy.messages.invoice_delete_confirm_title'),
      message: this.t('pracownicy.messages.invoice_delete_confirm_message', { numer: invoice.numer_faktury }),
      danger: true,
    });
    if (!confirmed) return;
    this.invoicesService.delete(invoice.id).subscribe({
      next: () => {
        this.toastService.success(this.t('pracownicy.messages.invoice_deleted'));
        this.loadInvoices();
        this.loadMissingInvoices();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }
  // --- Pomocnicze ---

  statusLabel(employee: Employee): string {
    return this.t(employee.is_active ? 'pracownicy.status.active' : 'pracownicy.status.inactive');
  }

  roleLabel(role: string | null): string {
    if (!role) return this.t('pracownicy.list.unassigned');
    const map: Record<string, string> = {
      operator: 'pracownicy.roles.operator',
      brygadzista: 'pracownicy.roles.foreman',
      sztauer: 'pracownicy.roles.stevedore',
      lukowy: 'pracownicy.roles.hatch',
      operator_zurawia: 'pracownicy.roles.crane_operator',
    };
    return this.t(map[role] ?? 'pracownicy.list.unassigned');
  }

  vacationTypeLabel(typ: VacationType): string {
    const map: Record<VacationType, string> = {
      wypoczynkowy: 'pracownicy.leave.type_vacation',
      na_zadanie: 'pracownicy.leave.type_on_demand',
      L4: 'pracownicy.leave.type_sick',
    };
    return this.t(map[typ] ?? typ);
  }

  vacationStatusLabel(status: EmployeeVacation['status']): string {
    const map: Record<string, string> = {
      oczekujacy: 'pracownicy.leave.status_planned',
      zatwierdzony: 'pracownicy.leave.status_approved',
      odrzucony: 'pracownicy.leave.status_rejected',
      zrealizowany: 'pracownicy.leave.status_completed',
    };
    return this.t(map[status] ?? status);
  }

  invoiceStatusLabel(status: InvoiceStatus): string {
    const map: Record<InvoiceStatus, string> = {
      wystawiona: 'pracownicy.invoices.status_issued',
      zaplacona: 'pracownicy.invoices.status_paid',
      przeterminowana: 'pracownicy.invoices.status_overdue',
    };
    return this.t(map[status] ?? status);
  }

  invoiceStatusTone(status: InvoiceStatus): 'success' | 'warning' | 'danger' {
    if (status === 'zaplacona') return 'success';
    if (status === 'przeterminowana') return 'danger';
    return 'warning';
  }

  invoiceTypLabel(typ: InvoiceTypWystawienia): string {
    const map: Record<InvoiceTypWystawienia, string> = {
      po_zleceniu: 'pracownicy.invoices.typ_after_order',
      po_tygodniu: 'pracownicy.invoices.typ_after_week',
      koniec_miesiaca: 'pracownicy.invoices.typ_month_end',
    };
    return this.t(map[typ] ?? typ);
  }

  docStatusLabel(doc: EmployeeDocument): string {
    if (doc.is_expired) return this.t('pracownicy.certificates.status_expired');
    if (doc.is_expiring_soon) return this.t('pracownicy.certificates.status_expiring');
    return this.t('pracownicy.certificates.status_valid');
  }

  docStatusTone(doc: EmployeeDocument): 'success' | 'warning' | 'danger' {
    if (doc.is_expired) return 'danger';
    if (doc.is_expiring_soon) return 'warning';
    return 'success';
  }

  fullName(employee: Employee): string {
    return `${employee.imie} ${employee.nazwisko}`;
  }

  private loadTerminalOptions(): void {
    this.terminalsService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._terminalOptions.set(
          res.data.map((t) => ({ value: t.id, label: t.nazwa, sublabel: t.operator })),
        );
      },
      error: () => {
        this._terminalOptions.set([]);
      },
    });
  }

  private loadEquipmentFilterOptions(): void {
    this.equipmentService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._equipmentFilterOptions.set(
          res.data.map((e) => ({ value: e.id, label: e.nazwa })),
        );
      },
      error: () => {
        this._equipmentFilterOptions.set([]);
      },
    });
  }

  private refreshSprzetOptions(employees: Employee[]): void {
    const map = new Map<number, string>();
    for (const e of employees) {
      if (e.current_sprzet_id !== null && e.sprzet_nazwa) {
        map.set(e.current_sprzet_id, e.sprzet_nazwa);
      }
    }
    this._sprzetOptions.set(Array.from(map.entries()).map(([value, label]) => ({ value, label })));
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }

  private isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
}