import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

export interface TimelineEvent {
  id: string | number;
  type: string;
  description: string;
  date: string;
  icon?: string;
}

/**
 * Komponent osi czasu (historia sprzętu).
 */
@Component({
  selector: 'app-timeline',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white rounded-lg shadow p-5 dark:bg-slate-800/60 dark:ring-1 dark:ring-slate-800">
      @if (title) {
        <h3 class="text-base font-semibold text-gray-900 mb-4 dark:text-white">{{ title | translate }}</h3>
      }
      @if (events.length === 0) {
        <p class="text-sm text-gray-400 dark:text-slate-500">{{ 'common.messages.info.no_results' | translate }}</p>
      } @else {
        <ol class="relative border-l-2 border-gray-200 ml-3 space-y-4 dark:border-slate-700">
          @for (event of events; track event.id) {
            <li class="ml-6">
              <span
                class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white"
                [class.bg-blue-500]="event.type === 'przypisanie'"
                [class.bg-green-500]="event.type === 'serwis'"
                [class.bg-red-500]="event.type === 'awaria'"
                [class.bg-gray-500]="event.type === 'inne' || event.type === 'przebieg'"
              >
                {{ event.icon || event.type.charAt(0).toUpperCase() }}
              </span>
              <div class="bg-gray-50 rounded-md p-3 dark:bg-slate-800">
                <div class="text-sm font-medium text-gray-900 dark:text-slate-200">{{ event.description }}</div>
                <div class="text-xs text-gray-500 mt-1 dark:text-slate-400">{{ event.date }}</div>
              </div>
            </li>
          }
        </ol>
      }
    </div>
  `,
})
export class TimelineComponent {
  @Input() title = '';
  @Input() events: TimelineEvent[] = [];
}