import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { AuditLogsService } from '../../services/audit-logs.service';
import { TranslateService } from '../../services/translate.service';
import { ConfirmService } from '../../services/confirm.service';
import { ToastService } from '../../services/toast.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { DataTableComponent, DataTableColumn, DataTableSortEvent, SortDirection } from '../../components/data-table/data-table.component';
import { AuditLog } from '../../models/audit-log.model';

/**
 * Sekcja Logi audytowe (menu → wyłącznie super_admin).
 * Wyświetla wpisy z tabeli `audit_log` (paginacja + sortowanie) oraz
 * umożliwia wyczyszczenie całego logu.
 * Dostęp wymuszany jest zarówno w menu/guardzie trasy, jak i na backendzie.
 */
@Component({
  selector: 'app-audit-logs',
  standalone: true,
  imports: [CommonModule, TranslatePipe, SvgIconComponent, DataTableComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './audit-logs.component.html',
})
export class AuditLogsComponent {
  private readonly auditLogsService = inject(AuditLogsService);
  private readonly translate = inject(TranslateService);
  private readonly confirmService = inject(ConfirmService);
  private readonly toastService = inject(ToastService);

  private readonly _logs = signal<AuditLog[]>([]);
  private readonly _logsTotal = signal<number>(0);
  private readonly _logsPage = signal<number>(1);
  private readonly _logsPerPage = signal<number>(25);
  private readonly _logsSortKey = signal<string>('id');
  private readonly _logsSortDirection = signal<SortDirection>('desc');
  private readonly _logsLoading = signal<boolean>(false);

  readonly logs = this._logs.asReadonly();
  readonly logsTotal = this._logsTotal.asReadonly();
  readonly logsPage = this._logsPage.asReadonly();
  readonly logsPerPage = this._logsPerPage.asReadonly();
  readonly logsSortKey = this._logsSortKey.asReadonly();
  readonly logsSortDirection = this._logsSortDirection.asReadonly();
  readonly logsLoading = this._logsLoading.asReadonly();

  /** Definicje kolumn tabeli logów (etykiety tłumaczone). */
  readonly logColumns = computed<DataTableColumn<AuditLog>[]>(() => [
    {
      key: 'created_at',
      label: this.t('dashboard.audit.date'),
      sortable: true,
      isTitle: true,
      minWidth: '170px',
      formatter: (row) => this.formatDate(row.created_at),
    },
    { key: 'user_email', label: this.t('dashboard.audit.user'), minWidth: '180px' },
    { key: 'action', label: this.t('dashboard.audit.action'), sortable: true, minWidth: '150px' },
    { key: 'resource_type', label: this.t('dashboard.audit.resource'), sortable: true, minWidth: '130px' },
    { key: 'ip_address', label: this.t('dashboard.audit.ip'), minWidth: '120px' },
    { key: 'user_agent', label: this.t('dashboard.audit.user_agent'), minWidth: '220px' },
  ]);

  constructor() {
    this.loadAuditLogs();
  }

  loadAuditLogs(): void {
    this._logsLoading.set(true);
    this.auditLogsService
      .list({
        action: '',
        user_email: '',
        sort: this._logsSortKey(),
        direction: this._logsSortDirection() === 'asc' ? 'asc' : 'desc',
        page: this._logsPage(),
        per_page: this._logsPerPage(),
      })
      .subscribe({
        next: (res) => {
          this._logs.set(res.data);
          this._logsTotal.set(res.total);
          this._logsLoading.set(false);
        },
        error: (err) => {
          this._logsLoading.set(false);
          this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
        },
      });
  }

  onLogsSort(event: DataTableSortEvent): void {
    this._logsSortKey.set(event.key);
    this._logsSortDirection.set(event.direction);
    this._logsPage.set(1);
    this.loadAuditLogs();
  }

  onLogsPageChange(page: number): void {
    this._logsPage.set(page);
    this.loadAuditLogs();
  }

  onLogsPerPageChange(perPage: number): void {
    this._logsPerPage.set(perPage);
    this._logsPage.set(1);
    this.loadAuditLogs();
  }

  async clearLogs(): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('dashboard.audit.clear_confirm_title'),
      message: this.t('dashboard.audit.clear_confirm_message'),
      confirmText: this.t('dashboard.audit.clear_confirm_button'),
      danger: true,
    });
    if (!confirmed) return;

    this.auditLogsService.clear().subscribe({
      next: () => {
        this.toastService.success(this.t('dashboard.audit.cleared'));
        this._logsPage.set(1);
        this.loadAuditLogs();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.translate(key, params);
  }

  private formatDate(value: string | null): string {
    if (!value) return '';
    const ts = new Date(value.includes('T') ? value : value.replace(' ', 'T')).getTime();
    if (Number.isNaN(ts)) return value;
    return new Intl.DateTimeFormat('pl-PL', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(ts);
  }
}
