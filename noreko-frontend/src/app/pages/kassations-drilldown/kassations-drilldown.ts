import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KassationsDrilldownService,
  OverviewData,
  ReasonItem,
  ReasonEvent,
  TrendPoint,
} from '../../services/kassations-drilldown.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kassations-drilldown',
  templateUrl: './kassations-drilldown.html',
  styleUrls: ['./kassations-drilldown.css'],
  imports: [CommonModule, FormsModule],
})
export class KassationsDrilldownPage implements OnInit, OnDestroy {
  // -- Period --
  days = 30;
  readonly dayOptions = [
    { value: 7,  label: '7 dagar' },
    { value: 30, label: '30 dagar' },
    { value: 90, label: '90 dagar' },
  ];

  // -- Laddning --
  loadingOverview = false;
  loadingTrend    = false;
  loadingDetail   = false;

  // -- Fel --
  errorOverview = false;
  errorTrend    = false;
  errorDetail   = false;

  // -- Data --
  overview: OverviewData | null = null;
  reasons: ReasonItem[] = [];
  trendData: TrendPoint[] = [];

  // -- Drill-down --
  expandedReasonId: number | null = null;
  expandedReasonName = '';
  detailEvents: ReasonEvent[] = [];

  // -- Charts --
  private reasonChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private reasonChartTimer: ReturnType<typeof setTimeout> | null = null;
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: KassationsDrilldownService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.reasonChartTimer) { clearTimeout(this.reasonChartTimer); this.reasonChartTimer = null; }
    if (this.trendChartTimer) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.reasonChart?.destroy(); } catch (_) {}
    this.reasonChart = null;
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  // =================================================================
  // Period
  // =================================================================

  onDaysChange(d: number): void {
    this.days = d;
    this.expandedReasonId = null;
    this.detailEvents = [];
    this.loadAll();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadOverview();
    this.loadTrend();
  }

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;
    this.svc.getOverview(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) {
          this.overview = res.data;
          this.reasons  = res.data.reasons ?? [];
          if (this.reasonChartTimer) clearTimeout(this.reasonChartTimer);
          this.reasonChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildReasonChart(); }, 0);
        } else {
          this.errorOverview = true;
          this.overview = null;
          this.reasons  = [];
        }
      });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.destroyTrendChart();
    this.svc.getTrend(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data.trend ?? [];
          if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
          this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 0);
        } else {
          this.errorTrend  = true;
          this.trendData   = [];
        }
      });
  }

  // =================================================================
  // Drill-down — klicka på orsak
  // =================================================================

  toggleReason(reason: ReasonItem): void {
    if (this.expandedReasonId === reason.reason_id) {
      this.expandedReasonId = null;
      this.detailEvents = [];
      return;
    }

    this.expandedReasonId   = reason.reason_id;
    this.expandedReasonName = reason.reason;
    this.loadingDetail      = true;
    this.errorDetail        = false;
    this.detailEvents       = [];

    this.svc.getReasonDetail(reason.reason_id, this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetail = false;
        if (res?.success) {
          this.detailEvents = res.data.events ?? [];
        } else {
          this.errorDetail = true;
        }
      });
  }

  // =================================================================
  // Chart.js — Horisontella staplar för orsaker
  // =================================================================

  private destroyReasonChart(): void {
    try { this.reasonChart?.destroy(); } catch (_) {}
    this.reasonChart = null;
  }

  private destroyTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  private buildReasonChart(): void {
    this.destroyReasonChart();
    const canvas = document.getElementById('reasonChart') as HTMLCanvasElement;
    if (!canvas || this.reasons.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.reasons.map(r => r.reason);
    const data   = this.reasons.map(r => r.antal);

    const barColors = this.reasons.map((_, i) => {
      const colors = [
        'rgba(252, 129, 129, 0.85)',
        'rgba(237, 137, 54, 0.85)',
        'rgba(236, 201, 75, 0.85)',
        'rgba(72, 187, 120, 0.85)',
        'rgba(66, 153, 225, 0.85)',
        'rgba(159, 122, 234, 0.85)',
        'rgba(237, 100, 166, 0.85)',
        'rgba(79, 209, 197, 0.85)',
      ];
      return colors[i % colors.length];
    });

    if (this.reasonChart) { (this.reasonChart as any).destroy(); }
    this.reasonChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Antal kasserade',
          data,
          backgroundColor: barColors,
          borderColor: barColors.map(c => c.replace('0.85', '1')),
          borderWidth: 1,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item: any) => ` Antal: ${item.raw}`,
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal kasserade', color: '#a0aec0', font: { size: 11 } },
          },
          y: {
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (_val: any, idx: number) => {
                const lbl = labels[idx] ?? '';
                return lbl.length > 22 ? lbl.substring(0, 20) + '...' : lbl;
              },
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  // =================================================================
  // Chart.js — Linjediagram för kassationstrend
  // =================================================================

  private buildTrendChart(): void {
    this.destroyTrendChart();
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || this.trendData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.trendData.map(t => t.date);
    const grads  = this.trendData.map(t => t.kassationsgrad);
    const kass   = this.trendData.map(t => t.kasserade);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(ctx, {
      data: {
        labels,
        datasets: [
          {
            type: 'bar',
            label: 'Kasserade IBC',
            data: kass,
            backgroundColor: 'rgba(252, 129, 129, 0.5)',
            borderColor: 'rgba(252, 129, 129, 1)',
            borderWidth: 1,
            yAxisID: 'yLeft',
            order: 2,
          } as any,
          {
            type: 'line',
            label: 'Kassationsgrad %',
            data: grads,
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246, 173, 85, 0.15)',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#f6ad55',
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            tension: 0.3,
            fill: false,
            yAxisID: 'yRight',
            order: 1,
          } as any,
        ],
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
          tooltip: {
            callbacks: {
              label: (item: any) => {
                if (item.dataset.label === 'Kasserade IBC') {
                  return ` Kasserade: ${item.raw}`;
                }
                if (item.dataset.label === 'Kassationsgrad %') {
                  return ` Kassationsgrad: ${(item.raw as number).toFixed(2)}%`;
                }
                return item.formattedValue;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxRotation: 45,
              font: { size: 10 },
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal kasserade', color: '#a0aec0', font: { size: 11 } },
          },
          yRight: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => `${v}%`,
            },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kassationsgrad %', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjälpmetoder
  // =================================================================

  get trendArrow(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'up':   return 'fa-arrow-up';
      case 'down': return 'fa-arrow-down';
      default:     return 'fa-minus';
    }
  }

  get trendColor(): string {
    if (!this.overview) return '#a0aec0';
    switch (this.overview.trend_direction) {
      case 'up':   return '#fc8181';  // sämre = röd
      case 'down': return '#68d391';  // bättre = grön
      default:     return '#a0aec0';
    }
  }

  get harReasons(): boolean {
    return this.reasons.length > 0;
  }

  get harTrendData(): boolean {
    return this.trendData.length > 0;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
