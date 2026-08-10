import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
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

import { AnalyticsService } from '../../services/analytics.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { KpiCardComponent, KpiTone } from '../../components/kpi-card/kpi-card.component';
import {
  AnalyticsOverview,
  AnalyticsTerminal,
  AnalyticsEmployee,
  AnalyticsEquipment,
  AnalyticsRelation,
} from '../../models/analytics.model';

interface KpiDatum {
  label: string;
  value: string | number;
  icon: string;
  tone: KpiTone;
  subtitle?: string;
}

type RangePreset = '7' | '30' | '90' | '365';

/**
 * Sekcja Analityka (Etap 12).
 * Filtry czasowe (zakres dat, domyślnie 30 dni), karty KPI, wykresy ApexCharts
 * (obciążenie terminali, rozkład sprzętu, zlecenia w czasie) oraz statystyki
 * pracowników i relacje między zasobami.
 */
@Component({
  selector: 'app-analityka',
  standalone: true,
  imports: [
    CommonModule,
    DatePipe,
    TranslatePipe,
    SvgIconComponent,
    KpiCardComponent,
    NgApexchartsModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './analityka.component.html',
})
export class AnalitykaComponent {
  private readonly analyticsService = inject(AnalyticsService);
  private readonly translate = inject(TranslateService);

  readonly activePreset = signal<RangePreset>('30');
  readonly loading = signal<boolean>(false);
  readonly error = signal<string>('');

  private readonly _overview = signal<AnalyticsOverview | null>(null);
  private readonly _terminals = signal<AnalyticsTerminal[]>([]);
  private readonly _employees = signal<AnalyticsEmployee[]>([]);
  private readonly _equipment = signal<AnalyticsEquipment[]>([]);
  private readonly _relations = signal<AnalyticsRelation[]>([]);

  readonly overview = this._overview.asReadonly();
  readonly terminals = this._terminals.asReadonly();
  readonly employees = this._employees.asReadonly();
  readonly equipment = this._equipment.asReadonly();
  readonly relations = this._relations.asReadonly();

  readonly presets: { key: RangePreset; label: string }[] = [
    { key: '7', label: 'analityka.presets.7' },
    { key: '30', label: 'analityka.presets.30' },
    { key: '90', label: 'analityka.presets.90' },
    { key: '365', label: 'analityka.presets.365' },
  ];

  constructor() {
    this.load();
  }

  setPreset(key: RangePreset): void {
    this.activePreset.set(key);
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set('');
    const days = Number(this.activePreset());
    const dateTo = new Date();
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - days);
    const params = {
      date_from: this.toDate(dateFrom),
      date_to: this.toDate(dateTo),
    };

    let pending = 5;
    const done = (): void => {
      pending -= 1;
      if (pending <= 0) {
        this.loading.set(false);
      }
    };

    this.analyticsService.overview(params).subscribe({
      next: (d) => this._overview.set(d),
      error: () => this.handleError(),
      complete: done,
    });
    this.analyticsService.terminals(params).subscribe({
      next: (d) => this._terminals.set(d.data),
      error: () => this.handleError(),
      complete: done,
    });
    this.analyticsService.employees(params).subscribe({
      next: (d) => this._employees.set(d.data),
      error: () => this.handleError(),
      complete: done,
    });
    this.analyticsService.equipment(params).subscribe({
      next: (d) => this._equipment.set(d.data),
      error: () => this.handleError(),
      complete: done,
    });
    this.analyticsService.relations(params).subscribe({
      next: (d) => this._relations.set(d.data),
      error: () => this.handleError(),
      complete: done,
    });
  }

  private handleError(): void {
    this.error.set(this.t('common.messages.error.generic'));
  }

  // --- Karty KPI ---

  kpis(): KpiDatum[] {
    const o = this._overview();
    return [
      { label: 'analityka.kpi.orders', value: o?.total_orders ?? 0, icon: 'harmonogram', tone: 'primary' },
      { label: 'analityka.kpi.hours', value: `${this.fmt(o?.total_hours ?? 0)} h`, icon: 'pracownicy', tone: 'info' },
      { label: 'analityka.kpi.wages', value: `${this.fmt(o?.total_wages ?? 0)} zł`, icon: 'raportowanie', tone: 'success' },
      {
        label: 'analityka.kpi.incidents',
        value: o?.total_incidents ?? 0,
        icon: 'awaria',
        tone: 'danger',
        subtitle: `${this.t('analityka.kpi.downtime')}: ${this.fmt(o?.incident_downtime_hours ?? 0)} h`,
      },
    ];
  }

  // --- Wykres: obciążenie terminali (bar) ---

  readonly terminalBar = computed(() => {
    const rows = this._terminals();
    return {
      chart: { type: 'bar', height: 300, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0 } as ApexChart,
      series: [{ name: this.t('analityka.charts.orders'), data: rows.map((r) => r.order_count) }] as ApexAxisChartSeries,
      xaxis: { categories: rows.map((r) => r.nazwa ?? '—'), axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexXAxis,
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexYAxis,
      plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } } as ApexPlotOptions,
      colors: ['#0891b2'],
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } } as ApexGrid,
      dataLabels: { enabled: false } as ApexDataLabels,
      tooltip: { theme: 'light' } as ApexTooltip,
      legend: { show: false } as ApexLegend,
    };
  });

  // --- Wykres: rozkład sprzętu (donut) ---

  readonly equipmentDonut = computed(() => {
    const rows = this._equipment();
    return {
      chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' } as ApexChart,
      series: rows.map((r) => r.assignment_count) as ApexNonAxisChartSeries,
      labels: rows.map((r) => r.nazwa ?? '—'),
      colors: ['#0891b2', '#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#64748b'],
      legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: '#475569' } } as ApexLegend,
      plotOptions: { pie: { donut: { size: '72%', labels: { show: true, name: { show: false }, value: { show: true, fontSize: '22px', fontWeight: '700', color: '#172d49' }, total: { show: true, label: this.t('analityka.charts.assignments'), color: '#64748b', fontSize: '12px' } } } } } as ApexPlotOptions,
      dataLabels: { enabled: false } as ApexDataLabels,
      stroke: { width: 0 },
      tooltip: { theme: 'light' } as ApexTooltip,
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }] as ApexResponsive[],
    };
  });

  // --- Wykres: zlecenia w czasie (line) ---

  readonly ordersLine = computed(() => {
    const rows = this._terminals();
    return {
      chart: { type: 'line', height: 300, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0 } as ApexChart,
      series: [{ name: this.t('analityka.charts.orders'), data: rows.map((r) => r.order_count) }] as ApexAxisChartSeries,
      xaxis: { categories: rows.map((r) => r.nazwa ?? '—'), axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexXAxis,
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexYAxis,
      stroke: { curve: 'smooth', width: 2.5 } as ApexStroke,
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03, stops: [0, 90, 100] } } as ApexFill,
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } } as ApexGrid,
      dataLabels: { enabled: false } as ApexDataLabels,
      tooltip: { theme: 'light', marker: { show: true } } as ApexTooltip,
      legend: { show: false } as ApexLegend,
      colors: ['#3b82f6'],
    };
  });

  // --- Pomocnicze ---

  employeeName(e: AnalyticsEmployee): string {
    return `${e.imie ?? ''} ${e.nazwisko ?? ''}`.trim() || '—';
  }

  relationName(r: AnalyticsRelation): string {
    return `${r.imie ?? ''} ${r.nazwisko ?? ''}`.trim() || '—';
  }

  initials(name: string): string {
    const parts = name.split(' ').filter(Boolean);
    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
  }

  private toDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  private fmt(n: number): string {
    return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(n);
  }

  fmtWages(n: number): string {
    return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2 }).format(n);
  }

  private t(key: string): string {
    return this.translate.instant(key);
  }
}
