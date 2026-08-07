import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

export type AlertSeverity = 'danger' | 'warning' | 'info';

export interface AlertItem {
  id: string | number;
  title: string;
  description?: string;
  severity: AlertSeverity;
  date?: string;
}

/**
 * Widget alertów dla dashboardu.
 */
@Component({
  selector: 'app-alert-widget',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white rounded-lg shadow p-5">
      @if (title) {
        <h3 class="text-base font-semibold text-gray-900 mb-3">{{ title | translate }}</h3>
      }
      @if (alerts.length === 0) {
        <p class="text-sm text-gray-400">{{ 'common.messages.info.no_results' | translate }}</p>
      } @else {
        <ul class="space-y-3">
          @for (alert of alerts; track alert.id) {
            <li
              class="flex items-start gap-3 p-3 rounded-md border-l-4"
              [class.border-red-500]="alert.severity === 'danger'"
              [class.bg-red-50]="alert.severity === 'danger'"
              [class.border-yellow-500]="alert.severity === 'warning'"
              [class.bg-yellow-50]="alert.severity === 'warning'"
              [class.border-blue-500]="alert.severity === 'info'"
              [class.bg-blue-50]="alert.severity === 'info'"
            >
              <span class="flex-1">
                <span class="block text-sm font-medium text-gray-900">{{ alert.title }}</span>
                @if (alert.description) {
                  <span class="block text-xs text-gray-600 mt-1">{{ alert.description }}</span>
                }
                @if (alert.date) {
                  <span class="block text-xs text-gray-400 mt-1">{{ alert.date }}</span>
                }
              </span>
              @if (actionLabel) {
                <button
                  type="button"
                  class="text-xs font-medium text-pbs-primary hover:underline"
                  (click)="onAction(alert)"
                >
                  {{ actionLabel | translate }}
                </button>
              }
            </li>
          }
        </ul>
      }
    </div>
  `,
})
export class AlertWidgetComponent {
  @Input() title = '';
  @Input() alerts: AlertItem[] = [];
  @Input() actionLabel = '';

  @Output() action = new EventEmitter<AlertItem>();

  onAction(alert: AlertItem): void {
    this.action.emit(alert);
  }
}