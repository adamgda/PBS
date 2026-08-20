import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';

import { ReportsService } from '../../services/reports.service';
import { TerminalsService } from '../../services/terminals.service';
import { EquipmentService } from '../../services/equipment.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { AuthService } from '../../services/auth.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import {
  AutocompleteSelectComponent,
  AutocompleteOption,
} from '../../components/autocomplete-select/autocomplete-select.component';

import { TerminalReport, VehicleReport, TerminalReportAutoData } from '../../models/report.model';

type ModalMode = 'create' | 'edit' | null;
type ModalKind = 'terminal' | 'vehicle';

/** Wiersz tabeli „Ostatnie raporty" — łączy raporty terminalowe i pojazdowe. */
interface RecentReportRow {
  kind: 'terminal' | 'vehicle';
  report: TerminalReport | VehicleReport;
}

/**
 * Sekcja Raportowanie (Etap 11) — widok zgodny z mockiem (other/mockup5/raportowanie.html):
 * przełącznik typu raportu, inline formularz raportu terminalowego z podglądem auto-danych
 * z harmonogramu (pracownicy + sprzęt + suma), tabela „Ostatnie raporty" oraz modal
 * tworzenia raportu pojazdowego / edycji raportu.
 */
@Component({
  selector: 'app-reporting',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    SvgIconComponent,
    ButtonComponent,
    IconButtonComponent,
    FormInputComponent,
    AutocompleteSelectComponent,
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
  private readonly authService = inject(AuthService);

  // --- Opcje autocomplete ---
  private readonly _terminalOptions = signal<AutocompleteOption[]>([]);
  private readonly _equipmentOptions = signal<AutocompleteOption[]>([]);
  readonly terminalOptions = this._terminalOptions.asReadonly();
  readonly equipmentOptions = this._equipmentOptions.asReadonly();

  /** Osoba sporządzająca raport — e-mail zalogowanego użytkownika. */
  readonly currentUserName = computed(() => this.authService.currentUser?.email ?? '');

  // --- Formularz tworzenia raportu terminalowego (inline, lewa kolumna) ---
  readonly createDate = signal<string>(this.todayDate());
  readonly createTerminalId = signal<number | null>(null);
  readonly createTerminalName = signal('');
  readonly createOpis = signal('');
  readonly createUwagi = signal('');
  readonly createSaving = signal(false);

  // --- Auto-dane z harmonogramu (podgląd, prawa kolumna) ---
  readonly autoData = signal<TerminalReportAutoData | null>(null);
  readonly autoTerminalName = signal('');
  readonly autoLoading = signal(false);

  // --- Ostatnie raporty (terminal + pojazd, posortowane po dacie) ---
  private readonly _recentReports = signal<RecentReportRow[]>([]);
  private readonly _recentLoading = signal<boolean>(false);
  readonly recentReports = this._recentReports.asReadonly();
  readonly recentLoading = this._recentLoading.asReadonly();

  // --- Modal: tworzenie raportu pojazdowego / edycja (terminal lub pojazd) ---
  readonly modalMode = signal<ModalMode>(null);
  readonly modalKind = signal<ModalKind>('vehicle');
  readonly modalReport = signal<TerminalReport | VehicleReport | null>(null);
  readonly modalSaving = signal(false);

  readonly modalTerminalId = signal<number | null>(null);
  readonly modalEquipmentId = signal<number | null>(null);
  readonly modalDate = signal('');
  readonly modalOpis = signal('');
  readonly modalUwagi = signal('');
  readonly modalPrzebieg = signal('');
  readonly modalPrzebiegOc = signal('');

  /** Auto-dane z harmonogramu (tylko edycja raportu terminalowego). */
  readonly modalAutoData = computed(() => {
    const r = this.modalReport();
    return r && 'auto_data' in r ? r.auto_data : undefined;
  });

  constructor() {
    this.loadOptions();
    this.loadRecentReports();
  }

  // --- Inicjalizacja ---

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

  /** Ładuje połączoną listę ostatnich raportów (terminal + pojazd). */
  private loadRecentReports(): void {
    this._recentLoading.set(true);
    forkJoin([
      this.reportsService.listTerminalReports({ page: 1, per_page: 5, sort: 'data_raportu', direction: 'desc' }),
      this.reportsService.listVehicleReports({ page: 1, per_page: 5, sort: 'data_raportu', direction: 'desc' }),
    ]).subscribe({
      next: ([t, v]) => {
        const rows: RecentReportRow[] = [
          ...t.data.map((r) => ({ kind: 'terminal' as const, report: r })),
          ...v.data.map((r) => ({ kind: 'vehicle' as const, report: r })),
        ];
        rows.sort((a, b) => {
          const da = a.report.data_raportu ?? '';
          const db = b.report.data_raportu ?? '';
          return db.localeCompare(da) || b.report.id - a.report.id;
        });
        this._recentReports.set(rows.slice(0, 5));
        this._recentLoading.set(false);
      },
      error: () => {
        this._recentReports.set([]);
        this._recentLoading.set(false);
      },
    });
  }

  // --- Auto-dane z harmonogramu ---

  onTerminalSelected(opt: AutocompleteOption | null): void {
    this.createTerminalId.set(opt ? Number(opt.value) : null);
    this.createTerminalName.set(opt ? opt.label : '');
    this.loadAutoData();
  }

  onDateChange(): void {
    this.loadAutoData();
  }

  private loadAutoData(): void {
    const id = this.createTerminalId();
    const date = this.createDate().trim();
    if (!id || !date) {
      this.autoData.set(null);
      this.autoTerminalName.set('');
      return;
    }
    this.autoLoading.set(true);
    this.reportsService.getTerminalAutoData(id, date).subscribe({
      next: (res) => {
        this.autoData.set(res.auto_data);
        this.autoTerminalName.set(res.terminal_nazwa ?? this.createTerminalName());
        this.autoLoading.set(false);
      },
      error: () => {
        this.autoData.set(null);
        this.autoLoading.set(false);
      },
    });
  }

  // --- Zapis raportu terminalowego (inline) ---

  saveTerminal(): void {
    const terminalId = this.createTerminalId();
    const date = this.createDate().trim();
    const opis = this.createOpis().trim();
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
      uwagi: this.createUwagi().trim() || null,
    };
    this.createSaving.set(true);
    this.reportsService.createTerminalReport(payload).subscribe({
      next: () => {
        this.createSaving.set(false);
        this.resetTerminalForm();
        this.toastService.success(this.t('reporting.messages.created.success'));
        this.loadRecentReports();
      },
      error: (err) => {
        this.createSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  private resetTerminalForm(): void {
    this.createTerminalId.set(null);
    this.createTerminalName.set('');
    this.createOpis.set('');
    this.createUwagi.set('');
    this.createDate.set(this.todayDate());
    this.autoData.set(null);
    this.autoTerminalName.set('');
  }


  // --- Modal: pojazd (nowy) / edycja raportu ---

  openVehicleCreate(): void {
    this.modalMode.set('create');
    this.modalKind.set('vehicle');
    this.modalReport.set(null);
    this.modalEquipmentId.set(null);
    this.modalTerminalId.set(null);
    this.modalDate.set(this.todayDate());
    this.modalOpis.set('');
    this.modalUwagi.set('');
    this.modalPrzebieg.set('');
    this.modalPrzebiegOc.set('');
  }

  openEdit(row: RecentReportRow): void {
    const report = row.report;
    this.modalMode.set('edit');
    this.modalKind.set(row.kind);
    this.modalReport.set(report);
    this.modalDate.set(report.data_raportu ?? '');
    this.modalUwagi.set(report.uwagi ?? '');

    if (row.kind === 'terminal') {
      const r = report as TerminalReport;
      this.modalTerminalId.set(r.terminal_id);
      this.modalEquipmentId.set(null);
      this.modalOpis.set(r.opis);
      this.modalPrzebieg.set('');
      this.modalPrzebiegOc.set('');
      // Pobierz szczegóły z auto-danymi z harmonogramu.
      this.reportsService.getTerminalReport(r.id).subscribe({
        next: (full) => this.modalReport.set(full),
        error: () => {},
      });
    } else {
      const r = report as VehicleReport;
      this.modalEquipmentId.set(r.equipment_id);
      this.modalTerminalId.set(null);
      this.modalOpis.set('');
      this.modalPrzebieg.set(String(r.aktualny_przebieg));
      this.modalPrzebiegOc.set(r.przebieg_oc);
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
    if (this.modalKind() === 'vehicle') {
      this.saveVehicle(mode);
    } else {
      this.saveTerminalModal(mode);
    }
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
        this.loadRecentReports();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  private saveTerminalModal(mode: ModalMode): void {
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
        this.loadRecentReports();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Autocomplete (modal) ---

  onModalTerminalSelected(opt: AutocompleteOption | null): void {
    this.modalTerminalId.set(opt ? Number(opt.value) : null);
  }

  onModalEquipmentSelected(opt: AutocompleteOption | null): void {
    this.modalEquipmentId.set(opt ? Number(opt.value) : null);
  }

  // --- Pomocnicze ---

  private todayDate(): string {
    return new Date().toISOString().slice(0, 10);
  }

  /** Oznaczenie obiektu w tabeli „Ostatnie raporty". */
  recentObject(row: RecentReportRow): string {
    return row.kind === 'terminal'
      ? ((row.report as TerminalReport).terminal_nazwa ?? '—')
      : ((row.report as VehicleReport).equipment_nazwa ?? '—');
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}

