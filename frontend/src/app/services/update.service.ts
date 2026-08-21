import { Injectable, inject } from '@angular/core';
import { SwUpdate, VersionReadyEvent } from '@angular/service-worker';
import { filter } from 'rxjs/operators';

import { environment } from '../../environments/environment';

/**
 * Serwis aktualizacji PWA.
 *
 * Po wgraniu nowego buildu na serwer nie trzeba czyścić danych przeglądarki.
 * Aplikacja sprawdza dostępność nowej wersji (przy starcie, cyklicznie oraz gdy
 * zakładka wraca na pierwszy plan) i — gdy tylko wykryje nową wersję —
 * automatycznie ją aktywuje i przeładowuje stronę. Dzięki temu wystarczy
 * jedno odświeżenie strony, żeby zobaczyć nowe zmiany (także na telefonie).
 *
 * Działa wyłącznie w produkcji (service worker jest włączony tylko tam).
 */
@Injectable({ providedIn: 'root' })
export class UpdateService {
  private readonly swUpdate = inject(SwUpdate);

  /** Jak często sprawdzać dostępność nowej wersji (ms). */
  private readonly checkIntervalMs = 30_000;

  /**
   * Uruchomienie monitorowania aktualizacji. Wołane raz przy starcie aplikacji.
   */
  init(): void {
    if (!environment.production || !this.swUpdate.isEnabled) {
      return;
    }

    // Nowa wersja pobrana w tle — natychmiast ją aktywuj i przeładuj stronę.
    this.swUpdate.versionUpdates
      .pipe(
        filter((event): event is VersionReadyEvent => event.type === 'VERSION_READY'),
      )
      .subscribe(() => {
        this.swUpdate.activateUpdate().then(() => location.reload());
      });

    // Sprawdź dostępność aktualizacji od razu przy starcie.
    this.swUpdate.checkForUpdate().catch(() => {});

    // Cykliczne sprawdzanie — jeśli aplikacja pozostaje otwarta.
    setInterval(() => {
      this.swUpdate.checkForUpdate().catch(() => {});
    }, this.checkIntervalMs);

    // Ponowne sprawdzenie, gdy zakładka wraca na pierwszy plan.
    // Kluczowe na telefonach, gdzie aplikacja bywa w tle.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        this.swUpdate.checkForUpdate().catch(() => {});
      }
    });
  }
}
