import { Component, Input, Output, EventEmitter, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

export interface CalendarEvent {
  id: string | number;
  title: string;
  date: string; // ISO date string
  color?: string;
}

export type CalendarView = 'week' | 'month' | 'day';

/**
 * Komponent kalendarza (harmonogram).
 * Obsługa widoków: tydzień, miesiąc, dzień.
 */
@Component({
  selector: 'app-calendar',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bg-white rounded-lg shadow">
      <!-- Toolbar -->
      <div class="flex items-center justify-between p-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-3 py-1 rounded border border-gray-200 text-sm hover:bg-gray-50"
            (click)="navigate(-1)"
          >←</button>
          <button
            type="button"
            class="px-3 py-1 rounded border border-gray-200 text-sm hover:bg-gray-50"
            (click)="navigate(1)"
          >→</button>
          <button
            type="button"
            class="px-3 py-1 rounded border border-gray-200 text-sm hover:bg-gray-50"
            (click)="goToday()"
          >{{ 'common.buttons.refresh' | translate }}</button>
          <span class="ml-2 font-semibold text-gray-900">{{ currentPeriodLabel() }}</span>
        </div>
        <div class="flex gap-1">
          @for (v of views; track v) {
            <button
              type="button"
              class="px-3 py-1 rounded text-sm font-medium transition-colors"
              [class.bg-pbs-primary]="view() === v"
              [class.text-white]="view() === v"
              [class.text-gray-600]="view() !== v"
              [class.hover:bg-gray-50]="view() !== v"
              (click)="changeView(v)"
            >{{ v }}</button>
          }
        </div>
      </div>

      <!-- Grid -->
      <div class="p-4">
        @switch (view()) {
          @case ('month') {
            <div class="grid grid-cols-7 gap-1">
              @for (day of dayNames; track day) {
                <div class="text-xs font-medium text-gray-500 text-center py-1">{{ day }}</div>
              }
              @for (cell of monthGrid(); track cell.date) {
                <div
                  class="min-h-20 border border-gray-100 rounded p-1 text-xs"
                  [class.bg-gray-50]="!cell.isCurrentMonth"
                  [class.bg-blue-50]="cell.isToday"
                >
                  <div class="text-gray-600">{{ cell.dayNumber }}</div>
                  @for (ev of cell.events; track ev.id) {
                    <div
                      class="mt-1 px-1 py-0.5 rounded text-white text-xs truncate"
                      [style.backgroundColor]="ev.color || '#3b82f6'"
                      (click)="onEventClick(ev)"
                    >{{ ev.title }}</div>
                  }
                </div>
              }
            </div>
          }
          @case ('week') {
            <div class="grid grid-cols-7 gap-2">
              @for (day of weekDays(); track day.date) {
                <div class="border border-gray-100 rounded p-2">
                  <div class="text-xs font-medium text-gray-600 mb-1">{{ day.label }}</div>
                  @for (ev of day.events; track ev.id) {
                    <div
                      class="mt-1 px-2 py-1 rounded text-white text-xs truncate cursor-pointer"
                      [style.backgroundColor]="ev.color || '#3b82f6'"
                      (click)="onEventClick(ev)"
                    >{{ ev.title }}</div>
                  }
                </div>
              }
            </div>
          }
          @case ('day') {
            <div class="space-y-2">
              @for (ev of dayEvents(); track ev.id) {
                <div
                  class="p-3 rounded text-white cursor-pointer"
                  [style.backgroundColor]="ev.color || '#3b82f6'"
                  (click)="onEventClick(ev)"
                >{{ ev.title }}</div>
              }
              @if (dayEvents().length === 0) {
                <p class="text-sm text-gray-400">{{ 'common.table.no_data' | translate }}</p>
              }
            </div>
          }
        }
      </div>
    </div>
  `,
})
export class CalendarComponent {
  @Input({ required: true }) set events(value: CalendarEvent[]) {
    this._events.set(value);
  }
  @Input() set view(value: CalendarView) {
    this._view.set(value);
  }

  @Output() eventClick = new EventEmitter<CalendarEvent>();
  @Output() dateChange = new EventEmitter<Date>();
  @Output() viewChange = new EventEmitter<CalendarView>();

  private readonly _events = signal<CalendarEvent[]>([]);
  private readonly _view = signal<CalendarView>('month');
  private readonly _currentDate = signal<Date>(new Date());

  readonly events = this._events.asReadonly();
  readonly view = this._view.asReadonly();

  readonly views: CalendarView[] = ['day', 'week', 'month'];

  dayNames = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Ndz'];

  readonly currentPeriodLabel = computed(() => {
    const d = this._currentDate();
    const months = ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
    return `${months[d.getMonth()]} ${d.getFullYear()}`;
  });

  readonly monthGrid = computed(() => {
    const d = this._currentDate();
    const year = d.getFullYear();
    const month = d.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startOffset = (firstDay.getDay() + 6) % 7; // Poniedziałek = 0
    const daysInMonth = lastDay.getDate();
    const cells: { date: string; dayNumber: number; isCurrentMonth: boolean; isToday: boolean; events: CalendarEvent[] }[] = [];

    for (let i = startOffset - 1; i >= 0; i--) {
      const date = new Date(year, month, -i);
      cells.push(this.createCell(date, false));
    }
    for (let i = 1; i <= daysInMonth; i++) {
      const date = new Date(year, month, i);
      cells.push(this.createCell(date, true));
    }
    while (cells.length % 7 !== 0) {
      const date = new Date(year, month + 1, cells.length - daysInMonth - startOffset + 1);
      cells.push(this.createCell(date, false));
    }
    return cells;
  });

  readonly weekDays = computed(() => {
    const d = this._currentDate();
    const start = new Date(d);
    const day = (d.getDay() + 6) % 7;
    start.setDate(d.getDate() - day);
    const days = [];
    for (let i = 0; i < 7; i++) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);
      days.push({
        date: date.toISOString().split('T')[0],
        label: `${this.dayNames[i]} ${date.getDate()}`,
        events: this.eventsForDate(date),
      });
    }
    return days;
  });

  readonly dayEvents = computed(() => this.eventsForDate(this._currentDate()));

  navigate(delta: number): void {
    const d = new Date(this._currentDate());
    if (this._view() === 'month') d.setMonth(d.getMonth() + delta);
    else if (this._view() === 'week') d.setDate(d.getDate() + delta * 7);
    else d.setDate(d.getDate() + delta);
    this._currentDate.set(d);
    this.dateChange.emit(d);
  }

  goToday(): void {
    this._currentDate.set(new Date());
    this.dateChange.emit(new Date());
  }

  changeView(v: CalendarView): void {
    this._view.set(v);
    this.viewChange.emit(v);
  }

  onEventClick(ev: CalendarEvent): void {
    this.eventClick.emit(ev);
  }

  private createCell(date: Date, isCurrentMonth: boolean) {
    const today = new Date();
    return {
      date: date.toISOString().split('T')[0],
      dayNumber: date.getDate(),
      isCurrentMonth,
      isToday: date.toDateString() === today.toDateString(),
      events: this.eventsForDate(date),
    };
  }

  private eventsForDate(date: Date): CalendarEvent[] {
    const dateStr = date.toISOString().split('T')[0];
    return this._events().filter((e) => e.date.startsWith(dateStr));
  }
}