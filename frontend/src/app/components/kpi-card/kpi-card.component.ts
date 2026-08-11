import { Component, Input, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  NgApexchartsModule,
  ApexChart,
  ApexAxisChartSeries,
  ApexStroke,
  ApexFill,
  ApexTooltip,
} from 'ng-apexcharts';

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

/** Kolor paska akcentu po lewej per ton. */
const TONE_ACCENT_CLASSES: Record<KpiTone, string> = {
  primary: 'bg-pbs-primary',
  info: 'bg-blue-500',
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  danger: 'bg-red-500',
};

/** Kolor linii sparkline per ton. */
const TONE_SPARK_COLORS: Record<KpiTone, string> = {
  primary: '#1e3a5f',
  info: '#3b82f6',
  success: '#22c55e',
  warning: '#f59e0b',
  danger: '#ef4444',
};

/** Kolory tekstu i tła trendu per znak (wzrost/spadek). */
const TREND_POSITIVE = 'bg-emerald-50 text-emerald-700';
const TREND_NEGATIVE = 'bg-red-50 text-red-700';

/**
 * Profesjonalna karta KPI dla dashboardu — gradientowy kafelek z ikoną,
 * etykieta, wyróżniona wartość, trend (▲/▼ z kolorem), opcjonalny podtytuł
 * oraz mini-sparkline (ApexCharts) pokazujący dynamikę w czasie.
 *
 * Użycie:
 *   <app-kpi-card label="dashboard.kpi.active_employees" value="24" icon="pracownicy"
 *                 tone="primary" [trend]="12.5" [sparkline]="[1,2,3,4]" />
 */
@Component({
  selector: 'app-kpi-card',
  standalone: true,
  imports: [CommonModule, TranslatePipe, SvgIconComponent, NgApexchartsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div
      class="group relative overflow-hidden rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-100 transition-all duration-200 hover:shadow-card-hover dark:bg-slate-900 dark:ring-slate-800"
    >
      <!-- Akcent po lewej -->
      <span class="absolute inset-y-0 left-0 w-1" [class]="accentClass()"></span>

      <!-- Subtelny akcent gradientu w rogu -->
      <div
        class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full opacity-[0.06] blur-xl transition-opacity group-hover:opacity-[0.12]"
        [class]="iconClass()"
      ></div>

      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-gray-500 dark:text-slate-400">{{ label | translate }}</p>
          <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ value }}</p>
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
          <span class="truncate text-xs text-gray-400 dark:text-slate-500">{{ subtitle | translate }}</span>
        }
      </div>

      @if (sparkline.length > 0) {
        <div class="mt-3 -mb-1">
          <apx-chart
            [chart]="sparkChart"
            [series]="sparkSeries()"
            [stroke]="sparkStroke"
            [fill]="sparkFill"
            [colors]="[sparkColor()]"
            [tooltip]="sparkTooltip"
          />
        </div>
      }
    </div>
  `,
})
export class KpiCardComponent {
  /** Klucz tłumaczeniowy etykiety karty. */
  @Input({ required: true }) label = '';
  /** Główna, wyróżniona wartość. */
  @Input({ required: true }) value: string | number = '';
  /** Opcjonalny podtytuł / opis (klucz tłumaczeniowy). */
  @Input() subtitle = '';
  /** Opcjonalny trend w procentach (ujemny → czerwony, dodatni → zielony). */
  @Input() trend: number | null = null;
  /** Nazwa ikony z SvgIconComponent (np. `pracownicy`). Puste = brak ikony. */
  @Input() icon = '';
  /** Ton kolorystyczny (gradient ikony i akcent). Domyślnie `primary`. */
  @Input() tone: KpiTone = 'primary';
  /** Opcjonalna seria liczb do mini-sparkline. Pusta = brak wykresu. */
  @Input() sparkline: number[] = [];

  readonly iconClass = computed(() => TONE_ICON_CLASSES[this.tone]);
  readonly accentClass = computed(() => TONE_ACCENT_CLASSES[this.tone]);
  readonly trendClass = computed(() => (this.trend! >= 0 ? TREND_POSITIVE : TREND_NEGATIVE));

  /** Konfiguracja mini-wykresu (sparkline). */
  readonly sparkChart: ApexChart = {
    type: 'area',
    height: 40,
    sparkline: { enabled: true },
    toolbar: { show: false },
  };
  readonly sparkStroke: ApexStroke = { curve: 'smooth', width: 2 };
  readonly sparkFill: ApexFill = {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0, stops: [0, 90, 100] },
  };
  readonly sparkTooltip: ApexTooltip = { enabled: false };

  sparkSeries(): ApexAxisChartSeries {
    return [{ name: '', data: this.sparkline }];
  }

  sparkColor(): string {
    return TONE_SPARK_COLORS[this.tone];
  }
}
