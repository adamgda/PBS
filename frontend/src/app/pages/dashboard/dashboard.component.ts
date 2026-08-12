import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import {
  NgApexchartsModule,
  ApexChart,
  ApexXAxis,
  ApexYAxis,
  ApexStroke,
  ApexFill,
  ApexGrid,
  ApexDataLabels,
  ApexTooltip,
  ApexLegend,
  ApexPlotOptions,
  ApexResponsive,
  ApexAxisChartSeries,
  ApexNonAxisChartSeries,
} from 'ng-apexcharts';

import { AuthService } from '../../services/auth.service';
import { DashboardService } from '../../services/dashboard.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { KpiCardComponent, KpiTone } from '../../components/kpi-card/kpi-card.component';
import { DashboardSummary, DashboardAlerts, DashboardCharts } from '../../models/dashboard.model';

interface KpiDatum {
  label: string;
  value: string | number;
  icon: string;
  tone: KpiTone;
  subtitle?: string;
}

interface HeroStat {
  key: string;
  value: string | number;
  icon: string;
}

interface AlertDatum {
  key: string;
  count: number;
  tone: 'warning' | 'info' | 'danger' | 'success' | 'neutral';
}

interface ShortcutDatum {
  key: string;
  icon: string;
  route: string;
}

interface ActivityDatum {
  titleKey: string;
  title: string;
  time: string;
  badge: string;
}

interface AreaChartConfig {
  chart: ApexChart;
  series: ApexAxisChartSeries;
  xaxis: ApexXAxis;
  yaxis: ApexYAxis;
  stroke: ApexStroke;
  fill: ApexFill;
  grid: ApexGrid;
  dataLabels: ApexDataLabels;
  tooltip: ApexTooltip;
  legend: ApexLegend;
  colors: string[];
}

interface DonutChartConfig {
  chart: ApexChart;
  series: ApexNonAxisChartSeries;
  labels: string[];
  colors: string[];
  legend: ApexLegend;
  plotOptions: ApexPlotOptions;
  dataLabels: ApexDataLabels;
  stroke: ApexStroke;
  tooltip: ApexTooltip;
  responsive: ApexResponsive[];
}

interface BarChartConfig {
  chart: ApexChart;
  series: ApexAxisChartSeries;
  xaxis: ApexXAxis;
  yaxis: ApexYAxis;
  plotOptions: ApexPlotOptions;
  colors: string[];
  fill: ApexFill;
  grid: ApexGrid;
  dataLabels: ApexDataLabels;
  tooltip: ApexTooltip;
  legend: ApexLegend;
}

/** Mapuje typ aktywności z API na klucz tłumaczenia i kolor kropki na osi czasu. */
const ACTIVITY_META: Record<string, { label: string; badge: string }> = {
  order: { label: 'dashboard.activity.item_order_created', badge: 'bg-blue-500' },
  incident: { label: 'dashboard.activity.item_incident_reported', badge: 'bg-red-500' },
  invoice: { label: 'dashboard.activity.item_invoice_paid', badge: 'bg-emerald-500' },
  employee: { label: 'dashboard.activity.item_new_employee', badge: 'bg-amber-500' },
  other: { label: 'dashboard.activity.item_other', badge: 'bg-gray-400' },
};

/**
 * Dashboard (Etap 13) — profesjonalny widok startowy po zalogowaniu.
 * Zawiera karty KPI, interaktywne wykresy (ApexCharts), alerty,
 * oś czasu aktywności i szybkie akcje.
 *
 * Wszystkie dane (KPI, alerty, wykresy, aktywność) pobierane są z API:
 * `/api/v1/dashboard/summary`, `/api/v1/dashboard/alerts` oraz
 * `/api/v1/dashboard/charts`. Brak danych statycznych/mocków.
 */
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    DatePipe,
    TranslatePipe,
    SvgIconComponent,
    KpiCardComponent,
    NgApexchartsModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly dashboardService = inject(DashboardService);
  private readonly router = inject(Router);

  /** Data wyświetlana w nagłówku. */
  readonly today = new Date();

  readonly loading = signal<boolean>(true);
  readonly error = signal<string>('');

  private readonly _summary = signal<DashboardSummary | null>(null);
  private readonly _alerts = signal<DashboardAlerts | null>(null);
  private readonly _charts = signal<DashboardCharts | null>(null);

  readonly summary = this._summary.asReadonly();
  readonly alertsData = this._alerts.asReadonly();
  readonly charts = this._charts.asReadonly();

  constructor() {
    this.load();
  }

  userName(): string {
    const email = this.authService.currentUser?.email ?? '';
    const name = email.split('@')[0] ?? '';
    return (name.charAt(0).toUpperCase() + name.slice(1)) || 'Użytkowniku';
  }

  go(route: string): void {
    this.router.navigate([route]);
  }

  areaTrend(): number {
    return this._charts()?.orders_trend.trend_pct ?? 0;
  }

  kpis(): KpiDatum[] {
    const s = this._summary();
    return [
      {
        label: 'dashboard.kpi.active_employees',
        value: s?.active_employees ?? 0,
        icon: 'pracownicy',
        tone: 'primary',
        subtitle: 'dashboard.kpi.active_employees_sub',
      },
      {
        label: 'dashboard.kpi.active_terminals',
        value: s?.active_terminals ?? 0,
        icon: 'terminale',
        tone: 'info',
        subtitle: 'dashboard.kpi.active_terminals_sub',
      },
      {
        label: 'dashboard.kpi.vehicles_in_use',
        value: s?.vehicles_in_use ?? 0,
        icon: 'sprzet',
        tone: 'success',
        subtitle: 'dashboard.kpi.vehicles_in_use_sub',
      },
      {
        label: 'dashboard.kpi.active_incidents',
        value: s?.active_incidents ?? 0,
        icon: 'awaria',
        tone: 'danger',
        subtitle: 'dashboard.kpi.active_incidents_sub',
      },
    ];
  }

  /** Szybkie statystyki dnia wyświetlane w hero. */
  heroStats(): HeroStat[] {
    const s = this._summary();
    return [
      { key: 'dashboard.hero.hours_today', value: s?.hours_today ?? 0, icon: 'history' },
      { key: 'dashboard.hero.employees_on_leave', value: s?.employees_on_leave ?? 0, icon: 'pracownicy' },
      { key: 'dashboard.hero.monthly_wages', value: this.formatMoney(s?.monthly_wages ?? 0), icon: 'money' },
    ];
  }

  private formatMoney(value: number): string {
    return new Intl.NumberFormat('pl-PL', {
      style: 'currency',
      currency: 'PLN',
      maximumFractionDigits: 0,
    }).format(value);
  }

  alerts(): AlertDatum[] {
    const a = this._alerts();
    return [
      { key: 'dashboard.alerts.expiring_certs', count: a?.expiring_certs.count ?? 0, tone: 'warning' },
      { key: 'dashboard.alerts.upcoming_inspections', count: a?.upcoming_inspections.count ?? 0, tone: 'info' },
      { key: 'dashboard.alerts.unresolved_incidents', count: a?.unresolved_incidents.count ?? 0, tone: 'danger' },
      { key: 'dashboard.alerts.returning_from_leave', count: a?.returning_from_leave.count ?? 0, tone: 'success' },
    ];
  }

  shortcuts(): ShortcutDatum[] {
    return [
      { key: 'dashboard.shortcuts.report_incident', icon: 'awaria', route: '/awaria' },
      { key: 'dashboard.shortcuts.create_report', icon: 'reporting', route: '/reporting' },
      { key: 'dashboard.shortcuts.add_order', icon: 'harmonogram', route: '/harmonogram' },
    ];
  }

  activity(): ActivityDatum[] {
    const items = this._charts()?.activity ?? [];
    return items.slice(0, 5).map((item) => {
      const meta = ACTIVITY_META[item.type] ?? ACTIVITY_META['other'];
      return {
        titleKey: meta.label,
        title: item.title,
        time: this.relativeTime(item.time),
        badge: meta.badge,
      };
    });
  }

  private relativeTime(value: string | null): string {
    if (!value) {
      return '';
    }
    const ts = new Date(value.includes('T') ? value : value.replace(' ', 'T')).getTime();
    if (Number.isNaN(ts)) {
      return '';
    }
    const diff = Math.max(0, Date.now() - ts);
    const mins = Math.floor(diff / 60000);
    if (mins < 1) {
      return 'przed chwilą';
    }
    if (mins < 60) {
      return `${mins} min temu`;
    }
    const hours = Math.floor(mins / 60);
    if (hours < 24) {
      return `${hours} godz. temu`;
    }
    return `${Math.floor(hours / 24)} dni temu`;
  }

  alertBadgeClass(tone: AlertDatum['tone']): string {
    switch (tone) {
      case 'danger':
        return 'bg-red-500';
      case 'warning':
        return 'bg-amber-500';
      case 'success':
        return 'bg-emerald-500';
      case 'info':
        return 'bg-blue-500';
      default:
        return 'bg-gray-400';
    }
  }

  private load(): void {
    this.loading.set(true);
    this.error.set('');

    this.dashboardService.summary().subscribe({
      next: (summary) => this._summary.set(summary),
      error: () => this.error.set('dashboard.error'),
    });

    this.dashboardService.alerts().subscribe({
      next: (alerts) => this._alerts.set(alerts),
      error: () => this.error.set('dashboard.error'),
    });

    this.dashboardService.charts().subscribe({
      next: (charts) => this._charts.set(charts),
      error: () => this.error.set('dashboard.error'),
    });

    // Żądania są niezależne — kończymy ładowanie po ich wystartowaniu.
    this.loading.set(false);
  }

  // ===== Wykres: operacje (area) — realne dane z `/dashboard/charts` =====
  readonly area = computed<AreaChartConfig>(() => {
    const trend = this._charts()?.orders_trend;
    return {
      chart: {
        type: 'area',
        height: 300,
        fontFamily: 'Inter, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        parentHeightOffset: 0,
      },
      series: [{ name: 'Zlecenia', data: trend?.series ?? [] }],
      xaxis: {
        categories: trend?.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
      },
      yaxis: {
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
      },
      stroke: { curve: 'smooth', width: 2.5 },
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03, stops: [0, 90, 100] },
      },
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } },
      dataLabels: { enabled: false },
      tooltip: { theme: 'light', marker: { show: true } },
      legend: { show: false },
      colors: ['#1e3a5f'],
    };
  });

  // ===== Wykres: struktura floty (donut) — realne dane z `/dashboard/charts` =====
  readonly donut = computed<DonutChartConfig>(() => {
    const fleet = this._charts()?.fleet_structure;
    return {
      chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' },
      series: fleet?.series ?? [],
      labels: fleet?.labels ?? [],
      colors: ['#f43f5e', '#3b82f6', '#f59e0b', '#22c55e'],
      legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: '#475569' } },
      plotOptions: {
        pie: {
          donut: {
            size: '74%',
            labels: {
              show: true,
              name: { show: false },
              value: { show: true, fontSize: '24px', fontWeight: '700', color: '#172d49' },
              total: { show: true, label: 'Szt.', color: '#64748b', fontSize: '12px' },
            },
          },
        },
      },
      dataLabels: { enabled: false },
      stroke: { width: 3, colors: ['#ffffff'] },
      tooltip: { theme: 'light' },
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }],
    };
  });

  // ===== Wykres: obrót per terminal (bar) — realne dane z `/dashboard/charts` =====
  readonly bar = computed<BarChartConfig>(() => {
    const turnover = this._charts()?.terminal_turnover;
    return {
      chart: {
        type: 'bar',
        height: 300,
        fontFamily: 'Inter, sans-serif',
        toolbar: { show: false },
        parentHeightOffset: 0,
      },
      series: [{ name: 'Wartość (PLN)', data: turnover?.series ?? [] }],
      xaxis: {
        categories: turnover?.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
      },
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } },
      plotOptions: {
        bar: { borderRadius: 8, columnWidth: '55%', distributed: false },
      },
      colors: ['#1e3a5f'],
      fill: {
        type: 'gradient',
        gradient: { shade: 'dark', type: 'vertical', shadeIntensity: 0.4, gradientToColors: ['#3b82f6'], opacityFrom: 1, opacityTo: 0.9, stops: [0, 100] },
      },
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } },
      dataLabels: { enabled: false },
      tooltip: { theme: 'light' },
      legend: { show: false },
    };
  });

}
