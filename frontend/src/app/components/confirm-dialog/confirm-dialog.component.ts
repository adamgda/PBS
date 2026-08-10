import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ConfirmService } from '../../services/confirm.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { ButtonComponent } from '../button/button.component';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

/**
 * Komponent dialogu potwierdzenia — globalny, sterowany przez ConfirmService.
 * Umieszczany w AppComponent.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule, TranslatePipe, ButtonComponent, SvgIconComponent],
  template: `
    @if (state(); as s) {
      <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
      >
        <div class="w-full max-w-md animate-scale-in rounded-xl bg-white p-6 shadow-elevated">
          <div class="mb-4 flex items-start gap-3">
            <span
              class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
              [class]="s.danger ? 'bg-red-100 text-red-600' : 'bg-pbs-navy-50 text-pbs-primary'"
            >
              <app-svg-icon name="alert" />
            </span>
            <h3 id="confirm-title" class="pt-1 text-lg font-semibold text-gray-900">
              {{ s.title || ('common.messages.warning.delete_confirm' | translate) }}
            </h3>
          </div>
          <p class="text-sm text-gray-600">{{ s.message }}</p>
          <div class="mt-6 flex justify-end gap-3">
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