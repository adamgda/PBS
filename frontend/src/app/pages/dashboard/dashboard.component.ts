import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
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
import { DashboardSummary, DashboardAlerts } from '../../models/dashboard.model';

interface KpiDatum {
  label: string;
  value: string | number;
  icon: string;
  tone: KpiTone;
  trend: number | null;
  subtitle?: string;
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
  time: string;
  badge: string;
}

/**
 * Dashboard (Etap 13) — profesjonalny widok startowy po zalogowaniu.
 * Zawiera karty KPI, interaktywne wykresy (ApexCharts), alerty,
 * oś czasu aktywności i szybkie akcje.
 *
 * Karty KPI i alerty pobierane są z API (`/api/v1/dashboard/summary` oraz
 * `/api/v1/dashboard/alerts`). Wykresy pozostają reprezentacyjne (mock).
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

  readonly summary = this._summary.asReadonly();
  readonly alertsData = this._alerts.asReadonly();

  constructor() {
    this.load();
  }

  userName(): string {
    const email = this.authService.currentUser?.email ?? '';
    const name = email.split('@')[0] ?? '';
    return (name.charAt(0) + name.slice(1)) || 'Użytkowniku';
  }

  go(route: string): void {
    this.router.navigate([route]);
  }

  areaTrend(): number {
    return 12.4;
  }

  kpis(): KpiDatum[] {
    const s = this._summary();
    return [
      { label: 'dashboard.kpi.active_employees', value: s?.active_employees ?? 0, icon: 'pracownicy', tone: 'primary', trend: null, subtitle: 'dashboard.kpi.active_employees_sub' },
      { label: 'dashboard.kpi.active_terminals', value: s?.active_terminals ?? 0, icon: 'terminale', tone: 'info', trend: null, subtitle: 'dashboard.kpi.active_terminals_sub' },
      { label: 'dashboard.kpi.vehicles_in_use', value: s?.vehicles_in_use ?? 0, icon: 'sprzet', tone: 'success', trend: null, subtitle: 'dashboard.kpi.vehicles_in_use_sub' },
      { label: 'dashboard.kpi.active_incidents', value: s?.active_incidents ?? 0, icon: 'awaria', tone: 'danger', trend: null, subtitle: 'dashboard.kpi.active_incidents_sub' },
    ];
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
      { key: 'dashboard.shortcuts.create_report', icon: 'raportowanie', route: '/raportowanie' },
      { key: 'dashboard.shortcuts.add_order', icon: 'harmonogram', route: '/harmonogram' },
    ];
  }

  activity(): ActivityDatum[] {
    return [
      { titleKey: 'dashboard.activity.item_new_employee', time: '12 min temu', badge: 'bg-blue-500' },
      { titleKey: 'dashboard.activity.item_invoice_paid', time: '1 godz. temu', badge: 'bg-emerald-500' },
      { titleKey: 'dashboard.activity.item_terminal_online', time: '3 godz. temu', badge: 'bg-amber-500' },
      { titleKey: 'dashboard.activity.item_incident_closed', time: '5 godz. temu', badge: 'bg-red-500' },
    ];
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

    // Oba żądania są niezależne — kończymy ładowanie po ich wystartowaniu.
    this.loading.set(false);
  }

  // ===== Wykres: operacje (area) =====
  readonly area: {
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
  } = {
    chart: {
      type: 'area',
      height: 300,
      fontFamily: 'Inter, sans-serif',
      toolbar: { show: false },
      zoom: { enabled: false },
      parentHeightOffset: 0,
    },
    series: [
      {
        name: 'Zlecenia',
        data: [42, 48, 45, 58, 62, 55, 68, 72, 65, 78, 74, 82, 88, 92],
      },
    ],
    xaxis: {
      categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14'],
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
    colors: ['#3b82f6'],
  };

  // ===== Wykres: struktura floty (donut) =====
  readonly donut: {
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
  } = {
    chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' },
    series: [42, 28, 18, 12],
    labels: ['Terminale', 'Pojazdy', 'Pracownicy', 'Inne'],
    colors: ['#1e3a5f', '#3b82f6', '#f59e0b', '#22c55e'],
    legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: '#475569' } },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            name: { show: false },
            value: { show: true, fontSize: '22px', fontWeight: '700', color: '#172d49' },
            total: { show: true, label: 'Szt.', color: '#64748b', fontSize: '12px' },
          },
        },
      },
    },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    tooltip: { theme: 'light' },
    responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }],
  };

  // ===== Wykres: obrót per terminal (bar) =====
  readonly bar: {
    chart: ApexChart;
    series: ApexAxisChartSeries;
    xaxis: ApexXAxis;
    yaxis: ApexYAxis;
    plotOptions: ApexPlotOptions;
    colors: string[];
    grid: ApexGrid;
    dataLabels: ApexDataLabels;
    tooltip: ApexTooltip;
    legend: ApexLegend;
  } = {
    chart: {
      type: 'bar',
      height: 300,
      fontFamily: 'Inter, sans-serif',
      toolbar: { show: false },
      parentHeightOffset: 0,
    },
    series: [{ name: 'Wartość (tys. PLN)', data: [320, 460, 275, 540, 380, 410] }],
    xaxis: {
      categories: ['Terminal A', 'Terminal B', 'Terminal C', 'Terminal D', 'Terminal E', 'Terminal F'],
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
    },
    yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } },
    plotOptions: {
      bar: { borderRadius: 6, columnWidth: '55%', distributed: false },
    },
    colors: ['#3b82f6'],
    grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } },
    dataLabels: { enabled: false },
    tooltip: { theme: 'light' },
    legend: { show: false },
  };

}
