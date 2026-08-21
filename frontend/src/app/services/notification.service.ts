import { Injectable, inject, signal, computed } from '@angular/core';

import { DashboardService } from './dashboard.service';
import { DashboardAlerts, DashboardActivityItem } from '../models/dashboard.model';

/**
 * Serwis powiadomień (dzwonek w app-barze) — agreguje alerty i ostatnią aktywność
 * z endpointów dashboardu (`/dashboard/alerts`, `/dashboard/charts`).
 *
 * - `alerts` — grupy alertów (certyfikaty, przeglądy, awarie, powroty z urlopów),
 * - `activity` — ostatnia aktywność systemowa (zlecenia, awarie, faktury, pracownicy),
 * - `totalCount` — suma liczników grup alertów (do badge'a na dzwonku).
 *
 * Dane są read-only i cache'owane na backendzie (TTL 60 s), więc ładowane raz
 * przy starcie aplikacji (komponent dzwonka wywołuje `load()` w konstruktorze).
 */
@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly dashboard = inject(DashboardService);

  private readonly _alerts = signal<DashboardAlerts | null>(null);
  private readonly _activity = signal<DashboardActivityItem[]>([]);
  private readonly _loading = signal<boolean>(true);

  readonly alerts = this._alerts.asReadonly();
  readonly activity = this._activity.asReadonly();
  readonly loading = this._loading.asReadonly();

  /** Oczekujące otwarcie certyfikatu z dzwonka (głęboki link do pracownika). */
  private readonly _pendingDocument = signal<{ employeeId: number; docId: number } | null>(null);
  readonly pendingDocument = this._pendingDocument.asReadonly();

  /**
   * Zgłasza intencję otwarcia konkretnego certyfikatu pracownika (z dzwonka).
   * `EmployeesComponent` nasłuchuje przez `effect` i otwiera modal, po czym czyści.
   */
  openDocument(employeeId: number, docId: number): void {
    this._pendingDocument.set({ employeeId, docId });
  }

  clearPendingDocument(): void {
    this._pendingDocument.set(null);
  }

  /** Łączna liczba alertów (suma liczników grup) — do badge'a na dzwonku. */
  readonly totalCount = computed(() => {
    const a = this._alerts();
    if (!a) {
      return 0;
    }
    return (
      (a.expiring_certs?.count ?? 0) +
      (a.upcoming_inspections?.count ?? 0) +
      (a.unresolved_incidents?.count ?? 0) +
      (a.returning_from_leave?.count ?? 0)
    );
  });

  /** Pobiera alerty i aktywność z API dashboardu. */
  load(): void {
    this._loading.set(true);

    this.dashboard.alerts().subscribe({
      next: (alerts) => {
        this._alerts.set(alerts);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
      },
    });

    this.dashboard.charts().subscribe({
      next: (charts) => {
        this._activity.set(charts?.activity ?? []);
      },
      error: () => {
        // Aktywność jest informacyjna — błąd nie blokuje alertów.
      },
    });
  }
}
