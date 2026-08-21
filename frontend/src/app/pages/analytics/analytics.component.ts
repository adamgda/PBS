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
import { ThemeService } from '../../services/theme.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { KpiCardComponent, KpiTone } from '../../components/kpi-card/kpi-card.component';
import {
  AnalyticsOverview,
  AnalyticsTerminal,
  AnalyticsEmployee,
  AnalyticsEquipment,
  AnalyticsOrderInTime,
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
  selector: 'app-analytics',
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
  templateUrl: './analytics.component.html',
})
export class AnalyticsComponent {
  private readonly analyticsService = inject(AnalyticsService);
  private readonly translate = inject(TranslateService);
  private readonly themeService = inject(ThemeService);

  readonly activePreset = signal<RangePreset>('30');
  readonly loading = signal<boolean>(false);
  readonly error = signal<string>('');

  private readonly _overview = signal<AnalyticsOverview | null>(null);
  private readonly _terminals = signal<AnalyticsTerminal[]>([]);
  private readonly _employees = signal<AnalyticsEmployee[]>([]);
  private readonly _equipment = signal<AnalyticsEquipment[]>([]);
  private readonly _ordersInTime = signal<AnalyticsOrderInTime[]>([]);
  private readonly _relations = signal<AnalyticsRelation[]>([]);

  readonly overview = this._overview.asReadonly();
  readonly terminals = this._terminals.asReadonly();
  readonly employees = this._employees.asReadonly();
  readonly equipment = this._equipment.asReadonly();
  readonly ordersInTime = this._ordersInTime.asReadonly();
  readonly relations = this._relations.asReadonly();

  /**
   * Flaga informująca, że dane wykresów zostały załadowane z API.
   * Wykresy ApexCharts są renderowane dopiero, gdy ta flaga jest true —
   * zapobiega to problemowi niewyświetlania wykresów przy pierwszym wejściu
   * / hard refresh (apx-chart źle przebudowuje się z pustych danych → pełne).
   */
  readonly chartDataLoaded = computed<boolean>(
    () => this._terminals().length > 0 || this._equipment().length > 0,
  );

  readonly presets: { key: RangePreset; label: string }[] = [
    { key: '7', label: 'analytics.presets.7' },
    { key: '30', label: 'analytics.presets.30' },
    { key: '90', label: 'analytics.presets.90' },
    { key: '365', label: 'analytics.presets.365' },
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

    let pending = 6;
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
    this.analyticsService.ordersInTime(params).subscribe({
      next: (d) => this._ordersInTime.set(d.data),
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
      { label: 'analytics.kpi.orders', value: o?.total_orders ?? 0, icon: 'harmonogram', tone: 'primary' },
      { label: 'analytics.kpi.hours', value: `${this.fmt(o?.total_hours ?? 0)} h`, icon: 'pracownicy', tone: 'info' },
      { label: 'analytics.kpi.wages', value: `${this.fmt(o?.total_wages ?? 0)} zł`, icon: 'reporting', tone: 'success' },
      {
        label: 'analytics.kpi.incidents',
        value: o?.total_incidents ?? 0,
        icon: 'awaria',
        tone: 'danger',
        subtitle: `${this.t('analytics.kpi.downtime')}: ${this.fmt(o?.incident_downtime_hours ?? 0)} h`,
      },
    ];
  }

  // --- Wykres: obciążenie terminali (bar) ---

  readonly terminalBar = computed(() => {
    const rows = this._terminals();
    const dark = this.themeService.dark();
    // Ten sam kolor słupków co w dashboard (ujednolicenie) — reaguje na darkmode.
    const barColor = dark ? '#60a5fa' : '#2563eb';
    return {
      chart: { type: 'bar', height: 300, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0 } as ApexChart,
      series: [{ name: this.t('analytics.charts.orders'), data: rows.map((r) => r.order_count) }] as ApexAxisChartSeries,
      xaxis: { categories: rows.map((r) => r.nazwa ?? '—'), axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexXAxis,
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexYAxis,
      plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } } as ApexPlotOptions,
      colors: [barColor],
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } } as ApexGrid,
      dataLabels: { enabled: false } as ApexDataLabels,
      tooltip: { theme: dark ? 'dark' : 'light' } as ApexTooltip,
      legend: { show: false } as ApexLegend,
    };
  });

  // --- Wykres: rozkład sprzętu (donut) ---

  readonly equipmentDonut = computed(() => {
    // „Najczęściej przypisany sprzęt" — pokazujemy tylko sprzęt z co najmniej
    // jednym przypisaniem (bez pustych wycinków) i ograniczamy do top 6.
    const used = this._equipment()
      .filter((r) => (r.assignment_count ?? 0) > 0)
      .slice(0, 6);
    const dark = this.themeService.dark();
    // Kolory czytelne w obu motywach: jasny font wartości na ciemnym tle.
    const centerValueColor = dark ? '#e2e8f0' : '#172d49';
    const mutedColor = dark ? '#94a3b8' : '#64748b';
    const legendColor = dark ? '#cbd5e1' : '#475569';
    return {
      chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' } as ApexChart,
      series: used.map((r) => r.assignment_count) as ApexNonAxisChartSeries,
      labels: used.map((r) => r.nazwa ?? '—'),
      colors: ['#0891b2', '#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#64748b'],
      legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: legendColor } } as ApexLegend,
      plotOptions: {
        pie: {
          donut: {
            size: '72%',
            labels: {
              show: true,
              name: { show: false },
              value: { show: true, fontSize: '22px', fontWeight: '700', color: centerValueColor },
              total: { show: true, label: this.t('analytics.charts.assignments'), color: mutedColor, fontSize: '12px' },
            },
          },
        },
      } as ApexPlotOptions,
      dataLabels: { enabled: false } as ApexDataLabels,
      // Obrys segmentów dopasowany do motywu — w dark mode ciemny zamiast białego.
      stroke: { width: 2, colors: [dark ? '#0f172a' : '#ffffff'] },
      tooltip: { theme: dark ? 'dark' : 'light' } as ApexTooltip,
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }] as ApexResponsive[],
    };
  });

  // --- Wykres: zlecenia w czasie (line) ---

  readonly ordersLine = computed(() => {
    const days = this._ordersInTime();
    const dark = this.themeService.dark();
    // Kolor linii reagujący na darkmode (spójny ze słupkami) — jaśniejszy odcień
    // w ciemnym motywie, aby linia była wyraźnie widoczna.
    const lineColor = dark ? '#60a5fa' : '#2563eb';
    return {
      chart: { type: 'line', height: 300, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0 } as ApexChart,
      series: [{ name: this.t('analytics.charts.orders'), data: days.map((d) => d.count) }] as ApexAxisChartSeries,
      xaxis: {
        categories: days.map((d) => this.shortDate(d.day)),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
        tickAmount: 8,
      } as ApexXAxis,
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '12px' } } } as ApexYAxis,
      stroke: { curve: 'smooth', width: 3, lineCap: 'round' } as ApexStroke,
      // Jednolite, subtelne wypełnienie obszaru pod linią — bez gradientu,
      // który rozmywał i rozjaśniał samą linię (stąd „niewidoczna”).
      fill: { type: 'solid', opacity: 0.12 } as ApexFill,
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } } as ApexGrid,
      dataLabels: { enabled: false } as ApexDataLabels,
      tooltip: { theme: dark ? 'dark' : 'light', marker: { show: true } } as ApexTooltip,
      legend: { show: false } as ApexLegend,
      colors: [lineColor],
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

  /** Formatuje ISO (Y-m-d) do czytelnej etykiety osi: DD.MM */
  private shortDate(iso: string): string {
    const parts = iso.split('-');
    if (parts.length !== 3) {
      return iso;
    }
    return `${parts[2]}.${parts[1]}`;
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
