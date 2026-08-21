import { Component, inject, signal, ChangeDetectionStrategy, HostListener, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

import { NotificationService } from '../../services/notification.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

type AlertTone = 'warning' | 'info' | 'danger' | 'success' | 'neutral';

/** Pojedyncza, konkretna pozycja w grupie alertów (np. jeden wygasający certyfikat). */
interface AlertDetailItem {
  id: number;
  title: string;
  meta: string;
  date: string;
  urgency: string;
  tone: AlertTone;
  route: string;
  /** Identyfikator pracownika (dla certyfikatów) — do głębokiego linku przez serwis. */
  employeeId?: number;
}

/** Grupa alertów — nagłówek + liczba + lista szczegółowych pozycji z odnośnikami. */
interface AlertGroupDatum {
  key: string;
  icon: string;
  tone: AlertTone;
  count: number;
  route: string;
  items: AlertDetailItem[];
}

interface ActivityDatum {
  title: string;
  time: string;
  badge: string;
}

/** Mapuje typ aktywności z API na kolor kropki na liście. */
const ACTIVITY_META: Record<string, { badge: string }> = {
  order: { badge: 'bg-blue-500' },
  incident: { badge: 'bg-red-500' },
  invoice: { badge: 'bg-emerald-500' },
  employee: { badge: 'bg-amber-500' },
  other: { badge: 'bg-gray-400' },
};

/** Czytelne etykiety statusów awarii (sekcja jest single-language PL). */
const INCIDENT_STATUS_LABELS: Record<string, string> = {
  zgloszona: 'Zgłoszona',
  w_trakcie_naprawy: 'W trakcie naprawy',
  naprawiona: 'Naprawiona',
};

/**
 * Globalny dzwonek powiadomień (app-bar) — agreguje alerty i ostatnią aktywność
 * z dashboardu. Dostępny z każdej podstrony i w wersji mobilnej (dropdown
 * prawoskrętny, z backdropem na mobile). Dane z `NotificationService`.
 */
@Component({
  selector: 'app-notification-bell',
  standalone: true,
  imports: [CommonModule, RouterLink, TranslatePipe, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './notification-bell.component.html',
})
export class NotificationBellComponent {
  private readonly notifications = inject(NotificationService);
  private readonly elementRef = inject(ElementRef);

  readonly alerts = this.notifications.alerts;
  readonly activity = this.notifications.activity;
  readonly loading = this.notifications.loading;
  readonly totalCount = this.notifications.totalCount;

  /** Czy dropdown jest otwarty. */
  readonly open = signal(false);

  constructor() {
    this.notifications.load();
  }

  toggle(): void {
    this.open.update((v) => !v);
  }

  close(): void {
    this.open.set(false);
  }

  /**
   * Kliknięcie pozycji alertu. Dla certyfikatów zgłasza intencję otwarcia
   * konkretnego certyfikatu pracownika (przez NotificationService), a nawigacja
   * do /employees odbywa się przez routerLink.
   */
  onAlertClick(item: AlertDetailItem): void {
    if (item.employeeId) {
      this.notifications.openDocument(item.employeeId, item.id);
    }
  }

  /** Obsługa klawiatury w panelu: Esc zamyka. */
  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.close();
    }
  }

  /** Kliknięcie poza komponentem (przycisk + panel) zamyka okno powiadomień. */
  @HostListener('document:click', ['$event'])
  onDocumentClick(event: Event): void {
    if (!this.open()) {
      return;
    }
    const target = event.target as Node | null;
    if (target && this.elementRef.nativeElement.contains(target)) {
      return;
    }
    this.close();
  }

  /** Grupy alertów — każda pozycja to konkretny rekord z datą i odnośnikiem. */
  groups(): AlertGroupDatum[] {
    const a = this.alerts();
    if (!a) {
      return [];
    }

    const certs: AlertDetailItem[] = (a.expiring_certs?.items ?? []).map((it) => {
      const empId = it['employee_id'];
      const docId = it['id'];
      return {
        id: Number(it['id']),
        title: String(it['nazwa'] ?? '—'),
        meta: this.fullName(it['imie'], it['nazwisko']),
        date: this.formatDate(it['data_waznosci']),
        urgency: this.daysUntil(it['data_waznosci']),
        tone: 'warning',
        route: '/employees',
        // Głęboki link: kliknięcie zgłasza intencję przez NotificationService,
        // a strona pracowników otwiera modal konkretnego certyfikatu.
        employeeId: empId ? Number(empId) : undefined,
      };
    });

    const inspections: AlertDetailItem[] = (a.upcoming_inspections?.items ?? []).map((it) => {
      const sprzet = String(it['sprzet_nazwa'] ?? '');
      const serial = it['numer_seryjny'] ? ` · ${String(it['numer_seryjny'])}` : '';
      return {
        id: Number(it['id']),
        title: String(it['typ_przegladu'] ?? '—'),
        meta: (sprzet + serial).trim() || '—',
        date: this.formatDate(it['data_nastepnego_planowanego']),
        urgency: this.daysUntil(it['data_nastepnego_planowanego']),
        tone: 'info',
        route: '/equipment',
      };
    });

    const incidents: AlertDetailItem[] = (a.unresolved_incidents?.items ?? []).map((it) => {
      const id = Number(it['id']);
      return {
        id,
        title: String(it['opis'] ?? 'Awarie'),
        meta: INCIDENT_STATUS_LABELS[String(it['status'] ?? '')] ?? String(it['status'] ?? ''),
        date: this.formatDate(it['data_zgloszenia']),
        urgency: '',
        tone: 'danger',
        route: id ? `/incidents/${id}` : '/incidents',
      };
    });

    const returns: AlertDetailItem[] = (a.returning_from_leave?.items ?? []).map((it) => ({
      id: Number(it['id']),
      title: this.fullName(it['imie'], it['nazwisko']) || '—',
      meta: 'Powrót z urlopu',
      date: this.formatDate(it['data_do']),
      urgency: this.daysUntil(it['data_do']),
      tone: 'success',
      route: '/employees',
    }));

    return [
      {
        key: 'powiadomienia.groups.expiring_certs',
        icon: 'document',
        tone: 'warning',
        count: a.expiring_certs?.count ?? 0,
        route: '/employees',
        items: certs,
      },
      {
        key: 'powiadomienia.groups.upcoming_inspections',
        icon: 'wrench',
        tone: 'info',
        count: a.upcoming_inspections?.count ?? 0,
        route: '/equipment',
        items: inspections,
      },
      {
        key: 'powiadomienia.groups.unresolved_incidents',
        icon: 'awaria',
        tone: 'danger',
        count: a.unresolved_incidents?.count ?? 0,
        route: '/incidents',
        items: incidents,
      },
      {
        key: 'powiadomienia.groups.returning_from_leave',
        icon: 'pracownicy',
        tone: 'success',
        count: a.returning_from_leave?.count ?? 0,
        route: '/employees',
        items: returns,
      },
    ];
  }

  /** Ostatnia aktywność (do 5 pozycji) z relatywnym czasem. */
  activityItems(): ActivityDatum[] {
    return this.activity()
      .slice(0, 5)
      .map((item) => {
        const meta = ACTIVITY_META[item.type] ?? ACTIVITY_META['other'];
        return { title: item.title, time: this.relativeTime(item.time), badge: meta.badge };
      });
  }

  badgeClass(tone: AlertTone): string {
    switch (tone) {
      case 'danger':
        return 'bg-red-500';
      case 'warning':
        return 'bg-amber-500';
      case 'success':
        return 'bg-emerald-500';
      case 'info':
        return 'bg-blue-500';
      default:
        return 'bg-gray-400';
    }
  }

  private fullName(first: string | number | null, last: string | number | null): string {
    return `${first ?? ''} ${last ?? ''}`.trim();
  }

  private formatDate(value: string | number | null): string {
    const ts = this.toTimestamp(value);
    if (!ts) {
      return '';
    }
    return new Date(ts).toLocaleDateString('pl-PL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  private daysUntil(value: string | number | null): string {
    const ts = this.toTimestamp(value);
    if (!ts) {
      return '';
    }
    const diff = Math.ceil((ts - Date.now()) / 86400000);
    if (diff <= 0) {
      return 'dziś';
    }
    if (diff === 1) {
      return 'jutro';
    }
    return `za ${diff} dni`;
  }

  private toTimestamp(value: string | number | null): number {
    if (value === null || value === undefined || value === '') {
      return 0;
    }
    const str = String(value);
    const ts = new Date(str.includes('T') ? str : str.replace(' ', 'T')).getTime();
    return Number.isNaN(ts) ? 0 : ts;
  }

  private relativeTime(value: string | null): string {
    if (!value) {
      return '';
    }
    const ts = new Date(value.includes('T') ? value : value.replace(' ', 'T')).getTime();
    if (Number.isNaN(ts)) {
      return '';
    }
    const diff = Math.max(0, Date.now() - ts);
    const mins = Math.floor(diff / 60000);
    if (mins < 1) {
      return 'przed chwilą';
    }
    if (mins < 60) {
      return `${mins} min temu`;
    }
    const hours = Math.floor(mins / 60);
    if (hours < 24) {
      return `${hours} godz. temu`;
    }
    return `${Math.floor(hours / 24)} dni temu`;
  }
}

