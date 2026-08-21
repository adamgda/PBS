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
import { ThemeService } from '../../services/theme.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { KpiCardComponent, KpiTone } from '../../components/kpi-card/kpi-card.component';
import { DashboardSummary, DashboardAlerts, DashboardCharts } from '../../models/dashboard.model';
import { ButtonComponent } from "../../components/button/button.component";

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

type AlertTone = 'warning' | 'info' | 'danger' | 'success' | 'neutral';

/** Pojedyncza, konkretna pozycja w grupie alertów (np. jeden wygasający certyfikat). */
interface AlertDetailItem {
  id: number;
  title: string;
  meta: string;
  date: string;
  urgency: string;
  tone: AlertTone;
  route: string;
}

/** Grupa alertów — nagłówek + liczba + lista szczegółowych pozycji z odnośnikami. */
interface AlertGroupDatum {
  key: string;
  icon: string;
  tone: AlertTone;
  count: number;
  route: string;
  items: AlertDetailItem[];
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

/** Czytelne etykiety statusów awarii (sekcja jest single-language PL jak relativeTime). */
const INCIDENT_STATUS_LABELS: Record<string, string> = {
  zgloszona: 'Zgłoszona',
  w_trakcie_naprawy: 'W trakcie naprawy',
  naprawiona: 'Naprawiona',
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
    ButtonComponent
],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly dashboardService = inject(DashboardService);
  private readonly themeService = inject(ThemeService);
  private readonly router = inject(Router);

  /** Data wyświetlana w nagłówku. */
  readonly today = new Date();

  readonly error = signal<string>('');

  /** Flagi ładowania per źródło danych — dzięki temu każda sekcja statystyk
   *  ma własny loader (zamiast pokazywania "0" przy wolnym internecie). */
  readonly summaryLoading = signal<boolean>(true);
  readonly alertsLoading = signal<boolean>(true);
  readonly chartsLoading = signal<boolean>(true);

  /** Czy trwa jakiekolwiek pobieranie danych z API (wyliczana z powyższych). */
  readonly loading = computed(
    () => this.summaryLoading() || this.alertsLoading() || this.chartsLoading(),
  );

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
    return (name.charAt(0) + name.slice(1)) || 'Użytkowniku';
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

  /**
   * Szczegółowe grupy alertów — każda pozycja to konkretny rekord
   * (certyfikat, przegląd, awaria, powrót z urlopu) z datą i odnośnikiem
   * do podstrony, na której można nim zarządzać.
   */
  alertGroups(): AlertGroupDatum[] {
    const a = this._alerts();

    const certs: AlertDetailItem[] = (a?.expiring_certs.items ?? []).map((it) => ({
      id: Number(it['id']),
      title: String(it['nazwa'] ?? '—'),
      meta: this.fullName(it['imie'], it['nazwisko']),
      date: this.formatDate(it['data_waznosci']),
      urgency: this.daysUntil(it['data_waznosci']),
      tone: 'warning',
      route: '/employees',
    }));

    const inspections: AlertDetailItem[] = (a?.upcoming_inspections.items ?? []).map((it) => {
      const sprzet = String(it['sprzet_nazwa'] ?? '');
      const serial = it['numer_seryjny'] ? ` · ${String(it['numer_seryjny'])}` : '';
      return {
        id: Number(it['id']),
        title: String(it['typ_przegladu'] ?? '—'),
        meta: (sprzet + serial).trim() || '—',
        date: this.formatDate(it['data_nastepnego_planowanego']),
        urgency: this.daysUntil(it['data_nastepnego_planowanego']),
        tone: 'info',
        route: '/equipment',
      };
    });

    const incidents: AlertDetailItem[] = (a?.unresolved_incidents.items ?? []).map((it) => {
      const id = Number(it['id']);
      return {
        id,
        title: String(it['opis'] ?? 'Awarie'),
        meta: INCIDENT_STATUS_LABELS[String(it['status'] ?? '')] ?? String(it['status'] ?? ''),
        date: this.formatDate(it['data_zgloszenia']),
        urgency: '',
        tone: 'danger',
        route: id ? `/incidents/${id}` : '/incidents',
      };
    });

    const returns: AlertDetailItem[] = (a?.returning_from_leave.items ?? []).map((it) => {
      const name = this.fullName(it['imie'], it['nazwisko']);
      return {
        id: Number(it['id']),
        title: name || '—',
        meta: 'Powrót z urlopu',
        date: this.formatDate(it['data_do']),
        urgency: this.daysUntil(it['data_do']),
        tone: 'success',
        route: '/employees',
      };
    });

    return [
      {
        key: 'dashboard.alerts.expiring_certs',
        icon: 'document',
        tone: 'warning',
        count: a?.expiring_certs.count ?? 0,
        route: '/employees',
        items: certs,
      },
      {
        key: 'dashboard.alerts.upcoming_inspections',
        icon: 'wrench',
        tone: 'info',
        count: a?.upcoming_inspections.count ?? 0,
        route: '/equipment',
        items: inspections,
      },
      {
        key: 'dashboard.alerts.unresolved_incidents',
        icon: 'awaria',
        tone: 'danger',
        count: a?.unresolved_incidents.count ?? 0,
        route: '/incidents',
        items: incidents,
      },
      {
        key: 'dashboard.alerts.returning_from_leave',
        icon: 'pracownicy',
        tone: 'success',
        count: a?.returning_from_leave.count ?? 0,
        route: '/employees',
        items: returns,
      },
    ];
  }

  private fullName(first: string | number | null, last: string | number | null): string {
    return `${first ?? ''} ${last ?? ''}`.trim();
  }

  private formatDate(value: string | number | null): string {
    const ts = this.toTimestamp(value);
    if (!ts) {
      return '';
    }
    return new Date(ts).toLocaleDateString('pl-PL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  private daysUntil(value: string | number | null): string {
    const ts = this.toTimestamp(value);
    if (!ts) {
      return '';
    }
    const diff = Math.ceil((ts - Date.now()) / 86400000);
    if (diff <= 0) {
      return 'dziś';
    }
    if (diff === 1) {
      return 'jutro';
    }
    return `za ${diff} dni`;
  }

  private toTimestamp(value: string | number | null): number {
    if (value === null || value === undefined || value === '') {
      return 0;
    }
    const str = String(value);
    const ts = new Date(str.includes('T') ? str : str.replace(' ', 'T')).getTime();
    return Number.isNaN(ts) ? 0 : ts;
  }

  shortcuts(): ShortcutDatum[] {
    return [
      { key: 'dashboard.shortcuts.report_incident', icon: 'awaria', route: '/incidents/new' },
      { key: 'dashboard.shortcuts.create_report', icon: 'reporting', route: '/reporting' },
      { key: 'dashboard.shortcuts.add_order', icon: 'harmonogram', route: '/schedule/new' },
    ];
  }

  activity(): ActivityDatum[] {
    const items = this._charts()?.activity ?? [];
    return items.slice(0, 6).map((item) => {
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

  alertBadgeClass(tone: AlertTone): string {
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
    this.error.set('');
    this.summaryLoading.set(true);
    this.alertsLoading.set(true);
    this.chartsLoading.set(true);

    // Żądania są niezależne — każdy włącza/wyłącza własną flagę ładowania,
    // dzięki czemu loader znika dopiero gdy realne dane dotrą (lub wystąpi błąd).
    this.dashboardService.summary().subscribe({
      next: (summary) => {
        this._summary.set(summary);
        this.summaryLoading.set(false);
      },
      error: () => {
        this.summaryLoading.set(false);
        this.error.set('dashboard.error');
      },
    });

    this.dashboardService.alerts().subscribe({
      next: (alerts) => {
        this._alerts.set(alerts);
        this.alertsLoading.set(false);
      },
      error: () => {
        this.alertsLoading.set(false);
        this.error.set('dashboard.error');
      },
    });

    this.dashboardService.charts().subscribe({
      next: (charts) => {
        this._charts.set(charts);
        this.chartsLoading.set(false);
      },
      error: () => {
        this.chartsLoading.set(false);
        this.error.set('dashboard.error');
      },
    });
  }

  // ===== Wykres: operacje (area) — realne dane z `/dashboard/charts` =====
  readonly area = computed<AreaChartConfig>(() => {
    const trend = this._charts()?.orders_trend;
    const data = trend?.series ?? [];
    const dark = this.themeService.dark();

    // Zapas (padding) wokół zakresu osi Y — dzięki temu gładka linia wykresu
    // nie jest przycinana w skrajnych punktach. Górna granica dostaje zapas
    // powyżej maksimum, a dolna schodzi lekko poniżej 0 (np. -0.1/-0.2),
    // aby wartość 0 też miała wolną przestrzeń pod linią.
    const rawMin = data.length ? Math.min(...data) : 0;
    const rawMax = data.length ? Math.max(...data) : 0;
    const spread = Math.max(rawMax - rawMin, 1);
    const yMin = rawMin - spread * 0.15;
    const yMax = rawMax + spread * 0.15;
    const yAuto = data.length === 0;

    return {
      chart: {
        type: 'area',
        height: 300,
        fontFamily: 'Inter, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        parentHeightOffset: 0,
      },
      series: [{ name: 'Zlecenia', data }],
      xaxis: {
        categories: trend?.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
      },
      yaxis: {
        min: yAuto ? undefined : yMin,
        max: yAuto ? undefined : yMax,
        forceNiceScale: yAuto,
        labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
      },
      stroke: { curve: 'smooth', width: 2.5 },
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03, stops: [0, 90, 100] },
      },
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } },
      dataLabels: { enabled: false },
      tooltip: { theme: dark ? 'dark' : 'light', marker: { show: true } },
      legend: { show: false },
      colors: ['#1e3a5f'],
    };
  });

  // ===== Wykres: struktura floty (donut) — realne dane z `/dashboard/charts` =====
  readonly donut = computed<DonutChartConfig>(() => {
    const fleet = this._charts()?.fleet_structure;
    const dark = this.themeService.dark();
    const centerValueColor = dark ? '#e2e8f0' : '#172d49';
    const mutedColor = dark ? '#94a3b8' : '#64748b';
    const legendColor = dark ? '#cbd5e1' : '#475569';
    return {
      chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' },
      series: fleet?.series ?? [],
      labels: fleet?.labels ?? [],
      colors: ['#f43f5e', '#3b82f6', '#f59e0b', '#22c55e'],
      legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: legendColor } },
      plotOptions: {
        pie: {
          donut: {
            size: '74%',
            labels: {
              show: true,
              name: { show: false },
              value: { show: true, fontSize: '24px', fontWeight: '700', color: centerValueColor },
              total: { show: true, label: 'Szt.', color: mutedColor, fontSize: '12px' },
            },
          },
        },
      },
      dataLabels: { enabled: false },
      // Obrys segmentów dopasowany do motywu — w dark mode ciemny zamiast białego.
      stroke: { width: 3, colors: [dark ? '#0f172a' : '#ffffff'] },
      tooltip: { theme: dark ? 'dark' : 'light' },
      responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }],
    };
  });

  // ===== Wykres: obrót per terminal (bar) — realne dane z `/dashboard/charts` =====
  readonly bar = computed<BarChartConfig>(() => {
    const turnover = this._charts()?.terminal_turnover;
    const dark = this.themeService.dark();
    // Kolor słupków dopasowany do motywu (jasny/ciemny) — niebieski z palety marki.
    const barColor = dark ? '#60a5fa' : '#2563eb';
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
      colors: [barColor],
      // Solidne (jednolite) wypełnienie — bez gradientu.
      fill: { type: 'solid', colors: [barColor] },
      grid: { borderColor: '#eef2f7', strokeDashArray: 4, padding: { left: 8, right: 8 } },
      dataLabels: { enabled: false },
      tooltip: { theme: dark ? 'dark' : 'light' },
      legend: { show: false },
    };
  });

}
