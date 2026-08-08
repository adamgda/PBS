import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ConfirmService } from '../../services/confirm.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { ButtonComponent } from '../button/button.component';

/**
 * Komponent dialogu potwierdzenia — globalny, sterowany przez ConfirmService.
 * Umieszczany w AppComponent.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule, TranslatePipe, ButtonComponent],
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
            <app-button variant="secondary" (clicked)="respond(false)">{{ s.cancelText || ('common.buttons.cancel' | translate) }}</app-button>
            <app-button [variant]="s.danger ? 'danger' : 'primary'" (clicked)="respond(true)">{{ s.confirmText || ('common.buttons.confirm' | translate) }}</app-button>
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