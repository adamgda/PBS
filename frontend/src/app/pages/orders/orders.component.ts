import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { OrdersService } from '../../services/orders.service';
import { TerminalsService } from '../../services/terminals.service';
import { EmployeesService } from '../../services/employees.service';
import { EquipmentService } from '../../services/equipment.service';
import { ToastService } from '../../services/toast.service';
import { ConfirmService } from '../../services/confirm.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { StatusBadgeComponent } from '../../components/status-badge/status-badge.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { SelectComponent, SelectOption } from '../../components/select/select.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../components/autocomplete-select/autocomplete-select.component';
import { CalendarComponent, CalendarEvent } from '../../components/calendar/calendar.component';

import {
  Order,
  OrderStatus,
  OrderEmployee,
  OrderEquipment,
  OrderListParams,
  CreateOrderRequest,
} from '../../models/orders.model';

type ModalMode = 'create' | 'edit' | 'assignEmployee' | 'assignEquipment' | 'copyWeek' | null;

/**
 * Sekcja Harmonogram / Zlecenia (Etap 9).
 * Widok kalendarza (tydzień/miesiąc/dzień) zlecenia jako wydarzenia;
 * formularz zlecenia (numer, klient, terminal, datetime, zakres, wartość, status);
 * przypisywanie pracowników i sprzętu; kopiowanie tygodnia jako szablon.
 */
@Component({
  selector: 'app-orders',
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
    CalendarComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './orders.component.html',
})
export class OrdersComponent {
  private readonly ordersService = inject(OrdersService);
  private readonly terminalsService = inject(TerminalsService);
  private readonly employeesService = inject(EmployeesService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly translate = inject(TranslateService);

  private readonly _orders = signal<Order[]>([]);
  private readonly _loading = signal<boolean>(false);

  readonly orders = this._orders.asReadonly();
  readonly loading = this._loading.asReadonly();

  readonly calendarEvents = computed<CalendarEvent[]>(() =>
    this._orders().map((o) => ({
      id: o.id,
      title: `${o.numer_zlecenia} · ${o.klient_nazwa}`,
      date: o.data_rozpoczecia ?? '',
      color: this.statusColor(o.status),
    })),
  );

  // Modal formularza (create/edit)
  readonly modalMode = signal<ModalMode>(null);
  readonly modalSaving = signal<boolean>(false);
  readonly editingId = signal<number | null>(null);
  readonly detailsOrder = signal<Order | null>(null);
  readonly formNumer = signal<string>('');
  readonly formKlient = signal<string>('');
  readonly formTerminalId = signal<number | null>(null);
  readonly formStart = signal<string>('');
  readonly formEnd = signal<string>('');
  readonly formZakres = signal<string>('');
  readonly formWartosc = signal<string>('0');
  readonly formStatus = signal<OrderStatus>('nowe');

  // Modal przypisań
  readonly assignOrder = signal<Order | null>(null);
  readonly assignEmployeeId = signal<number | null>(null);
  readonly assignEquipmentId = signal<number | null>(null);

  // Modal kopiowania tygodnia
  readonly copyWeekSource = signal<string>('');
  readonly copyWeekTarget = signal<string>('');
  readonly copyWeekSaving = signal<boolean>(false);

  // Opcje
  private readonly _terminalOptions = signal<AutocompleteOption[]>([]);
  private readonly _employeeOptions = signal<AutocompleteOption[]>([]);
  private readonly _equipmentOptions = signal<AutocompleteOption[]>([]);
  readonly terminalOptions = this._terminalOptions.asReadonly();
  readonly employeeOptions = this._employeeOptions.asReadonly();
  readonly equipmentOptions = this._equipmentOptions.asReadonly();

  readonly statusSelectOptions: SelectOption[] = [
    { value: 'nowe', labelKey: 'harmonogram.status.nowe' },
    { value: 'w_realizacji', labelKey: 'harmonogram.status.w_realizacji' },
    { value: 'zakonczone', labelKey: 'harmonogram.status.zakonczone' },
  ];

  constructor() {
    this.load();
    this.loadOptions();
  }

  // --- Lista ---

  load(): void {
    this._loading.set(true);
    const params: OrderListParams = { per_page: 100 };
    this.ordersService.list(params).subscribe({
      next: (res) => {
        this._orders.set(res.data);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('harmonogram.messages.load_error'));
      },
    });
  }

  onCalendarEventClick(ev: CalendarEvent): void {
    const order = this._orders().find((o) => o.id === ev.id);
    if (order) {
      this.openEdit(order);
    }
  }

  // --- Modal formularza (create/edit) ---

  openCreate(): void {
    this.modalMode.set('create');
    this.editingId.set(null);
    this.detailsOrder.set(null);
    this.formNumer.set('');
    this.formKlient.set('');
    this.formTerminalId.set(null);
    this.formStart.set('');
    this.formEnd.set('');
    this.formZakres.set('');
    this.formWartosc.set('0');
    this.formStatus.set('nowe');
  }

  openEdit(order: Order): void {
    this.modalMode.set('edit');
    this.editingId.set(order.id);
    this.formNumer.set(order.numer_zlecenia);
    this.formKlient.set(order.klient_nazwa);
    this.formTerminalId.set(order.terminal_id);
    this.formStart.set(this.toLocalInput(order.data_rozpoczecia));
    this.formEnd.set(this.toLocalInput(order.data_zakonczenia));
    this.formZakres.set(order.zakres_prac);
    this.formWartosc.set(String(order.wartosc_pln));
    this.formStatus.set(order.status);
    this.loadDetails(order.id);
  }

  closeModal(): void {
    this.modalMode.set(null);
    this.editingId.set(null);
    this.detailsOrder.set(null);
  }

  onTerminalSelected(opt: AutocompleteOption | null): void {
    this.formTerminalId.set(opt ? Number(opt.value) : null);
  }

  saveModal(): void {
    const numer = this.formNumer().trim();
    if (!numer) {
      this.toastService.error(this.t('harmonogram.messages.number_required'));
      return;
    }
    if (!this.formKlient().trim()) {
      this.toastService.error(this.t('harmonogram.messages.client_required'));
      return;
    }
    if (this.formTerminalId() === null) {
      this.toastService.error(this.t('harmonogram.messages.terminal_required'));
      return;
    }
    if (!this.formStart() || !this.formEnd()) {
      this.toastService.error(this.t('harmonogram.messages.dates_required'));
      return;
    }
    if (new Date(this.formEnd()) < new Date(this.formStart())) {
      this.toastService.error(this.t('harmonogram.messages.date_order'));
      return;
    }

    const payload: CreateOrderRequest = {
      numer_zlecenia: numer,
      klient_nazwa: this.formKlient().trim(),
      terminal_id: this.formTerminalId()!,
      data_rozpoczecia: this.toDbDateTime(this.formStart()),
      data_zakonczenia: this.toDbDateTime(this.formEnd()),
      zakres_prac: this.formZakres(),
      wartosc_pln: this.toFloat(this.formWartosc()),
      status: this.formStatus(),
    };

    const id = this.editingId();
    this.modalSaving.set(true);

    const done = () => {
      this.modalSaving.set(false);
      this.closeModal();
      this.load();
    };
    const fail = (err: unknown): void => {
      this.modalSaving.set(false);
      const e = err as { error?: { error?: string } };
      this.toastService.error(e?.error?.error || this.t('common.messages.error.generic'));
    };

    if (id !== null) {
      this.ordersService.update(id, payload).subscribe({
        next: () => {
          this.toastService.success(this.t('harmonogram.messages.updated.success', { number: numer }));
          done();
        },
        error: fail,
      });
      return;
    }

    this.ordersService.create(payload).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.messages.created.success', { number: numer }));
        done();
      },
      error: fail,
    });
  }

  async deleteOrder(): Promise<void> {
    const id = this.editingId();
    const order = this.detailsOrder();
    if (id === null || !order) return;
    const confirmed = await this.confirmService.confirm({
      title: this.t('harmonogram.messages.delete_confirm_title'),
      message: this.t('harmonogram.messages.delete_confirm_message', { number: order.numer_zlecenia }),
      danger: true,
    });
    if (!confirmed) return;

    this.ordersService.delete(id).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.messages.deleted.success', { number: order.numer_zlecenia }));
        this.closeModal();
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  loadDetails(id: number): void {
    this.ordersService.get(id).subscribe({
      next: (o) => this.detailsOrder.set(o),
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Przypisywanie pracowników i sprzętu ---

  openAssignEmployee(): void {
    const order = this.detailsOrder();
    if (!order) return;
    this.assignOrder.set(order);
    this.assignEmployeeId.set(null);
    this.modalMode.set('assignEmployee');
  }

  openAssignEquipment(): void {
    const order = this.detailsOrder();
    if (!order) return;
    this.assignOrder.set(order);
    this.assignEquipmentId.set(null);
    this.modalMode.set('assignEquipment');
  }

  closeAssign(): void {
    this.modalMode.set('edit');
    this.assignOrder.set(null);
    this.assignEmployeeId.set(null);
    this.assignEquipmentId.set(null);
  }

  onAssignEmployeeSelected(opt: AutocompleteOption | null): void {
    this.assignEmployeeId.set(opt ? Number(opt.value) : null);
  }

  onAssignEquipmentSelected(opt: AutocompleteOption | null): void {
    this.assignEquipmentId.set(opt ? Number(opt.value) : null);
  }

  saveAssignEmployee(): void {
    const order = this.assignOrder();
    const empId = this.assignEmployeeId();
    if (!order || empId === null) return;
    this.ordersService.assignEmployee(order.id, empId).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.added_success'));
        this.closeAssign();
        this.loadDetails(order.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  saveAssignEquipment(): void {
    const order = this.assignOrder();
    const eqId = this.assignEquipmentId();
    if (!order || eqId === null) return;
    this.ordersService.assignEquipment(order.id, eqId).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.added_success'));
        this.closeAssign();
        this.loadDetails(order.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  removeEmployee(emp: OrderEmployee): void {
    const order = this.detailsOrder();
    if (!order || emp.employee_id === null) return;
    this.ordersService.unassignEmployee(order.id, emp.employee_id).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.removed_success'));
        this.loadDetails(order.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  removeEquipment(eq: OrderEquipment): void {
    const order = this.detailsOrder();
    if (!order) return;
    this.ordersService.unassignEquipment(order.id, eq.equipment_id).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.removed_success'));
        this.loadDetails(order.id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Kopiowanie tygodnia ---

  openCopyWeek(): void {
    this.modalMode.set('copyWeek');
    this.copyWeekSource.set(this.mondayOf(new Date()));
    this.copyWeekTarget.set(this.mondayOf(this.addDays(new Date(), 7)));
  }

  closeCopyWeek(): void {
    this.modalMode.set(null);
  }

  doCopyWeek(): void {
    const source = this.copyWeekSource();
    const target = this.copyWeekTarget();
    if (!source || !target) {
      this.toastService.error(this.t('harmonogram.messages.dates_required'));
      return;
    }
    this.copyWeekSaving.set(true);
    this.ordersService.copyWeek({ source_week_start: source, target_week_start: target }).subscribe({
      next: (res) => {
        this.copyWeekSaving.set(false);
        this.toastService.success(this.t('harmonogram.messages.copied.success', { count: res.copied }));
        this.closeCopyWeek();
        this.load();
      },
      error: (err) => {
        this.copyWeekSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Pomocnicze ---

  statusLabel(status: OrderStatus): string {
    const map: Record<OrderStatus, string> = {
      nowe: 'harmonogram.status.nowe',
      w_realizacji: 'harmonogram.status.w_realizacji',
      zakonczone: 'harmonogram.status.zakonczone',
    };
    return this.t(map[status]);
  }

  statusColor(status: OrderStatus): string {
    const map: Record<OrderStatus, string> = {
      nowe: '#3b82f6',
      w_realizacji: '#f59e0b',
      zakonczone: '#22c55e',
    };
    return map[status] ?? '#3b82f6';
  }

  private toLocalInput(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  private toDbDateTime(local: string): string {
    const d = new Date(local);
    if (isNaN(d.getTime())) return local;
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
  }

  private toFloat(value: string): number {
    const n = parseFloat(value);
    return Number.isNaN(n) ? 0 : n;
  }

  private mondayOf(d: Date): string {
    const date = new Date(d);
    const day = (date.getDay() + 6) % 7;
    date.setDate(date.getDate() - day);
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  }

  private addDays(d: Date, days: number): Date {
    const r = new Date(d);
    r.setDate(r.getDate() + days);
    return r;
  }

  private loadOptions(): void {
    this.terminalsService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._terminalOptions.set(res.data.map((t) => ({ value: t.id, label: t.nazwa, sublabel: t.operator ?? undefined })));
      },
      error: () => this._terminalOptions.set([]),
    });
    this.employeesService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._employeeOptions.set(
          res.data.map((e) => ({ value: e.id, label: `${e.imie} ${e.nazwisko}`, sublabel: e.email ?? undefined })),
        );
      },
      error: () => this._employeeOptions.set([]),
    });
    this.equipmentService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._equipmentOptions.set(res.data.map((e) => ({ value: e.id, label: e.nazwa, sublabel: e.numer_seryjny ?? undefined })));
      },
      error: () => this._equipmentOptions.set([]),
    });
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}