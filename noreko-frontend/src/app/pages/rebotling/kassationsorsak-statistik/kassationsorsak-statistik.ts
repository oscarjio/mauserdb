import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KassationsorsakStatistikService,
  OverviewData,
  ParetoData,
  ParetoItem,
  TrendData,
  PerOperatorData,
  PerShiftData,
  DrilldownData,
} from '../../../services/kassationsorsak-statistik.service';

Chart.register(...registerables);

const ORSAK_COLORS = [
  '#fc8181', '#f6ad55', '#ecc94b', '#68d391', '#63b3ed',
  '#9f7aea', '#f687b3', '#4fd1c5', '#fbd38d', '#b794f4',
];

@Component({
  standalone: true,
  selector: 'app-kassationsorsak-statistik',
  templateUrl: './kassationsorsak-statistik.html',
  styleUrls: ['./kassationsorsak-statistik.css'],
  imports: [CommonModule, FormsModule],
})
export class KassationsorsakStatistikPage implements OnInit, OnDestroy {
  // Period
  period = 30;
  readonly periodOptions = [7, 30, 90, 365];

  // Loading flags
  loadingOverview = false;
  loadingPareto = false;
  loadingTrend = false;
  loadingOperator = false;
  loadingShift = false;
  loadingDrilldown = false;

  // Error flags
  errorOverview = false;

  // Data
  overview: OverviewData | null = null;
  paretoData: ParetoData | null = null;
  trendData: TrendData | null = null;
  operatorData: PerOperatorData | null = null;
  shiftData: PerShiftData | null = null;
  drilldownData: DrilldownData | null = null;
  drilldownOrsakNamn = '';

  // Cached computed properties
  cachedTrendText = '';
  cachedTrendIcon = '';

  // Trend checkboxar
  selectedTrendOrsaker = new Set<number>();

  // Charts
  private paretoChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private drilldownChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: KassationsorsakStatistikService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  setPeriod(p: number): void {
    this.period = p;
    this.drilldownData = null;
    this.loadAll();
  }

  loadAll(): void {
    this.loadOverview();
    this.loadPareto();
    this.loadTrend();
    this.loadPerOperator();
    this.loadPerShift();
  }

  // ---- Overview ----
  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
        this.cachedTrendText = this.getTrendText();
        this.cachedTrendIcon = this.getTrendIcon();
      } else {
        this.errorOverview = true;
      }
    });
  }

  // ---- Pareto ----
  private loadPareto(): void {
    this.loadingPareto = true;
    this.svc.getPareto(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPareto = false;
      if (res?.success) {
        this.paretoData = res.data;
        setTimeout(() => { if (!this.destroy$.closed) this.buildParetoChart(); }, 100);
      }
    });
  }

  // ---- Trend ----
  private loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        // Välj top 3 orsaker som standard
        this.selectedTrendOrsaker.clear();
        const top = res.data.series
          .map(s => ({ id: s.orsak_id, sum: s.values.reduce((a, b) => a + b, 0) }))
          .sort((a, b) => b.sum - a.sum)
          .slice(0, 3);
        top.forEach(t => this.selectedTrendOrsaker.add(t.id));
        setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 100);
      }
    });
  }

  // ---- Per operator ----
  private loadPerOperator(): void {
    this.loadingOperator = true;
    this.svc.getPerOperator(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOperator = false;
      if (res?.success) {
        this.operatorData = res.data;
      }
    });
  }

  // ---- Per shift ----
  private loadPerShift(): void {
    this.loadingShift = true;
    this.svc.getPerShift(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingShift = false;
      if (res?.success) {
        this.shiftData = res.data;
      }
    });
  }

  // ---- Drilldown ----
  openDrilldown(orsakId: number, orsakNamn: string): void {
    this.drilldownOrsakNamn = orsakNamn;
    this.loadingDrilldown = true;
    this.drilldownData = null;

    this.svc.getDrilldown(orsakId, this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingDrilldown = false;
      if (res?.success) {
        this.drilldownData = res.data;
        setTimeout(() => { if (!this.destroy$.closed) this.buildDrilldownChart(); }, 100);
      }
    });
  }

  closeDrilldown(): void {
    this.drilldownData = null;
    this.drilldownOrsakNamn = '';
    this.destroyDrilldownChart();
  }

  // ---- Trend checkbox ----
  toggleTrendOrsak(id: number): void {
    if (this.selectedTrendOrsaker.has(id)) {
      this.selectedTrendOrsaker.delete(id);
    } else {
      this.selectedTrendOrsaker.add(id);
    }
    this.buildTrendChart();
  }

  // ---- Charts ----
  private destroyCharts(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    try { this.drilldownChart?.destroy(); } catch (_) {}
    this.paretoChart = null;
    this.trendChart = null;
    this.drilldownChart = null;
  }

  private destroyDrilldownChart(): void {
    try { this.drilldownChart?.destroy(); } catch (_) {}
    this.drilldownChart = null;
  }

  private buildParetoChart(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    this.paretoChart = null;

    if (!this.paretoData?.pareto?.length) return;
    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas) return;

    const items = this.paretoData.pareto;
    const labels = items.map(i => i.orsak);
    const antalData = items.map(i => i.antal);
    const kumulativData = items.map(i => i.kumulativ_pct);
    const colors = items.map((_, i) => ORSAK_COLORS[i % ORSAK_COLORS.length]);

    const self = this;

    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal kasserade',
            data: antalData,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1,
            yAxisID: 'y',
            order: 2,
          },
          {
            label: 'Kumulativ %',
            data: kumulativData,
            type: 'line',
            borderColor: '#e2e8f0',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#e2e8f0',
            tension: 0.3,
            yAxisID: 'y1',
            order: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event: any, elements: any[]) => {
          if (elements.length > 0) {
            const idx = elements[0].index;
            const item = items[idx];
            if (item) {
              self.openDrilldown(item.orsak_id, item.orsak);
            }
          }
        },
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 12 } } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            position: 'left',
            title: { display: true, text: 'Antal', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          y1: {
            position: 'right',
            title: { display: true, text: 'Kumulativ %', color: '#a0aec0' },
            ticks: { color: '#a0aec0', callback: (v: any) => v + '%' },
            grid: { drawOnChartArea: false },
            min: 0,
            max: 100,
          },
        },
      },
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    if (!this.trendData?.dates?.length) return;
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const dates = this.trendData.dates;
    const datasets = this.trendData.series
      .filter(s => this.selectedTrendOrsaker.has(s.orsak_id))
      .map((s, i) => ({
        label: s.orsak,
        data: s.values,
        borderColor: ORSAK_COLORS[i % ORSAK_COLORS.length],
        backgroundColor: 'transparent',
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.3,
      }));

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels: dates, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Antal kasserade', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  private buildDrilldownChart(): void {
    this.destroyDrilldownChart();

    if (!this.drilldownData?.dag_series?.length) return;
    const canvas = document.getElementById('drilldownChart') as HTMLCanvasElement;
    if (!canvas) return;

    const series = this.drilldownData.dag_series;
    const labels = series.map(d => d.datum);
    const values = series.map(d => d.antal);

    this.drilldownChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: this.drilldownOrsakNamn,
          data: values,
          backgroundColor: 'rgba(252, 129, 129, 0.6)',
          borderColor: '#fc8181',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Antal', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Helpers ----
  getTrendIcon(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'down': return 'fas fa-arrow-down text-success';
      case 'up':   return 'fas fa-arrow-up text-danger';
      default:     return 'fas fa-minus text-muted';
    }
  }

  getTrendText(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'down': return 'Forbattras';
      case 'up':   return 'Forsamras';
      default:     return 'Stabil';
    }
  }

  getSkiftLabel(skift: string): string {
    switch (skift) {
      case 'dag':    return 'Dag (06-14)';
      case 'kväll':  return 'Kvall (14-22)';
      case 'natt':   return 'Natt (22-06)';
      default:       return 'Okand';
    }
  }

  getOrsakColor(index: number): string {
    return ORSAK_COLORS[index % ORSAK_COLORS.length];
  }
  trackByIndex(index: number): number { return index; }
}
