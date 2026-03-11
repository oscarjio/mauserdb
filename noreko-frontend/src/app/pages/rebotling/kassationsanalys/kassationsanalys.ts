import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingService,
  KassationsOverviewData,
  KassationOrsak,
  KassationsByPeriodData,
  KassationsDetailsData,
  KassationsTrendRateData,
} from '../../../services/rebotling.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kassationsanalys',
  templateUrl: './kassationsanalys.html',
  styleUrls: ['./kassationsanalys.css'],
  imports: [CommonModule, FormsModule],
})
export class KassationsanalysPage implements OnInit, OnDestroy {
  // -- Period --
  days = 30;
  readonly dayOptions = [30, 90, 180, 365];
  groupMode: 'week' | 'month' = 'week';

  // -- Filter --
  filterOrsak: number | null = null;
  filterOperator = '';
  orsakLista: { id: number; namn: string }[] = [];
  operatorLista: string[] = [];

  // -- Ladda --
  loadingOverview = false;
  loadingByCause = false;
  loadingByPeriod = false;
  loadingDetails = false;
  loadingTrend = false;

  // -- Fel --
  errorOverview = false;
  errorByCause = false;
  errorByPeriod = false;
  errorDetails = false;
  errorTrend = false;

  // -- Data --
  overview: KassationsOverviewData | null = null;
  orsaker: KassationOrsak[] = [];
  byPeriodData: KassationsByPeriodData | null = null;
  detailsData: KassationsDetailsData | null = null;
  trendData: KassationsTrendRateData | null = null;

  // -- Charts --
  private stackedChart: Chart | null = null;
  private doughnutChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: RebotlingService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.stackedChart?.destroy(); } catch (_) {}
    try { this.doughnutChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.stackedChart = null;
    this.doughnutChart = null;
    this.trendChart = null;
  }

  // =================================================================
  // Period / Filter
  // =================================================================

  onDaysChange(d: number): void {
    this.days = d;
    this.groupMode = d <= 90 ? 'week' : 'month';
    this.filterOrsak = null;
    this.filterOperator = '';
    this.loadAll();
  }

  onGroupChange(g: 'week' | 'month'): void {
    this.groupMode = g;
    this.loadByPeriod();
  }

  onFilterChange(): void {
    this.loadDetails();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadOverview();
    this.loadByCause();
    this.loadByPeriod();
    this.loadDetails();
    this.loadTrend();
  }

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getKassationsOverview(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) {
          this.overview = res.data;
        } else {
          this.errorOverview = true;
        }
      });
  }

  loadByCause(): void {
    this.loadingByCause = true;
    this.errorByCause = false;
    this.svc.getKassationsByCause(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingByCause = false;
        if (res?.success) {
          this.orsaker = res.data.orsaker ?? [];
          setTimeout(() => { if (!this.destroy$.closed) this.buildDoughnutChart(); }, 0);
        } else {
          this.errorByCause = true;
          this.orsaker = [];
        }
      });
  }

  loadByPeriod(): void {
    this.loadingByPeriod = true;
    this.errorByPeriod = false;
    this.svc.getKassationsByPeriod(this.days, this.groupMode)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingByPeriod = false;
        if (res?.success) {
          this.byPeriodData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.buildStackedChart(); }, 0);
        } else {
          this.errorByPeriod = true;
          this.byPeriodData = null;
        }
      });
  }

  loadDetails(): void {
    this.loadingDetails = true;
    this.errorDetails = false;
    const orsak = this.filterOrsak ?? undefined;
    const op = this.filterOperator || undefined;
    this.svc.getKassationsDetails(this.days, orsak, op)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetails = false;
        if (res?.success) {
          this.detailsData = res.data;
          this.orsakLista = res.data.orsaker ?? [];
          this.operatorLista = res.data.operatorer ?? [];
        } else {
          this.errorDetails = true;
          this.detailsData = null;
        }
      });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getKassationsTrendRate(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
          this.trendData = null;
        }
      });
  }

  // =================================================================
  // Chart.js — Stacked bar (vecka/manad)
  // =================================================================

  private buildStackedChart(): void {
    try { this.stackedChart?.destroy(); } catch (_) {}
    this.stackedChart = null;

    const canvas = document.getElementById('kaStackedChart') as HTMLCanvasElement;
    if (!canvas || !this.byPeriodData?.har_data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, datasets } = this.byPeriodData;

    this.stackedChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: datasets.map(ds => ({
          label: ds.label,
          data: ds.data,
          backgroundColor: ds.backgroundColor,
          borderColor: ds.borderColor,
          borderWidth: ds.borderWidth,
          stack: 'kassationer',
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal kassationer', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Chart.js — Doughnut (orsaksfordelning)
  // =================================================================

  private buildDoughnutChart(): void {
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.doughnutChart = null;

    const canvas = document.getElementById('kaDoughnutChart') as HTMLCanvasElement;
    if (!canvas || this.orsaker.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const palette = [
      '#fc8181', '#f6ad55', '#68d391', '#63b3ed', '#b794f6',
      '#ecc94b', '#ed7979', '#81e6d9', '#f687b3', '#a0aec0',
    ];

    const top = this.orsaker.filter(o => o.antal > 0).slice(0, 8);
    if (top.length === 0) return;

    this.doughnutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: top.map(o => o.namn),
        datasets: [{
          data: top.map(o => o.antal),
          backgroundColor: top.map((_, i) => palette[i % palette.length]),
          borderColor: '#2d3748',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
          legend: {
            display: true,
            position: 'right',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => {
                const total = top.reduce((s, o) => s + o.antal, 0);
                const pct = total > 0 ? ((item.raw as number) / total * 100).toFixed(1) : '0';
                return ` ${item.label}: ${item.raw} st (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  // =================================================================
  // Chart.js — Trendgraf (kassationsgrad % + trendlinje)
  // =================================================================

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('kaTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData?.har_data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, rates, moving_avg, trendline } = this.trendData;

    const datasets: any[] = [
      {
        label: 'Kassationsgrad (%)',
        data: rates,
        borderColor: '#fc8181',
        backgroundColor: 'rgba(252,129,129,0.15)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: '#fc8181',
        borderWidth: 2,
      },
      {
        label: 'Glidande medel (4v)',
        data: moving_avg,
        borderColor: '#63b3ed',
        borderDash: [5, 3],
        fill: false,
        tension: 0.3,
        pointRadius: 0,
        borderWidth: 2,
      },
    ];

    if (trendline.length > 0) {
      datasets.push({
        label: 'Trendlinje',
        data: trendline,
        borderColor: '#a0aec0',
        borderDash: [8, 4],
        fill: false,
        tension: 0,
        pointRadius: 0,
        borderWidth: 1.5,
      });
    }

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Kassationsgrad (%)', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjalpmetoder
  // =================================================================

  trendIcon(trend: string): string {
    if (trend === 'up') return '\u25B2';
    if (trend === 'down') return '\u25BC';
    return '\u2014';
  }

  trendColor(trend: string, invertGood = false): string {
    if (trend === 'stable') return '#a0aec0';
    const up = invertGood ? '#fc8181' : '#68d391';
    const down = invertGood ? '#68d391' : '#fc8181';
    return trend === 'up' ? up : down;
  }

  rateColor(rate: number): string {
    if (rate > 5) return '#fc8181';
    if (rate > 2) return '#f6ad55';
    return '#68d391';
  }

  formatKostnad(v: number): string {
    if (v >= 1000000) return (v / 1000000).toFixed(1) + ' Mkr';
    if (v >= 1000) return (v / 1000).toFixed(0) + ' tkr';
    return v + ' kr';
  }

  formatDatum(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }
}
