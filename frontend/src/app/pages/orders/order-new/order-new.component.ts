import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { OrdersService } from '../../../services/orders.service';
import { TerminalsService } from '../../../services/terminals.service';
import { EmployeesService } from '../../../services/employees.service';
import { EquipmentService } from '../../../services/equipment.service';
import { ToastService } from '../../../services/toast.service';
import { TranslateService } from '../../../services/translate.service';
import { TranslatePipe } from '../../../pipes/translate.pipe';
import { SvgIconComponent } from '../../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../../components/button/button.component';
import { FormInputComponent } from '../../../components/form-input/form-input.component';
import { SelectComponent, SelectOption } from '../../../components/select/select.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../../components/autocomplete-select/autocomplete-select.component';

import { CreateOrderRequest, OrderStatus } from '../../../models/orders.model';

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
 * Podstrona „Nowe zlecenie" — formularz tworzenia zlecenia poza modalem.
 * Po zapisie (POST /orders + przypisania) wraca do /harmonogram.
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
  private readonly translate = inject(TranslateService);
  private readonly router = inject(Router);

  readonly saving = signal<boolean>(false);

  readonly formNumer = signal<string>('');
  readonly formKlient = signal<string>('');
  readonly formTerminalId = signal<number | null>(null);
  readonly formStart = signal<string>('');
  readonly formEnd = signal<string>('');
  readonly formZakres = signal<string>('');
  readonly formWartosc = signal<string>('0');
  readonly formStatus = signal<OrderStatus>('nowe');

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
    this.loadOptions();
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

