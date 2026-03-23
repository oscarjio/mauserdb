import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KvalitetstrendanalysService,
  KtaOverviewData,
  StationTrendData,
  PerOperatorData,
  OperatorItem,
  AlarmData,
  HeatmapData,
} from '../../../services/kvalitetstrendanalys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kvalitetstrendanalys',
  templateUrl: './kvalitetstrendanalys.html',
  styleUrls: ['./kvalitetstrendanalys.css'],
  imports: [CommonModule, FormsModule],
})
export class KvalitetstrendanalysPage implements OnInit, OnDestroy {
  // Period
  period = 30;
  readonly periodOptions = [7, 30, 90, 365];

  // Loading
  loadingOverview = false;
  loadingTrend = false;
  loadingOperators = false;
  loadingAlarm = false;
  loadingHeatmap = false;

  // Errors
  errorOverview = false;
  errorTrend = false;
  errorOperators = false;
  errorAlarm = false;
  errorHeatmap = false;

  // Data
  overview: KtaOverviewData | null = null;
  trendData: StationTrendData | null = null;
  operatorData: PerOperatorData | null = null;
  alarmData: AlarmData | null = null;
  heatmapData: HeatmapData | null = null;

  // Station checkboxar
  stationChecked: { [id: number]: boolean } = {};

  // Operator sorting
  operatorSortKey: keyof OperatorItem = 'rate';
  operatorSortAsc = false;

  // Troskelvarden
  warningThreshold = 3;
  criticalThreshold = 5;

  // Charts
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private refreshInterval: any = null;
  private isFetching = false;
  private pendingLoads = 0;

  // Farger for stationer
  readonly stationColors = [
    '#63b3ed', '#68d391', '#ecc94b', '#fc8181', '#9f7aea',
    '#4fd1c5', '#f6ad55', '#f687b3', '#a0aec0', '#b794f4',
  ];

  constructor(private svc: KvalitetstrendanalysService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    this.destroyCharts();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  onPeriodChange(): void {
    this.isFetching = false;
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.pendingLoads = 5;
    this.loadOverview();
    this.loadTrend();
    this.loadOperators();
    this.loadAlarm();
    this.loadHeatmap();
  }

  private onLoadDone(): void {
    this.pendingLoads--;
    if (this.pendingLoads <= 0) {
      this.isFetching = false;
    }
  }

  // ---- Overview ----
  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorOverview = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingOverview = false;
      this.onLoadDone();
      if (res?.success) {
        this.overview = res.data;
      } else if (res !== null) {
        this.errorOverview = true;
      }
    });
  }

  // ---- Per-station trend ----
  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getPerStationTrend(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorTrend = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTrend = false;
      this.onLoadDone();
      if (res?.success) {
        this.trendData = res.data;
        // Init station checkboxar
        if (Object.keys(this.stationChecked).length === 0) {
          for (const s of this.trendData.series) {
            this.stationChecked[s.station_id] = true;
          }
        }
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 100));
      } else if (res !== null) {
        this.errorTrend = true;
      }
    });
  }

  // ---- Per-operator ----
  private loadOperators(): void {
    this.loadingOperators = true;
    this.errorOperators = false;
    this.svc.getPerOperator(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorOperators = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingOperators = false;
      this.onLoadDone();
      if (res?.success) {
        this.operatorData = res.data;
      } else if (res !== null) {
        this.errorOperators = true;
      }
    });
  }

  // ---- Alarm ----
  loadAlarm(): void {
    this.loadingAlarm = true;
    this.errorAlarm = false;
    this.svc.getAlarm(this.period, this.warningThreshold, this.criticalThreshold).pipe(
      timeout(15000),
      catchError(() => { this.errorAlarm = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingAlarm = false;
      this.onLoadDone();
      if (res?.success) {
        this.alarmData = res.data;
      } else if (res !== null) {
        this.errorAlarm = true;
      }
    });
  }

  // ---- Heatmap ----
  private loadHeatmap(): void {
    this.loadingHeatmap = true;
    this.errorHeatmap = false;
    this.svc.getHeatmap(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorHeatmap = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingHeatmap = false;
      this.onLoadDone();
      if (res?.success) {
        this.heatmapData = res.data;
      } else if (res !== null) {
        this.errorHeatmap = true;
      }
    });
  }

  // ---- Station checkbox toggle ----
  onStationToggle(): void {
    this.buildTrendChart();
  }

  // ---- Operator sorting ----
  sortOperators(key: keyof OperatorItem): void {
    if (this.operatorSortKey === key) {
      this.operatorSortAsc = !this.operatorSortAsc;
    } else {
      this.operatorSortKey = key;
      this.operatorSortAsc = false;
    }
  }

  get sortedOperators(): OperatorItem[] {
    if (!this.operatorData?.operators) return [];
    const ops = [...this.operatorData.operators];
    const dir = this.operatorSortAsc ? 1 : -1;
    ops.sort((a, b) => {
      const av = a[this.operatorSortKey];
      const bv = b[this.operatorSortKey];
      if (typeof av === 'string' && typeof bv === 'string') return av.localeCompare(bv) * dir;
      return ((av as number) - (bv as number)) * dir;
    });
    return ops;
  }

  getSortIcon(key: string): string {
    if (this.operatorSortKey !== key) return 'fas fa-sort text-muted';
    return this.operatorSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  // ---- Charts ----
  private destroyCharts(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  private buildTrendChart(): void {
    this.destroyCharts();
    if (!this.trendData?.series?.length) return;

    const canvas = document.getElementById('stationTrendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const labels = this.trendData.dates.map(d => {
      const parts = d.split('-');
      return parts[1] + '-' + parts[2];
    });

    const datasets = this.trendData.series
      .filter(s => this.stationChecked[s.station_id])
      .map((s, i) => ({
        label: s.station,
        data: s.values,
        borderColor: this.stationColors[i % this.stationColors.length],
        backgroundColor: 'transparent',
        borderWidth: 2,
        fill: false,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.3,
        spanGaps: true,
      }));

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => `${ctx.dataset.label}: ${ctx.parsed.y != null ? ctx.parsed.y.toFixed(2) + '%' : 'N/A'}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 20 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Kassationsrate %', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Heatmap helpers ----
  getHeatmapColor(value: number | null): string {
    if (value === null) return '#2d3748';
    if (value <= 1) return '#276749';
    if (value <= 2) return '#38a169';
    if (value <= 3) return '#68d391';
    if (value <= 4) return '#ecc94b';
    if (value <= 5) return '#dd6b20';
    if (value <= 7) return '#e53e3e';
    return '#9b2c2c';
  }

  getHeatmapTextColor(value: number | null): string {
    if (value === null) return '#4a5568';
    if (value <= 3) return '#1a202c';
    return '#fff';
  }

  // ---- Trend helpers ----
  getTrendIcon(direction: string): string {
    switch (direction) {
      case 'down': return 'fas fa-arrow-down text-success';
      case 'up':   return 'fas fa-arrow-up text-danger';
      default:     return 'fas fa-minus text-muted';
    }
  }

  getAlarmBadgeClass(niva: string): string {
    return niva === 'kritisk' ? 'badge bg-danger' : 'badge bg-warning text-dark';
  }

  getTypBadge(typ: string): string {
    return typ === 'station' ? 'badge bg-info' : 'badge bg-primary';
  }

  abs(val: number): number {
    return Math.abs(val);
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
