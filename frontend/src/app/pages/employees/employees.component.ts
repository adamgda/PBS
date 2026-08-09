import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { EmployeesService } from '../../services/employees.service';
import { TerminalsService } from '../../services/terminals.service';
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
} from '../../models/employee.model';

type ModalMode = 'create' | 'edit' | null;
type DocModalMode = 'create' | 'edit' | null;

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

  private readonly statusOptions = [
    { value: '1', labelKey: 'pracownicy.status.active' },
    { value: '0', labelKey: 'pracownicy.status.inactive' },
  ];

  readonly columns = computed<DataTableColumn<Employee>[]>(() => [
    { key: 'imie', label: this.t('pracownicy.list.first_name'), sortable: true, isTitle: true },
    { key: 'nazwisko', label: this.t('pracownicy.list.last_name'), sortable: true },
    { key: 'telefon', label: this.t('pracownicy.list.phone') },
    { key: 'email', label: this.t('pracownicy.list.email') },
    { key: 'terminal_nazwa', label: this.t('pracownicy.list.terminal') },
    { key: 'sprzet_nazwa', label: this.t('pracownicy.list.equipment') },
    { key: 'is_active', label: this.t('pracownicy.list.status'), sortable: true },
  ]);

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'imie', label: this.t('pracownicy.filters.first_name'), type: 'text', placeholder: this.t('pracownicy.filters.search_name_placeholder') },
    { key: 'nazwisko', label: this.t('pracownicy.filters.last_name'), type: 'text', placeholder: this.t('pracownicy.filters.search_last_name_placeholder') },
    { key: 'is_active', label: this.t('pracownicy.filters.status'), type: 'select', options: this.statusOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
  ]);

  constructor() {
    this.load();
    this.loadTerminalOptions();
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

    const payload: CreateEmployeeRequest = {
      imie,
      nazwisko,
      telefon: this.modalTelefon().trim() || null,
      email: this.modalEmail().trim() || null,
      current_terminal_id: this.modalTerminalId(),
      current_sprzet_id: this.modalSprzetId(),
      is_active: this.modalIsActive(),
    };

    const mode = this.modalMode();
    const editing = this.modalEmployee();
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

  // --- Pomocnicze ---

  statusLabel(employee: Employee): string {
    return this.t(employee.is_active ? 'pracownicy.status.active' : 'pracownicy.status.inactive');
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
}