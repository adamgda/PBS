import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

/**
 * Karta KPI dla dashboardu.
 */
@Component({
  selector: 'app-kpi-card',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white rounded-lg shadow p-5 flex flex-col gap-2">
      <div class="text-sm text-gray-500">{{ label | translate }}</div>
      <div class="text-3xl font-bold text-gray-900">{{ value }}</div>
      @if (subtitle) {
        <div class="text-xs text-gray-400">{{ subtitle }}</div>
      }
      @if (trend !== null) {
        <div
          class="text-xs font-medium flex items-center gap-1"
          [class.text-green-600]="trend! >= 0"
          [class.text-red-600]="trend! < 0"
        >
          <span>{{ trend! >= 0 ? '▲' : '▼' }}</span>
          <span>{{ trend! >= 0 ? '+' : '' }}{{ trend }}%</span>
        </div>
      }
    </div>
  `,
})
export class KpiCardComponent {
  @Input({ required: true }) label = '';
  @Input({ required: true }) value: string | number = '';
  @Input() subtitle = '';
  @Input() trend: number | null = null;
}