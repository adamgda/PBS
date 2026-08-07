import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { OfflineService } from '../../services/offline.service';

/**
 * Wskaźnik statusu połączenia (online/offline banner).
 * Wyświetlany globalnie, gdy offline.
 */
@Component({
  selector: 'app-offline-banner',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (!online()) {
      <div
        class="fixed top-0 left-0 right-0 z-40 bg-yellow-500 text-white text-center text-sm py-2 px-4 shadow"
        role="status"
        aria-live="polite"
      >
        Tryb offline — niektóre funkcje mogą być niedostępne. Żądania zostaną wysłane po odzyskaniu połączenia.
        @if (queue().length > 0) {
          <span class="ml-2 font-semibold">({{ queue().length }} w kolejce)</span>
        }
      </div>
    }
  `,
})
export class OfflineBannerComponent {
  private readonly offlineService = inject(OfflineService);

  readonly online = this.offlineService.online;
  readonly queue = this.offlineService.queue;
}