import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { OrdersService } from '../../../services/orders.service';
import { TerminalsService } from '../../../services/terminals.service';
import { EmployeesService } from '../../../services/employees.service';
import { EquipmentService } from '../../../services/equipment.service';
import { ToastService } from '../../../services/toast.service';
import { ConfirmService } from '../../../services/confirm.service';
import { TranslateService } from '../../../services/translate.service';
import { TranslatePipe } from '../../../pipes/translate.pipe';
import { SvgIconComponent } from '../../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../../components/button/button.component';
import { FormInputComponent } from '../../../components/form-input/form-input.component';
import { SelectComponent, SelectOption } from '../../../components/select/select.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../../components/autocomplete-select/autocomplete-select.component';
import { IconButtonComponent } from '../../../components/icon-button/icon-button.component';

import { CreateOrderRequest, OrderStatus, Order, OrderEmployee, OrderEquipment } from '../../../models/orders.model';

/** Przypisanie pracownika zebrane w formularzu (aplikowane po utworzeniu). */
interface PendingEmployeeAssignment {
  employee_id: number;
  name: string;
  rola: string | null;
  godziny: number | null;
}

/** Przypisanie sprzętu zebrane w formularzu (aplikowane po utworzeniu). */
interface PendingEquipmentAssignment {
  equipment_id: number;
  name: string;
}

/**
 * Podstrona formularza zlecenia (poza modalem).
 * Bez parametru id działa jako „Nowe zlecenie” (POST /orders + przypisania),
 * a z parametrem id jako „Edytuj zlecenie” (/harmonogram/edytuj/:id) — wczytuje
 * zlecenie, pozwala edytować dane oraz zarządzać przypisaniami pracowników/sprzętu.
 * Po zapisie wraca do /harmonogram.
 */
@Component({
  selector: 'app-order-new',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    SvgIconComponent,
    ButtonComponent,
    FormInputComponent,
    SelectComponent,
    AutocompleteSelectComponent,
    IconButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './order-new.component.html',
})
export class OrderNewComponent {
  private readonly ordersService = inject(OrdersService);
  private readonly terminalsService = inject(TerminalsService);
  private readonly employeesService = inject(EmployeesService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly translate = inject(TranslateService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly saving = signal<boolean>(false);

  readonly formNumer = signal<string>('');
  readonly formKlient = signal<string>('');
  readonly formTerminalId = signal<number | null>(null);
  readonly formStart = signal<string>('');
  readonly formEnd = signal<string>('');
  readonly formZakres = signal<string>('');
  readonly formWartosc = signal<string>('0');
  readonly formStatus = signal<OrderStatus>('nowe');

  // Tryb edycji (podstrona /harmonogram/edytuj/:id)
  readonly editingId = signal<number | null>(null);
  readonly loading = signal<boolean>(false);
  readonly assignedEmployees = signal<OrderEmployee[]>([]);
  readonly assignedEquipment = signal<OrderEquipment[]>([]);
  readonly assignEmployeeId = signal<number | null>(null);
  readonly assignRole = signal<string | null>(null);
  readonly assignGodziny = signal<string>('');
  readonly assignEquipmentId = signal<number | null>(null);

  readonly pendingEmployees = signal<PendingEmployeeAssignment[]>([]);
  readonly pendingEquipment = signal<PendingEquipmentAssignment[]>([]);

  // Opcje autocomplete
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
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.initEdit(Number(id));
      } else {
        this.loadOptions();
      }
    });
  }

  onTerminalSelected(opt: AutocompleteOption | null): void {
    this.formTerminalId.set(opt ? Number(opt.value) : null);
  }

  onPendingEmployeeSelected(opt: AutocompleteOption | null): void {
    if (!opt) return;
    const empId = Number(opt.value);
    if (this.pendingEmployees().some((p) => p.employee_id === empId)) return;
    this.pendingEmployees.update((list) => [...list, { employee_id: empId, name: opt.label, rola: null, godziny: null }]);
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

  save(): void {
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

    this.saving.set(true);

    const id = this.editingId();
    if (id !== null) {
      this.ordersService.update(id, payload).subscribe({
        next: () => {
          this.saving.set(false);
          this.toastService.success(this.t('harmonogram.messages.updated.success', { number: numer }));
          this.router.navigate(['/harmonogram']);
        },
        error: (err) => {
          this.saving.set(false);
          const e = err as { error?: { error?: string } };
          this.toastService.error(e?.error?.error || this.t('common.messages.error.generic'));
        },
      });
      return;
    }

    this.ordersService.create(payload).subscribe({
      next: (created) => {
        this.applyPendingAssignments(created.id, () => {
          this.saving.set(false);
          this.toastService.success(this.t('harmonogram.messages.created.success', { number: numer }));
          this.router.navigate(['/harmonogram']);
        });
      },
      error: (err) => {
        this.saving.set(false);
        const e = err as { error?: { error?: string } };
        this.toastService.error(e?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  cancel(): void {
    this.router.navigate(['/harmonogram']);
  }

  /** Tryb edycji — pobiera zlecenie i wypełnia formularz oraz listy przypisań. */
  private initEdit(id: number): void {
    this.editingId.set(id);
    this.loading.set(true);
    this.loadOptions();
    this.ordersService.get(id).subscribe({
      next: (order) => {
        this.editingId.set(order.id);
        this.formNumer.set(order.numer_zlecenia);
        this.formKlient.set(order.klient_nazwa);
        this.formTerminalId.set(order.terminal_id);
        this.formStart.set(this.toLocalInput(order.data_rozpoczecia));
        this.formEnd.set(this.toLocalInput(order.data_zakonczenia));
        this.formZakres.set(order.zakres_prac);
        this.formWartosc.set(String(order.wartosc_pln));
        this.formStatus.set(order.status);
        this.assignedEmployees.set(order.employees ?? []);
        this.assignedEquipment.set(order.equipment ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toastService.error(this.t('harmonogram.messages.load_error'));
      },
    });
  }

  async delete(): Promise<void> {
    const id = this.editingId();
    if (id === null) return;
    const confirmed = await this.confirmService.confirm({
      title: this.t('harmonogram.messages.delete_confirm_title'),
      message: this.t('harmonogram.messages.delete_confirm_message', { number: this.formNumer() }),
      danger: true,
    });
    if (!confirmed) return;
    this.saving.set(true);
    this.ordersService.delete(id).subscribe({
      next: () => {
        this.saving.set(false);
        this.toastService.success(this.t('harmonogram.messages.deleted.success', { number: this.formNumer() }));
        this.router.navigate(['/harmonogram']);
      },
      error: (err) => {
        this.saving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  onAssignEmployeeSelected(opt: AutocompleteOption | null): void {
    this.assignEmployeeId.set(opt ? Number(opt.value) : null);
  }

  onAssignEquipmentSelected(opt: AutocompleteOption | null): void {
    this.assignEquipmentId.set(opt ? Number(opt.value) : null);
  }

  assignEmployee(): void {
    const id = this.editingId();
    const empId = this.assignEmployeeId();
    if (id === null || empId === null) return;
    const raw = this.assignGodziny().trim();
    const godziny = raw === '' ? null : this.toFloat(raw);
    this.ordersService.assignEmployee(id, { employee_id: empId, rola: this.assignRole(), godziny }).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.added_success'));
        this.assignEmployeeId.set(null);
        this.assignRole.set(null);
        this.assignGodziny.set('');
        this.reloadAssigned(id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  assignEquipment(): void {
    const id = this.editingId();
    const eqId = this.assignEquipmentId();
    if (id === null || eqId === null) return;
    this.ordersService.assignEquipment(id, eqId).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.added_success'));
        this.assignEquipmentId.set(null);
        this.reloadAssigned(id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  unassignEmployee(emp: OrderEmployee): void {
    const id = this.editingId();
    if (id === null || emp.employee_id === null) return;
    this.ordersService.unassignEmployee(id, emp.employee_id).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.removed_success'));
        this.reloadAssigned(id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  unassignEquipment(eq: OrderEquipment): void {
    const id = this.editingId();
    if (id === null) return;
    this.ordersService.unassignEquipment(id, eq.equipment_id).subscribe({
      next: () => {
        this.toastService.success(this.t('harmonogram.assign.removed_success'));
        this.reloadAssigned(id);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  private reloadAssigned(id: number): void {
    this.ordersService.get(id).subscribe({
      next: (order) => {
        this.assignedEmployees.set(order.employees ?? []);
        this.assignedEquipment.set(order.equipment ?? []);
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  private toLocalInput(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  /** Aplikuje przypisania zebrane w formularzu po pomyślnym POST /orders. */
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

