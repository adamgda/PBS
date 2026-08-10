import { Component, Input, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';

/** Dopuszczalne tony kolorystyczne karty KPI. */
export type KpiTone = 'primary' | 'info' | 'success' | 'warning' | 'danger';

/** Gradient kafelka z ikoną per ton. */
const TONE_ICON_CLASSES: Record<KpiTone, string> = {
  primary: 'from-pbs-primary to-pbs-navy-600',
  info: 'from-blue-500 to-blue-600',
  success: 'from-emerald-500 to-emerald-600',
  warning: 'from-amber-500 to-orange-500',
  danger: 'from-red-500 to-rose-600',
};

/** Kolory tekstu i tła trendu per znak (wzrost/spadek). */
const TREND_POSITIVE = 'bg-emerald-50 text-emerald-700';
const TREND_NEGATIVE = 'bg-red-50 text-red-700';

/**
 * Profesjonalna karta KPI dla dashboardu — gradientowy kafelek z ikoną,
 * etykieta, wyróżniona wartość, trend (▲/▼ z kolorem) i opcjonalny podtytuł.
 *
 * Użycie:
 *   <app-kpi-card label="dashboard.kpi.active_employees" value="24" icon="pracownicy" tone="primary" [trend]="12.5" />
 */
@Component({
  selector: 'app-kpi-card',
  standalone: true,
  imports: [CommonModule, TranslatePipe, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div
      class="group relative overflow-hidden rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-100 transition-all duration-200 hover:shadow-card-hover"
    >
      <!-- Subtelny akcent gradientu w rogu -->
      <div
        class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full opacity-[0.06] blur-xl transition-opacity group-hover:opacity-[0.12]"
        [class]="accentGlowClass()"
      ></div>

      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-gray-500">{{ label | translate }}</p>
          <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900">{{ value }}</p>
        </div>

        @if (icon) {
          <span
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-white shadow-sm"
            [class]="iconClass()"
          >
            <app-svg-icon [name]="icon" />
          </span>
        }
      </div>

      <div class="mt-3 flex items-center gap-2">
        @if (trend !== null) {
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
            [class]="trendClass()"
          >
            <span>{{ trend! >= 0 ? '▲' : '▼' }}</span>
            <span>{{ trend! >= 0 ? '+' : '' }}{{ trend }}%</span>
          </span>
        }
        @if (subtitle) {
          <span class="truncate text-xs text-gray-400">{{ subtitle }}</span>
        }
      </div>
    </div>
  `,
})
export class KpiCardComponent {
  /** Klucz tłumaczeniowy etykiety karty. */
  @Input({ required: true }) label = '';
  /** Główna, wyróżniona wartość. */
  @Input({ required: true }) value: string | number = '';
  /** Opcjonalny podtytuł / opis. */
  @Input() subtitle = '';
  /** Opcjonalny trend w procentach (ujemny → czerwony, dodatni → zielony). */
  @Input() trend: number | null = null;
  /** Nazwa ikony z SvgIconComponent (np. `pracownicy`). Puste = brak ikony. */
  @Input() icon = '';
  /** Ton kolorystyczny (gradient ikony i akcent). Domyślnie `primary`. */
  @Input() tone: KpiTone = 'primary';

  readonly iconClass = computed(() => TONE_ICON_CLASSES[this.tone]);
  readonly accentGlowClass = computed(() => TONE_ICON_CLASSES[this.tone]);
  readonly trendClass = computed(() => (this.trend! >= 0 ? TREND_POSITIVE : TREND_NEGATIVE));
}
