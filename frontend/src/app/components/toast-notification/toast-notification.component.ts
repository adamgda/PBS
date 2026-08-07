import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ToastService } from '../../services/toast.service';

/**
 * Komponent powiadomień Toast — wyświetla komunikaty z ToastService.
 * Umieszczany w AppComponent (globalny).
 */
@Component({
  selector: 'app-toast-notification',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div
      class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full"
      role="alert"
      aria-live="polite"
    >
      @for (toast of toasts(); track toast.id) {
        <div
          class="flex items-start gap-3 p-4 rounded-lg shadow-lg border-l-4 transition-all"
          [class.bg-green-50]="toast.type === 'success'"
          [class.text-green-800]="toast.type === 'success'"
          [class.border-green-500]="toast.type === 'success'"
          [class.bg-red-50]="toast.type === 'error'"
          [class.text-red-800]="toast.type === 'error'"
          [class.border-red-500]="toast.type === 'error'"
          [class.bg-yellow-50]="toast.type === 'warning'"
          [class.text-yellow-800]="toast.type === 'warning'"
          [class.border-yellow-500]="toast.type === 'warning'"
          [class.bg-blue-50]="toast.type === 'info'"
          [class.text-blue-800]="toast.type === 'info'"
          [class.border-blue-500]="toast.type === 'info'"
        >
          <span class="flex-1 text-sm font-medium">{{ toast.message }}</span>
          <button
            type="button"
            class="text-current opacity-60 hover:opacity-100 transition-opacity"
            (click)="dismiss(toast.id)"
            aria-label="Zamknij"
          >
            ✕
          </button>
        </div>
      }
    </div>
  `,
})
export class ToastNotificationComponent {
  private readonly toastService = inject(ToastService);

  readonly toasts = this.toastService.toasts;

  dismiss(id: number): void {
    this.toastService.dismiss(id);
  }
}