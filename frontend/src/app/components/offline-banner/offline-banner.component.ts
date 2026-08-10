import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { OfflineService } from '../../services/offline.service';
import { SvgIconComponent } from './../svg-icon/svg-icon.component';

/**
 * Wskaźnik statusu połączenia (online/offline banner).
 * Wyświetlany globalnie, gdy offline.
 */
@Component({
  selector: 'app-offline-banner',
  standalone: true,
  imports: [CommonModule, SvgIconComponent],
  template: `
    @if (!online()) {
      <div
        class="fixed inset-x-0 top-0 z-40 flex items-center justify-center gap-3 border-b border-amber-300/40 bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2.5 text-center text-sm font-medium text-white shadow-md"
        role="status"
        aria-live="polite"
      >
        <app-svg-icon name="alert" size="sm" />
        <span>Tryb offline — niektóre funkcje mogą być niedostępne. Żądania zostaną wysłane po odzyskaniu połączenia.</span>
        @if (queue().length > 0) {
          <span class="inline-flex items-center rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">
            {{ queue().length }} w kolejce
          </span>
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