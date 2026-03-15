import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';

import {
  StatistikDashboardService,
  DashboardSummary,
  ProductionTrendData,
  ProductionTrendItem,
  DailyTableData,
  DailyTableRow,
  StatusIndicator,
} from '../../../services/statistik-dashboard.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-statistik-dashboard',
  templateUrl: './statistik-dashboard.component.html',
  styleUrls: ['./statistik-dashboard.component.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikDashboardPage implements OnInit, OnDestroy {

  // ---- Period ----
  trendPeriod = 30;
  readonly periodOptions = [
    { val: 7,  label: '7 dagar'  },
    { val: 14, label: '14 dagar' },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Laddning ----
  loadingSummary   = false;
  loadingTrend     = false;
  loadingTable     = false;
  loadingStatus    = false;

  // ---- Fel ----
  errorSummary   = false;
  errorTrend     = false;
  errorTable     = false;
  errorStatus    = false;

  // ---- Data ----
  summary:   DashboardSummary | null       = null;
  trendData: ProductionTrendData | null    = null;
  tableData: DailyTableData | null         = null;
  status:    StatusIndicator | null        = null;

  // ---- Senaste uppdatering ----
  lastUpdated: string | null = null;

  // ---- Chart ----
  private trendChart: Chart | null = null;

  // ---- Lifecycle ----
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private chartBuildTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: StatistikDashboardService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.chartBuildTimer) {
      clearTimeout(this.chartBuildTimer);
      this.chartBuildTimer = null;
    }
    this.destroyChart();
  }

  // ================================================================
  // LOAD
  // ================================================================

  loadAll(): void {
    this.loadSummary();
    this.loadTrend();
    this.loadTable();
    this.loadStatus();
    this.lastUpdated = new Date().toLocaleTimeString('sv-SE');
  }

  onPeriodChange(): void {
    this.loadTrend();
  }

  private loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getSummary().pipe(
      timeout(15000),
      catchError(() => { this.errorSummary = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingSummary = false;
      if (res?.success) {
        this.summary = res.data;
      } else if (res !== null) {
        this.errorSummary = true;
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.svc.getProductionTrend(this.trendPeriod).pipe(
      timeout(15000),
      catchError(() => { this.errorTrend = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        if (this.chartBuildTimer) clearTimeout(this.chartBuildTimer);
        this.chartBuildTimer = setTimeout(() => this.buildChart(), 100);
      } else if (res !== null) {
        this.errorTrend = true;
      }
    });
  }

  private loadTable(): void {
    this.loadingTable = true;
    this.errorTable   = false;
    this.svc.getDailyTable().pipe(
      timeout(15000),
      catchError(() => { this.errorTable = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTable = false;
      if (res?.success) {
        this.tableData = res.data;
      } else if (res !== null) {
        this.errorTable = true;
      }
    });
  }

  private loadStatus(): void {
    this.loadingStatus = true;
    this.errorStatus   = false;
    this.svc.getStatusIndicator().pipe(
      timeout(15000),
      catchError(() => { this.errorStatus = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingStatus = false;
      if (res?.success) {
        this.status = res.data;
      } else if (res !== null) {
        this.errorStatus = true;
      }
    });
  }

  // ================================================================
  // CHART
  // ================================================================

  private destroyChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  private buildChart(): void {
    this.destroyChart();
    if (!this.trendData?.daily?.length) return;

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const daily     = this.trendData.daily;
    const labels    = daily.map(d => d.datum);
    const ibcData   = daily.map(d => d.total);
    const kassData  = daily.map(d => d.kassation_pct);
    const snittLine = daily.map(() => this.trendData!.snitt_ibc_dag);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC totalt',
            data: ibcData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.15)',
            borderWidth: 2,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 7,
            tension: 0.3,
            yAxisID: 'yLeft',
          },
          {
            label: 'Snitt IBC/dag',
            data: snittLine,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [6, 4],
            fill: false,
            pointRadius: 0,
            yAxisID: 'yLeft',
          },
          {
            label: 'Kassation %',
            data: kassData,
            borderColor: '#fc8181',
            backgroundColor: 'rgba(252,129,129,0.08)',
            borderWidth: 2,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 6,
            tension: 0.3,
            yAxisID: 'yRight',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event, elements) => {
          if (elements.length > 0) {
            const idx   = elements[0].index;
            const item  = daily[idx];
            if (item) this.showTrendTooltip(item);
          }
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                if (ctx.datasetIndex === 2) {
                  return ` Kassation: ${ctx.parsed.y}%`;
                }
                return ` ${ctx.dataset.label}: ${ctx.parsed.y}`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'IBC (antal)', color: '#63b3ed' },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          yRight: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Kassation %', color: '#fc8181' },
            ticks: {
              color: '#fc8181',
              callback: (val: any) => `${val}%`,
            },
            grid: { drawOnChartArea: false },
            beginAtZero: true,
            min: 0,
          },
        },
      },
    });
  }

  // ================================================================
  // HELPERS
  // ================================================================

  tooltipItem: ProductionTrendItem | null = null;

  showTrendTooltip(item: ProductionTrendItem): void {
    this.tooltipItem = item;
  }

  closeTrendTooltip(): void {
    this.tooltipItem = null;
  }

  getStatusClass(): string {
    if (!this.status) return 'status-grön';
    return 'status-' + this.status.status;
  }

  getStatusBgClass(): string {
    if (!this.status) return 'bg-success';
    switch (this.status.status) {
      case 'röd':  return 'bg-danger';
      case 'gul':  return 'bg-warning';
      default:     return 'bg-success';
    }
  }

  getDiffClass(current: number, previous: number, invertiert = false): string {
    if (current === previous) return 'text-muted';
    const better = invertiert ? current < previous : current > previous;
    return better ? 'text-success' : 'text-danger';
  }

  getDiffIcon(current: number, previous: number, invertiert = false): string {
    if (current === previous) return 'fas fa-minus';
    const better = invertiert ? current < previous : current > previous;
    return better ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
  }

  getDiffValue(current: number, previous: number): string {
    const diff = current - previous;
    return diff >= 0 ? `+${diff}` : `${diff}`;
  }

  getDiffPct(current: number, previous: number): string {
    if (previous === 0) return '';
    const diff = ((current - previous) / previous * 100);
    return diff >= 0 ? `+${diff.toFixed(1)}%` : `${diff.toFixed(1)}%`;
  }

  getRowClass(row: DailyTableRow): string {
    switch (row.fargklass) {
      case 'röd':  return 'table-danger-row';
      case 'gul':  return 'table-warning-row';
      default:     return 'table-success-row';
    }
  }

  getKassationBadgeClass(pct: number): string {
    if (pct < 5)   return 'badge-grön';
    if (pct <= 10) return 'badge-gul';
    return 'badge-röd';
  }

  ibcPerHVsMal(ibcH: number): string {
    if (!this.summary) return 'text-muted';
    const mal = this.summary.mal_ibc_per_h;
    if (ibcH >= mal)           return 'text-success';
    if (ibcH >= mal * 0.85)    return 'text-warning';
    return 'text-danger';
  }

  formatDatum(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum);
    const dagar = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${dagar[d.getDay()]} ${datum}`;
  }
  trackByIndex(index: number): number { return index; }
}
