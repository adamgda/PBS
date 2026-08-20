import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ToastService } from '../../services/toast.service';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

/**
 * Komponent powiadomień Toast — wyświetla komunikaty z ToastService.
 * Umieszczany w AppComponent (globalny).
 */
@Component({
  selector: 'app-toast-notification',
  standalone: true,
  imports: [CommonModule, SvgIconComponent],
  template: `
    <!-- Toasty na dole po prawej — nie zasłaniają przycisków w górnym headerze
         (przełącznik motywu), co na mobile wymuszało drugie kliknięcie. -->
    <div
      class="fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
      role="alert"
      aria-live="polite"
    >
      @for (toast of toasts(); track toast.id) {
        <div
          class="flex animate-slide-in-right items-center gap-3 rounded-xl bg-white p-3.5 shadow-elevated ring-1 ring-gray-100"
        >
          <span
            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white shadow-sm"
            [class.bg-emerald-500]="toast.type === 'success'"
            [class.bg-red-500]="toast.type === 'error'"
            [class.bg-amber-500]="toast.type === 'warning'"
            [class.bg-blue-500]="toast.type === 'info'"
          >
            <app-svg-icon [name]="toastTypeIcon(toast.type)" size="sm" />
          </span>
          <span class="flex-1 text-sm font-medium text-gray-800">{{ toast.message }}</span>
          <button
            type="button"
            class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            (click)="dismiss(toast.id)"
            aria-label="Zamknij"
          >
            <app-svg-icon name="close" size="sm" />
          </button>
        </div>
      }
    </div>
  `,
})
export class ToastNotificationComponent {
  private readonly toastService = inject(ToastService);

  readonly toasts = this.toastService.toasts;

  toastTypeIcon(type: string): string {
    switch (type) {
      case 'success':
        return 'check';
      case 'error':
        return 'alert';
      case 'warning':
        return 'alert';
      default:
        return 'alert';
    }
  }

  dismiss(id: number): void {
    this.toastService.dismiss(id);
  }
}