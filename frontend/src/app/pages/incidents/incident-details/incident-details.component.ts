import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { IncidentsService } from '../../../services/incidents.service';
import { ToastService } from '../../../services/toast.service';
import { TranslateService } from '../../../services/translate.service';
import { TranslatePipe } from '../../../pipes/translate.pipe';
import { SvgIconComponent } from '../../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../../components/button/button.component';
import { StatusBadgeComponent } from '../../../components/status-badge/status-badge.component';
import { SelectComponent } from '../../../components/select/select.component';

import {
  Incident,
  IncidentStatus,
  IncidentComment,
  IncidentStatusHistory,
} from '../../../models/incidents.model';

/**
 * Podstrona szczegółów awarii (poza modalem) — /incidents/:id.
 * Wczytuje awarię po id, pokazuje lifecycle, statystyki, opis, zmianę statusu,
 * szybkie akcje, komentarze i historię statusów. Przycisk „Wstecz” wraca do /incidents.
 */
@Component({
  selector: 'app-incident-details',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    SvgIconComponent,
    ButtonComponent,
    StatusBadgeComponent,
    SelectComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './incident-details.component.html',
})
export class IncidentDetailsComponent {
  private readonly incidentsService = inject(IncidentsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly incident = signal<Incident | null>(null);
  readonly loading = signal<boolean>(false);
  readonly comments = signal<IncidentComment[]>([]);
  readonly history = signal<IncidentStatusHistory[]>([]);
  readonly statusSaving = signal<boolean>(false);
  readonly newStatus = signal<IncidentStatus>('zgloszona');
  readonly newComment = signal<string>('');
  readonly commentSaving = signal<boolean>(false);

  readonly statusOptions = [
    { value: 'zgloszona', labelKey: 'awaria.status.zgloszona' },
    { value: 'w_trakcie_naprawy', labelKey: 'awaria.status.w_trakcie_naprawy' },
    { value: 'naprawiona', labelKey: 'awaria.status.naprawiona' },
    { value: 'zamknieta', labelKey: 'awaria.status.zamknieta' },
  ];

  readonly lifecycleSteps = [
    { status: 'zgloszona' as IncidentStatus, labelKey: 'awaria.lifecycle.reported' },
    { status: 'w_trakcie_naprawy' as IncidentStatus, labelKey: 'awaria.lifecycle.under_repair' },
    { status: 'naprawiona' as IncidentStatus, labelKey: 'awaria.lifecycle.repaired' },
    { status: 'zamknieta' as IncidentStatus, labelKey: 'awaria.lifecycle.closed' },
  ];

  private readonly STATUS_ORDER: IncidentStatus[] = ['zgloszona', 'w_trakcie_naprawy', 'naprawiona', 'zamknieta'];

  constructor() {
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.load(Number(id));
      }
    });
  }

  load(id: number): void {
    this.loading.set(true);
    this.incidentsService.get(id).subscribe({
      next: (inc) => {
        this.incident.set(inc);
        this.comments.set(inc.comments ?? []);
        this.history.set(inc.status_history ?? []);
        this.newStatus.set(inc.status);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  changeStatus(): void {
    const inc = this.incident();
    if (!inc) return;
    const status = this.newStatus();
    if (status === inc.status) return;

    this.statusSaving.set(true);
    this.incidentsService.changeStatus(inc.id, { status }).subscribe({
      next: () => {
        this.statusSaving.set(false);
        this.toastService.success(this.t('awaria.messages.status_changed.success'));
        this.load(inc.id);
      },
      error: (err) => {
        this.statusSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  sendComment(): void {
    const inc = this.incident();
    if (!inc) return;
    const tresc = this.newComment().trim();
    if (!tresc) {
      this.toastService.error(this.t('awaria.messages.comment_required'));
      return;
    }

    this.commentSaving.set(true);
    this.incidentsService.addComment(inc.id, { tresc }).subscribe({
      next: () => {
        this.commentSaving.set(false);
        this.newComment.set('');
        this.toastService.success(this.t('awaria.messages.comment_added.success'));
        this.load(inc.id);
      },
      error: (err) => {
        this.commentSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  /** Skrót: ustawia status (np. „naprawiona" / „zamknieta") przez changeStatus(). */
  setStatus(status: IncidentStatus): void {
    const inc = this.incident();
    if (!inc || inc.status === status) {
      return;
    }
    this.newStatus.set(status);
    this.changeStatus();
  }

  back(): void {
    this.router.navigate(['/incidents']);
  }

  // --- Pomocnicze ---

  typeLabel(inc: Incident): string {
    return this.t(inc.typ === 'sprzet' ? 'awaria.list.type_equipment' : 'awaria.list.type_other');
  }

  statusBadgeStatus(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'reported',
      w_trakcie_naprawy: 'under_repair',
      naprawiona: 'repaired',
      zamknieta: 'closed',
    };
    return map[status];
  }

  statusBadgeKey(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'awaria.lifecycle.reported',
      w_trakcie_naprawy: 'awaria.lifecycle.under_repair',
      naprawiona: 'awaria.lifecycle.repaired',
      zamknieta: 'awaria.lifecycle.closed',
    };
    return map[status];
  }

  downtime(inc: Incident): string {
    if (!inc.data_zgloszenia) return '—';
    const start = new Date(inc.data_zgloszenia).getTime();
    const end = inc.data_zakonczenia ? new Date(inc.data_zakonczenia).getTime() : Date.now();
    const hours = Math.max(0, Math.round((end - start) / 3600000));
    return `${hours} h`;
  }

  lifecycleIndex(status: IncidentStatus): number {
    return this.STATUS_ORDER.indexOf(status);
  }

  lifecycleIcon(status: IncidentStatus): string {
    const map: Record<IncidentStatus, string> = {
      zgloszona: 'check',
      w_trakcie_naprawy: 'wrench',
      naprawiona: 'check',
      zamknieta: 'close',
    };
    return map[status];
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}
