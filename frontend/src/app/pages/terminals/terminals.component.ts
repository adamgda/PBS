import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

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
import {
  DataTableComponent,
  DataTableColumn,
  DataTableSortEvent,
  SortDirection,
} from '../../components/data-table/data-table.component';

import { Terminal, TerminalListParams, CreateTerminalRequest } from '../../models/terminal.model';

type ModalMode = 'create' | 'edit' | null;

/**
 * Sekcja Terminale (Etap 6).
 * Lista (DataTable + filtry: nazwa, operator, status), dodawanie, edycja,
 * usuwanie (ConfirmDialog).
 */
@Component({
  selector: 'app-terminals',
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
    DataTableComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './terminals.component.html',
})
export class TerminalsComponent {
  private readonly terminalsService = inject(TerminalsService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly translate = inject(TranslateService);

  private readonly _terminals = signal<Terminal[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _loading = signal<boolean>(false);
  readonly _page = signal<number>(1);
  readonly _perPage = signal<number>(25);
  readonly _sortKey = signal<string>('id');
  readonly _sortDirection = signal<SortDirection>('asc');
  private readonly _filters = signal<Record<string, string>>({});

  readonly terminals = this._terminals.asReadonly();
  readonly total = this._total.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  readonly modalMode = signal<ModalMode>(null);
  readonly modalTerminal = signal<Terminal | null>(null);
  readonly modalSaving = signal<boolean>(false);
  readonly modalNazwa = signal<string>('');
  readonly modalAdres = signal<string>('');
  readonly modalOperator = signal<string>('');
  readonly modalPhone = signal<string>('');
  readonly modalEmail = signal<string>('');
  readonly modalIsActive = signal<boolean>(true);

  private readonly statusOptions = [
    { value: '1', labelKey: 'terminale.status.active' },
    { value: '0', labelKey: 'terminale.status.inactive' },
  ];

  readonly columns = computed<DataTableColumn<Terminal>[]>(() => [
    { key: 'nazwa', label: this.t('terminale.list.name'), sortable: true, isTitle: true },
    { key: 'adres', label: this.t('terminale.list.address') },
    { key: 'operator', label: this.t('terminale.list.operator'), sortable: true },
    { key: 'telefon_operatora', label: this.t('terminale.list.phone') },
    { key: 'email_operatora', label: this.t('terminale.list.email') },
    { key: 'is_active', label: this.t('terminale.list.status'), sortable: true },
  ]);

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'nazwa', label: this.t('terminale.filters.name'), type: 'text', placeholder: this.t('terminale.filters.search_placeholder') },
    { key: 'operator', label: this.t('terminale.filters.operator'), type: 'text' },
    { key: 'is_active', label: this.t('terminale.filters.status'), type: 'select', options: this.statusOptions.map((o) => ({ value: o.value, label: this.t(o.labelKey) })) },
  ]);

  constructor() {
    this.load();
  }

  // --- Ładowanie listy ---

  load(): void {
    this._loading.set(true);
    const params: TerminalListParams = {
      ...this._filters(),
      sort: this._sortKey(),
      direction: this._sortDirection() === 'desc' ? 'desc' : 'asc',
      page: this._page(),
      per_page: this._perPage(),
    };
    this.terminalsService.list(params).subscribe({
      next: (res) => {
        this._terminals.set(res.data);
        this._total.set(res.total);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('terminale.messages.load_error'));
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

  // --- Modal: dodawanie / edycja ---

  openCreate(): void {
    this.modalMode.set('create');
    this.modalTerminal.set(null);
    this.modalNazwa.set('');
    this.modalAdres.set('');
    this.modalOperator.set('');
    this.modalPhone.set('');
    this.modalEmail.set('');
    this.modalIsActive.set(true);
  }

  openEdit(terminal: Terminal): void {
    this.modalMode.set('edit');
    this.modalTerminal.set(terminal);
    this.modalNazwa.set(terminal.nazwa);
    this.modalAdres.set(terminal.adres);
    this.modalOperator.set(terminal.operator);
    this.modalPhone.set(terminal.telefon_operatora ?? '');
    this.modalEmail.set(terminal.email_operatora ?? '');
    this.modalIsActive.set(terminal.is_active);
  }

  closeModal(): void {
    this.modalMode.set(null);
    this.modalTerminal.set(null);
    this.modalSaving.set(false);
  }

  saveModal(): void {
    const nazwa = this.modalNazwa().trim();
    if (!nazwa) {
      this.toastService.error(this.t('terminale.messages.name_required'));
      return;
    }
    const adres = this.modalAdres().trim();
    if (!adres) {
      this.toastService.error(this.t('terminale.messages.address_required'));
      return;
    }
    const operator = this.modalOperator().trim();
    if (!operator) {
      this.toastService.error(this.t('terminale.messages.operator_required'));
      return;
    }

    const payload: CreateTerminalRequest = {
      nazwa,
      adres,
      operator,
      telefon_operatora: this.modalPhone().trim() || null,
      email_operatora: this.modalEmail().trim() || null,
      is_active: this.modalIsActive(),
    };

    const mode = this.modalMode();
    const editing = this.modalTerminal();
    if (mode === 'edit' && editing) {
      this.modalSaving.set(true);
      this.terminalsService.update(editing.id, payload).subscribe({
        next: () => {
          this.modalSaving.set(false);
          this.closeModal();
          this.toastService.success(this.t('terminale.messages.updated.success', { name: nazwa }));
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
    this.terminalsService.create(payload).subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(this.t('terminale.messages.created.success', { name: nazwa }));
        this.load();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Usuwanie (ConfirmDialog) ---

  async deleteTerminal(terminal: Terminal): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('terminale.messages.delete_confirm_title'),
      message: this.t('terminale.messages.delete_confirm_message', { name: terminal.nazwa }),
      danger: true,
    });
    if (!confirmed) return;

    this.terminalsService.delete(terminal.id).subscribe({
      next: () => {
        this.toastService.success(this.t('terminale.messages.deleted.success', { name: terminal.nazwa }));
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Pomocnicze ---

  statusLabel(terminal: Terminal): string {
    return this.t(terminal.is_active ? 'terminale.status.active' : 'terminale.status.inactive');
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}