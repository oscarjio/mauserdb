import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  EffektivitetService,
  TrendData,
  TrendRad,
  SummaryData,
  SkiftData,
} from '../../services/effektivitet.service';
import { parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-effektivitet',
  templateUrl: './effektivitet.html',
  styleUrls: ['./effektivitet.css'],
  imports: [CommonModule],
})
export class EffektivitetComponent implements OnInit, OnDestroy {
  Math = Math;

  // ---- Perioder ----
  selectedDays: 7 | 14 | 30 | 90 = 30;
  perioder: { val: 7 | 14 | 30 | 90; label: string }[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 14, label: '14 dagar' },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Laddningsstate ----
  summaryLoading = false;
  summaryLoaded  = false;
  summaryError   = false;
  trendLoading   = false;
  trendLoaded    = false;
  trendError     = false;
  skiftLoading   = false;
  skiftLoaded    = false;

  // ---- Data ----
  summaryData: SummaryData | null = null;
  trendData: TrendData | null     = null;
  skiftData: SkiftData | null     = null;

  lastRefreshed: Date | null = null;

  private destroy$   = new Subject<void>();
  private trendChart: Chart | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: EffektivitetService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  // ================================================================
  // DATA LOADING
  // ================================================================

  setPeriod(days: 7 | 14 | 30 | 90): void {
    if (this.selectedDays === days) return;
    this.selectedDays = days;
    this.trendLoaded  = false;
    this.skiftLoaded  = false;
    this.trendData    = null;
    this.skiftData    = null;
    this.loadTrend();
    this.loadSkift();
  }

  private loadAll(): void {
    this.loadSummary();
    this.loadTrend();
    this.loadSkift();
  }

  private loadSummary(): void {
    if (this.summaryLoading) return;
    this.summaryLoading = true;
    this.summaryError   = false;

    this.service.getSummary()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.summaryLoading = false;
        this.summaryLoaded  = true;
        if (res?.success) {
          this.summaryData  = res.data;
          this.summaryError = false;
        } else {
          this.summaryData  = null;
          this.summaryError = true;
        }
        this.lastRefreshed = new Date();
      });
  }

  private loadTrend(): void {
    if (this.trendLoading) return;
    this.trendLoading = true;
    this.trendError   = false;

    this.service.getTrend(this.selectedDays)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.trendLoading = false;
        this.trendLoaded  = true;
        if (res?.success) {
          this.trendData  = res.data;
          this.trendError = false;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderTrendChart();
          }, 150);
        } else {
          this.trendData  = null;
          this.trendError = true;
        }
      });
  }

  private loadSkift(): void {
    if (this.skiftLoading) return;
    this.skiftLoading = true;

    this.service.getByShift(this.selectedDays)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.skiftLoading = false;
        this.skiftLoaded  = true;
        if (res?.success) {
          this.skiftData = res.data;
        } else {
          this.skiftData = null;
        }
      });
  }

  // ================================================================
  // CHART: Trendgraf
  // ================================================================

  private renderTrendChart(): void {
    this.destroyChart();

    const canvas = document.getElementById('effektivitetTrendChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.trendData) return;

    const { trend, snitt_30d } = this.trendData;

    const labels = trend.map(r => this.formatDatumKort(r.date));
    const dagliga = trend.map(r => r.ibc_per_hour);
    const glidande = trend.map(r => r.moving_avg_7d);
    const snittLinje = trend.map(() => snitt_30d);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h (daglig)',
            data: dagliga,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.08)',
            fill: true,
            tension: 0.2,
            pointRadius: 3,
            pointBackgroundColor: '#4299e1',
            borderWidth: 1.5,
            spanGaps: false,
          },
          {
            label: '7-dagars glidande medel',
            data: glidande,
            borderColor: '#ecc94b',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 3,
            spanGaps: true,
          },
          {
            label: `${this.selectedDays}-dagarssnitt (${snitt_30d !== null ? snitt_30d.toFixed(1) : '–'} IBC/h)`,
            data: snittLinje,
            borderColor: '#68d391',
            backgroundColor: 'transparent',
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            borderDash: [6, 4],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)} IBC/h`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              font: { size: 10 },
              maxTicksLimit: 15,
              maxRotation: 45,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => `${val} IBC/h`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC per drifttimme',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getTrendLabel(): string {
    if (!this.summaryData) return '–';
    switch (this.summaryData.trend) {
      case 'improving': return 'Förbättras';
      case 'declining': return 'Försämras';
      default:          return 'Stabilt';
    }
  }

  getTrendPil(): string {
    if (!this.summaryData) return '→';
    switch (this.summaryData.trend) {
      case 'improving': return '↑';
      case 'declining': return '↓';
      default:          return '→';
    }
  }

  getTrendKlass(): string {
    if (!this.summaryData) return 'trend-stable';
    switch (this.summaryData.trend) {
      case 'improving': return 'trend-up';
      case 'declining': return 'trend-down';
      default:          return 'trend-stable';
    }
  }

  getTrendBadgeKlass(): string {
    if (!this.summaryData) return 'badge-stable';
    switch (this.summaryData.trend) {
      case 'improving': return 'badge-forbattras';
      case 'declining': return 'badge-forsamras';
      default:          return 'badge-stable';
    }
  }

  getChangeText(): string {
    const pct = this.summaryData?.change_pct ?? null;
    if (pct === null) return '';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}% vs föregående 7d`;
  }

  formatIbcH(v: number | null): string {
    if (v === null || v === undefined) return '–';
    return v.toFixed(1);
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '–';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return '–';
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatDatumKort(datum: string): string {
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { month: 'numeric', day: 'numeric' });
  }

  getAvvikelsePct(ibcH: number | null): number | null {
    const snitt = this.trendData?.snitt_30d ?? null;
    if (ibcH === null || snitt === null || snitt === 0) return null;
    return Math.round((ibcH - snitt) / snitt * 100 * 10) / 10;
  }

  getAvvikelseKlass(ibcH: number | null): string {
    const pct = this.getAvvikelsePct(ibcH);
    if (pct === null) return 'text-muted';
    if (pct > 5)  return 'text-success';
    if (pct < -5) return 'text-danger';
    return 'text-muted';
  }

  formatAvvikelse(ibcH: number | null): string {
    const pct = this.getAvvikelsePct(ibcH);
    if (pct === null) return '–';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  getSkiftIkonFarg(ar_bast: boolean): string {
    return ar_bast ? 'color: #48bb78;' : 'color: #a0aec0;';
  }

  get dagligaTabelRader(): TrendRad[] {
    if (!this.trendData) return [];
    // Visa bara dagar med produktion, senast först
    return [...this.trendData.trend]
      .filter(r => r.ibc_count > 0)
      .reverse();
  }

  isLoading(): boolean {
    return this.summaryLoading || this.trendLoading;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
