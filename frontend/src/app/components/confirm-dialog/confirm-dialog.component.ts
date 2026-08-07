import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ConfirmService } from '../../services/confirm.service';
import { TranslatePipe } from '../../pipes/translate.pipe';

/**
 * Komponent dialogu potwierdzenia — globalny, sterowany przez ConfirmService.
 * Umieszczany w AppComponent.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  template: `
    @if (state(); as s) {
      <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
      >
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 id="confirm-title" class="text-lg font-semibold text-gray-900 mb-2">
            {{ s.title || ('common.messages.warning.delete_confirm' | translate) }}
          </h3>
          <p class="text-sm text-gray-600 mb-6">{{ s.message }}</p>
          <div class="flex justify-end gap-3">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
              (click)="respond(false)"
            >
              {{ s.cancelText || ('common.buttons.cancel' | translate) }}
            </button>
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-white rounded-md transition-colors"
              [class.bg-red-600]="s.danger"
              [class.hover:bg-red-700]="s.danger"
              [class.bg-pbs-primary]="!s.danger"
              [class.hover:bg-blue-700]="!s.danger"
              (click)="respond(true)"
            >
              {{ s.confirmText || ('common.buttons.confirm' | translate) }}
            </button>
          </div>
        </div>
      </div>
    }
  `,
})
export class ConfirmDialogComponent {
  private readonly confirmService = inject(ConfirmService);

  readonly state = this.confirmService.state;

  respond(value: boolean): void {
    this.confirmService.respond(value);
  }
}