import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

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
import { CalendarComponent, CalendarEvent, CalendarView } from '../../components/calendar/calendar.component';

import {
  Order,
  OrderStatus,
  OrderEmployee,
  OrderEquipment,
  OrderListParams,
  CreateOrderRequest,
} from '../../models/orders.model';
import { Employee, EmployeeRole } from '../../models/employee.model';

type ModalMode = 'create' | 'edit' | 'assignEmployee' | 'assignEquipment' | 'copyWeek' | null;

/** Przypisanie pracownika zebrane w formularzu tworzenia zlecenia (aplikowane po utworzeniu). */
interface PendingEmployeeAssignment {
  employee_id: number;
  name: string;
  rola: string | null;
  godziny: number | null;
}

/** Przypisanie sprzętu zebrane w formularzu tworzenia zlecenia (aplikowane po utworzeniu). */
interface PendingEquipmentAssignment {
  equipment_id: number;
  name: string;
}

/** Rząd zmiany w siatce tygodniowej (06–14, 14–22, 22–06). */
interface ShiftRow {
  key: string;
  label: string;
  cells: ShiftCell[];
}

/** Komórka siatki tygodniowej (jeden dzień × jedna zmiana). */
interface ShiftCell {
  date: string;
  isToday: boolean;
  orders: Order[];
}

/** Kolumna dnia w siatce tygodniowej. */
interface WeekDayColumn {
  label: string;
  date: string;
  isToday: boolean;
}

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
  private readonly router = inject(Router);

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
  readonly assignRole = signal<string | null>(null);
  readonly assignGodziny = signal<string>('');
  readonly assignEquipmentId = signal<number | null>(null);

  // Panel "Dostępni pracownicy" (widok główny) — pełna lista z on_leave/stawka
  private readonly _availableEmployees = signal<Employee[]>([]);
  readonly availableEmployees = this._availableEmployees.asReadonly();
  readonly employeeSearch = signal<string>('');

  // Wybrane zlecenie do detalu w widoku głównym (klik na kalendarzu)
  readonly selectedOrder = signal<Order | null>(null);

  // Przypisania oczekujące przy tworzeniu zlecenia (aplikowane po POST /orders)
  readonly pendingEmployees = signal<PendingEmployeeAssignment[]>([]);
  readonly pendingEquipment = signal<PendingEquipmentAssignment[]>([]);

  // Odfiltrowana lista dostępnych pracowników (wg wyszukiwania; urlopowicy widoczni jako wyłączeni)
  readonly filteredAvailableEmployees = computed<Employee[]>(() => {
    const q = this.employeeSearch().trim().toLowerCase();
    const list = this._availableEmployees();
    if (!q) return list;
    return list.filter((e) => `${e.imie} ${e.nazwisko}`.toLowerCase().includes(q));
  });

  // Rozliczenie godzin i wynagrodzeń dla wybranego zlecenia (suma)
  readonly wagesSummary = computed<{ godziny: number; wynagrodzenie: number }>(() => {
    const order = this.selectedOrder();
    const emps = order?.employees ?? [];
    let godziny = 0;
    let wynagrodzenie = 0;
    for (const e of emps) {
      godziny += e.godziny ?? 0;
      wynagrodzenie += e.wynagrodzenie ?? 0;
    }
    return { godziny: Math.round(godziny * 100) / 100, wynagrodzenie: Math.round(wynagrodzenie * 100) / 100 };
  });

  // --- Siatka tygodniowa (widok Harmonogram) ---
  readonly viewMode = signal<CalendarView>('week');
  readonly weekStart = signal<string>(this.mondayOf(new Date()));

  private readonly SHIFT_DEFS: { key: string; label: string; from: number; to: number }[] = [
    { key: '06-14', label: '06–14', from: 6, to: 14 },
    { key: '14-22', label: '14–22', from: 14, to: 22 },
    { key: '22-06', label: '22–06', from: 22, to: 30 },
  ];

  /** Etykieta tygodnia dla podanego poniedziałku (np. „Tydzień 25 · 15–21 czerwca 2026”). */
  weekLabelOf(monday: string): string {
    const d = new Date(monday + 'T00:00:00');
    if (isNaN(d.getTime())) return '';
    const end = this.addDays(d, 6);
    const weekNo = this.isoWeek(d);
    const months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
    const sameMonth = d.getMonth() === end.getMonth();
    const range = sameMonth
      ? `${d.getDate()}–${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`
      : `${d.getDate()} ${months[d.getMonth()]} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`;
    return this.t('harmonogram.view.week_label', { n: weekNo, range });
  }

  /** Etykieta bieżącego okresu: „Tydzień 25 · 15–21 czerwca 2026”. */
  readonly periodLabel = computed<string>(() => this.weekLabelOf(this.weekStart()));

  /** Kolumny dni tygodnia (nagłówek siatki). */
  readonly weekColumns = computed<WeekDayColumn[]>(() => {
    const start = this.weekStart();
    const base = new Date(start + 'T00:00:00');
    const names = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];
    const today = new Date();
    const cols: WeekDayColumn[] = [];
    for (let i = 0; i < 7; i++) {
      const d = this.addDays(base, i);
      cols.push({
        label: `${names[i]} ${d.getDate()}`,
        date: this.toYmd(d),
        isToday: d.toDateString() === today.toDateString(),
      });
    }
    return cols;
  });

  /** Rzędy zmian z komórkami dnia (zlecenia przypisane do dnia + zmiany). */
  readonly shiftRows = computed<ShiftRow[]>(() => {
    const cols = this.weekColumns();
    const orders = this._orders();
    return this.SHIFT_DEFS.map((shift) => ({
      key: shift.key,
      label: shift.label,
      cells: cols.map((col) => ({
        date: col.date,
        isToday: col.isToday,
        orders: orders.filter((o) => this.toYmd(new Date(o.data_rozpoczecia ?? '')) === col.date && this.shiftKeyOf(o) === shift.key),
      })),
    }));
  });

  /** Czy wybrane zlecenie obejmuje >1 zmianę (do boxu „Przekazanie zmiany"). */
  readonly shiftHandover = computed<{ from: string; to: string } | null>(() => {
    const order = this.selectedOrder();
    if (!order || !order.data_rozpoczecia || !order.data_zakonczenia) return null;
    const start = new Date(order.data_rozpoczecia);
    const end = new Date(order.data_zakonczenia);
    if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return null;
    const spanned = this.spannedShifts(start, end);
    const labels: Record<string, string> = { '06-14': '06–14', '14-22': '14–22', '22-06': '22–06' };
    if (spanned.length >= 2) {
      return { from: labels[spanned[0]] ?? spanned[0], to: labels[spanned[1]] ?? spanned[1] };
    }
    return null;
  });

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

  /** Przejście do podstrony „Nowe zlecenie" (formularz poza modalem). */
  goToNewOrder(): void {
    this.router.navigate(['/harmonogram/nowe']);
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
      // Panel detalu w widoku głównym: ustaw wybrane zlecenie i pobierz szczegóły
      this.selectedOrder.set(order);
      this.loadDetails(order.id);
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
    this.pendingEmployees.set([]);
    this.pendingEquipment.set([]);
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
      next: (created) => {
        const id = created.id;
        this.applyPendingAssignments(id, () => {
          this.pendingEmployees.set([]);
          this.pendingEquipment.set([]);
          this.toastService.success(this.t('harmonogram.messages.created.success', { number: numer }));
          done();
        });
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
      next: (o) => {
        this.detailsOrder.set(o);
        // Synchronizuj panel detalu w widoku głównym (odświeżone przypisania)
        this.selectedOrder.set(o);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Przypisywanie pracowników i sprzętu ---

  openAssignEmployee(): void {
    const order = this.detailsOrder() ?? this.selectedOrder();
    if (!order) return;
    this.assignOrder.set(order);
    this.assignEmployeeId.set(null);
    this.assignRole.set(null);
    this.assignGodziny.set('');
    this.modalMode.set('assignEmployee');
  }

  openAssignEquipment(): void {
    const order = this.detailsOrder() ?? this.selectedOrder();
    if (!order) return;
    this.assignOrder.set(order);
    this.assignEquipmentId.set(null);
    this.modalMode.set('assignEquipment');
  }

  closeAssign(): void {
    this.modalMode.set('edit');
    this.assignOrder.set(null);
    this.assignEmployeeId.set(null);
    this.assignRole.set(null);
    this.assignGodziny.set('');
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
    const godzinyRaw = this.assignGodziny().trim();
    const godziny = godzinyRaw === '' ? null : this.toFloat(godzinyRaw);
    this.ordersService
      .assignEmployee(order.id, { employee_id: empId, rola: this.assignRole(), godziny })
      .subscribe({
        next: () => {
          this.toastService.success(this.t('harmonogram.assign.added_success'));
          this.closeAssign();
          this.loadDetails(order.id);
        },
        error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
      });
  }

  /** Szybkie przypisanie pracownika z panelu "Dostępni pracownicy" do wybranego zlecenia. */
  quickAssignEmployee(emp: Employee): void {
    const order = this.selectedOrder();
    if (!order) {
      this.toastService.error(this.t('harmonogram.assign.select_order_first'));
      return;
    }
    this.ordersService
      .assignEmployee(order.id, { employee_id: emp.id, rola: emp.rola_dzis ?? null })
      .subscribe({
        next: () => {
          this.toastService.success(this.t('harmonogram.assign.added_success'));
          this.loadDetails(order.id);
        },
        error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
      });
  }

  /** Czy pracownik jest już przypisany do wybranego zlecenia (do ukrycia przycisku "Przypisz"). */
  isEmployeeAssigned(emp: Employee): boolean {
    const order = this.selectedOrder();
    return !!order?.employees?.some((e) => e.employee_id === emp.id);
  }

  // --- Przypisania podczas tworzenia zlecenia (pille w formularzu) ---

  onPendingEmployeeSelected(opt: AutocompleteOption | null): void {
    if (!opt) return;
    const empId = Number(opt.value);
    if (this.pendingEmployees().some((p) => p.employee_id === empId)) return;
    this.pendingEmployees.update((list) => [
      ...list,
      { employee_id: empId, name: opt.label, rola: null, godziny: null },
    ]);
  }

  removePendingEmployee(item: PendingEmployeeAssignment): void {
    this.pendingEmployees.update((list) => list.filter((p) => p.employee_id !== item.employee_id));
  }

  onPendingEquipmentSelected(opt: AutocompleteOption | null): void {
    if (!opt) return;
    const eqId = Number(opt.value);
    if (this.pendingEquipment().some((p) => p.equipment_id === eqId)) return;
    this.pendingEquipment.update((list) => [...list, { equipment_id: eqId, name: opt.label }]);
  }

  removePendingEquipment(item: PendingEquipmentAssignment): void {
    this.pendingEquipment.update((list) => list.filter((p) => p.equipment_id !== item.equipment_id));
  }

  /** Aplikuje przypisania zebrane podczas tworzenia zlecenia po pomyślnym POST /orders. */
  private applyPendingAssignments(orderId: number, done: () => void): void {
    const emps = this.pendingEmployees();
    const eqs = this.pendingEquipment();
    if (emps.length === 0 && eqs.length === 0) {
      done();
      return;
    }
    let remaining = emps.length + eqs.length;
    const finishOne = (): void => {
      remaining--;
      if (remaining <= 0) done();
    };
    for (const p of emps) {
      this.ordersService
        .assignEmployee(orderId, { employee_id: p.employee_id, rola: p.rola, godziny: p.godziny })
        .subscribe({ next: finishOne, error: finishOne });
    }
    for (const p of eqs) {
      this.ordersService.assignEquipment(orderId, p.equipment_id).subscribe({ next: finishOne, error: finishOne });
    }
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

  /** Ton badge'a statusu zlecenia (dla StatusBadgeComponent). */
  statusTone(status: OrderStatus): 'info' | 'warning' | 'success' {
    const map: Record<OrderStatus, 'info' | 'warning' | 'success'> = {
      nowe: 'info',
      w_realizacji: 'warning',
      zakonczone: 'success',
    };
    return map[status] ?? 'info';
  }

  roleLabel(role: string | null): string {
    if (!role) return this.t('harmonogram.assign.no_role');
    const map: Record<string, string> = {
      operator: 'pracownicy.roles.operator',
      brygadzista: 'pracownicy.roles.foreman',
      sztauer: 'pracownicy.roles.stevedore',
      lukowy: 'pracownicy.roles.hatch',
      operator_zurawia: 'pracownicy.roles.crane_operator',
    };
    return this.t(map[role] ?? role);
  }

  formatCurrency(value: number | null): string {
    const v = value ?? 0;
    return v.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł';
  }

  /** Inicjały do awatara w panelu dostępnych pracowników. */
  initials(imie: string, nazwisko: string): string {
    return ((imie[0] ?? '') + (nazwisko[0] ?? '')).toUpperCase();
  }

  /** Lista nazw przypisanego sprzętu (do pille w detalu zlecenia). */
  equipmentNames(order: Order): string {
    return (order.equipment ?? [])
      .map((eq) => eq.equipment_nazwa || eq.equipment_numer_seryjny || '—')
      .join(', ');
  }

  /** Podtytuł karty zlecenia w siatce: terminal · zakres prac. */
  orderCardSubtitle(order: Order): string {
    const parts: string[] = [];
    if (order.terminal_nazwa) parts.push(order.terminal_nazwa);
    if (order.zakres_prac) parts.push(order.zakres_prac);
    return parts.join(' · ');
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

  /** YYYY-MM-DD z obiektu Date (lokalnie). */
  private toYmd(d: Date): string {
    if (isNaN(d.getTime())) return '';
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  /** Numer tygodnia ISO (1–53). */
  private isoWeek(d: Date): number {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const dayNum = (date.getUTCDay() + 6) % 7;
    date.setUTCDate(date.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(date.getUTCFullYear(), 0, 4));
    return 1 + Math.round(((date.getTime() - firstThursday.getTime()) / 86400000 - 3 + ((firstThursday.getUTCDay() + 6) % 7)) / 7);
  }

  /** Klucz zmiany („06-14" | „14-22" | „22-06") na podstawie godziny rozpoczęcia. */
  private shiftKeyOf(order: Order): string | null {
    const raw = order.data_rozpoczecia;
    if (!raw) return null;
    const d = new Date(raw);
    if (isNaN(d.getTime())) return null;
    const h = d.getHours();
    if (h >= 6 && h < 14) return '06-14';
    if (h >= 14 && h < 22) return '14-22';
    return '22-06';
  }

  /** Uporządkowana lista zmian objętych przedziałem start–end (bez końca). */
  private spannedShifts(start: Date, end: Date): string[] {
    const order: string[] = ['06-14', '14-22', '22-06'];
    const set = new Set<string>();
    const cursor = new Date(start);
    // iterujemy godzinowo aż do (ale nie wliczając) końca
    while (cursor < end) {
      const h = cursor.getHours();
      const key = h >= 6 && h < 14 ? '06-14' : h >= 14 && h < 22 ? '14-22' : '22-06';
      set.add(key);
      cursor.setHours(cursor.getHours() + 1);
      if (set.size === 3) break; // wszystkie zmiany — nie ma sensu liczyć dalej
    }
    return order.filter((k) => set.has(k));
  }

  // --- Nawigacja widoku Harmonogram ---

  prevPeriod(): void {
    if (this.viewMode() === 'week') this.weekStart.set(this.mondayOf(this.addDays(new Date(this.weekStart() + 'T00:00:00'), -7)));
  }

  nextPeriod(): void {
    if (this.viewMode() === 'week') this.weekStart.set(this.mondayOf(this.addDays(new Date(this.weekStart() + 'T00:00:00'), 7)));
  }

  goToday(): void {
    this.weekStart.set(this.mondayOf(new Date()));
  }

  setView(v: CalendarView): void {
    this.viewMode.set(v);
  }

  /** Klasy Tailwind dla karty zlecenia w siatce (wg statusu, jak na mocku). */
  cardColorClasses(status: OrderStatus): string {
    const map: Record<OrderStatus, string> = {
      nowe: 'bg-cyan-50 border-cyan-500 text-cyan-800 dark:bg-cyan-500/20 dark:border-cyan-400 dark:text-cyan-100',
      w_realizacji: 'bg-amber-50 border-amber-500 text-amber-800 dark:bg-amber-500/20 dark:border-amber-400 dark:text-amber-100',
      zakonczone: 'bg-emerald-50 border-emerald-500 text-emerald-800 dark:bg-emerald-500/20 dark:border-emerald-400 dark:text-emerald-100',
    };
    return map[status] ?? map.nowe;
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
        this._availableEmployees.set(res.data);
      },
      error: () => {
        this._employeeOptions.set([]);
        this._availableEmployees.set([]);
      },
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