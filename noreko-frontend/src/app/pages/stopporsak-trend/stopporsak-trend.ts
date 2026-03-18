import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  StopporsakTrendService,
  WeeklyData,
  WeeklyRow,
  SummaryData,
  SummaryRow,
  DetailData,
} from '../../services/stopporsak-trend.service';

Chart.register(...registerables);

interface Period { val: number; label: string; }

@Component({
  standalone: true,
  selector: 'app-stopporsak-trend',
  templateUrl: './stopporsak-trend.html',
  styleUrls: ['./stopporsak-trend.css'],
  imports: [CommonModule, FormsModule],
})
export class StopporsakTrendComponent implements OnInit, OnDestroy {
  Math = Math;

  // ---- Perioder ----
  selectedWeeks: number = 12;
  perioder: Period[] = [
    { val: 4,  label: '4 veckor'  },
    { val: 8,  label: '8 veckor'  },
    { val: 12, label: '12 veckor' },
    { val: 26, label: '26 veckor' },
  ];

  // ---- Laddningsstatus ----
  weeklyLoading  = false;
  weeklyLoaded   = false;
  weeklyError    = false;
  summaryLoading = false;
  summaryLoaded  = false;
  summaryError   = false;
  detailLoading  = false;

  // ---- Data ----
  weeklyData: WeeklyData | null  = null;
  summaryData: SummaryData | null = null;
  detailData: DetailData | null   = null;
  selectedReason: string | null   = null;

  lastRefreshed: Date | null = null;

  private destroy$          = new Subject<void>();
  private trendChart: Chart | null  = null;
  private detailChart: Chart | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null       = null;
  private detailChartTimer: ReturnType<typeof setTimeout> | null = null;

  // Palettkonstant — konsekvent färg per orsak
  private readonly COLORS = [
    '#4299e1', '#48bb78', '#ecc94b', '#ed8936', '#e53e3e',
    '#9f7aea', '#38b2ac', '#f687b3', '#68d391', '#fc8181',
  ];

  constructor(private service: StopporsakTrendService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer)       { clearTimeout(this.chartTimer);       this.chartTimer = null; }
    if (this.detailChartTimer) { clearTimeout(this.detailChartTimer); this.detailChartTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.trendChart?.destroy(); }  catch (_) {}
    try { this.detailChart?.destroy(); } catch (_) {}
    this.trendChart  = null;
    this.detailChart = null;
  }

  // ================================================================
  // DATAINLÄSNING
  // ================================================================

  setPeriod(w: number): void {
    if (this.selectedWeeks === w) return;
    this.selectedWeeks = w;
    this.weeklyLoaded  = false;
    this.summaryLoaded = false;
    this.weeklyData    = null;
    this.summaryData   = null;
    this.detailData    = null;
    this.selectedReason = null;
    this.loadAll();
  }

  private loadAll(): void {
    this.loadWeekly();
    this.loadSummary();
  }

  private loadWeekly(): void {
    if (this.weeklyLoading) return;
    this.weeklyLoading = true;
    this.weeklyError   = false;

    this.service.getWeekly(this.selectedWeeks)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.weeklyLoading = false;
        this.weeklyLoaded  = true;
        if (res?.success) {
          this.weeklyData  = res.data;
          this.weeklyError = false;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderTrendChart();
          }, 150);
        } else {
          this.weeklyData  = null;
          this.weeklyError = true;
        }
        this.lastRefreshed = new Date();
      });
  }

  private loadSummary(): void {
    if (this.summaryLoading) return;
    this.summaryLoading = true;
    this.summaryError   = false;

    this.service.getSummary(this.selectedWeeks)
      .pipe(takeUntil(this.destroy$))
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
      });
  }

  loadDetail(reason: string): void {
    if (this.selectedReason === reason && this.detailData) return;
    this.selectedReason = reason;
    this.detailData     = null;
    this.detailLoading  = true;

    // Förstör befintlig detaljgraf
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;

    this.service.getDetail(reason, this.selectedWeeks)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.detailLoading = false;
        if (res?.success) {
          this.detailData = res.data;
          if (this.detailChartTimer) clearTimeout(this.detailChartTimer);
          this.detailChartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderDetailChart();
          }, 150);
        }
      });
  }

  closeDetail(): void {
    this.selectedReason = null;
    this.detailData     = null;
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;
  }

  // ================================================================
  // CHART: Grouped bar chart — X = veckor, Y = antal stopp, serie per orsak
  // ================================================================

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('stopporsakTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.weeklyData) return;

    const { veckor, top_reasons } = this.weeklyData;
    if (!veckor.length || !top_reasons.length) return;

    const labels = veckor.map(v => v.week_label);

    const datasets = top_reasons.map((rsn, idx) => {
      const color = this.COLORS[idx % this.COLORS.length];
      const data  = veckor.map(v => {
        const r = v.reasons.find(r => r.reason === rsn);
        return r ? r.count : 0;
      });
      return {
        label: rsn,
        data,
        backgroundColor: color + 'cc',
        borderColor: color,
        borderWidth: 1,
        borderRadius: 3,
        stack: 'stopp',
      };
    });

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets },
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
                if (v === 0) return '';
                return ` ${ctx.dataset.label}: ${v} stopp`;
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              stepSize: 1,
              callback: (val: any) => `${val}`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Antal stopp',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // CHART: Detaljgraf — tidsserie för en orsak
  // ================================================================

  private renderDetailChart(): void {
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;

    const canvas = document.getElementById('stopporsakDetailChart') as HTMLCanvasElement;
    if (!canvas || !this.detailData) return;

    const { tidslinje, reason } = this.detailData;
    const labels = tidslinje.map(r => r.week_label);
    const counts = tidslinje.map(r => r.count);

    // Välj en konsekvent färg baserat på orsakens position i top_reasons
    const idx   = this.weeklyData?.top_reasons.indexOf(reason) ?? 0;
    const color = this.COLORS[Math.max(0, idx) % this.COLORS.length];

    if (this.detailChart) { (this.detailChart as any).destroy(); }
    this.detailChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: reason,
            data: counts,
            borderColor: color,
            backgroundColor: color + '20',
            fill: true,
            tension: 0.35,
            pointRadius: 5,
            pointBackgroundColor: color,
            borderWidth: 2.5,
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} stopp`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              stepSize: 1,
              callback: (val: any) => `${val}`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Antal stopp',
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

  getTrendKlass(trend: string): string {
    if (trend === 'increasing') return 'trend-up';
    if (trend === 'decreasing') return 'trend-down';
    return 'trend-flat';
  }

  getTrendPil(trend: string): string {
    if (trend === 'increasing') return '↑';
    if (trend === 'decreasing') return '↓';
    return '→';
  }

  getTrendBadgeKlass(trend: string): string {
    if (trend === 'increasing') return 'badge-okar';
    if (trend === 'decreasing') return 'badge-minskar';
    return 'badge-stabil';
  }

  getTrendBadgeLabel(trend: string): string {
    if (trend === 'increasing') return 'Ökar';
    if (trend === 'decreasing') return 'Minskar';
    return 'Stabil';
  }

  formatForandring(pct: number): string {
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  formatMinuter(min: number): string {
    if (min === 0) return '–';
    if (min < 60) return `${Math.round(min)} min`;
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  formatHmm(totalMinutes: number): string {
    if (totalMinutes === 0) return '0:00';
    const h = Math.floor(totalMinutes / 60);
    const m = Math.round(totalMinutes % 60);
    return `${h}:${String(m).padStart(2, '0')}`;
  }

  getReasonColor(reason: string): string {
    const idx = this.weeklyData?.top_reasons.indexOf(reason) ?? -1;
    if (idx < 0) return '#718096';
    return this.COLORS[idx % this.COLORS.length];
  }

  getSparkdata(summary: SummaryRow): (number | null)[] {
    if (!this.weeklyData) return [];
    const { veckor } = this.weeklyData;
    const sista6 = veckor.slice(-6);
    return sista6.map(v => {
      const r = v.reasons.find(r => r.reason === summary.reason);
      return r ? r.count : 0;
    });
  }

  getSparkDotKlass(val: number | null, avgVal: number): string {
    if (val === null) return 'spark-null';
    if (val === 0)    return 'spark-good';
    if (val <= avgVal * 0.8) return 'spark-good';
    if (val <= avgVal * 1.2) return 'spark-warn';
    return 'spark-bad';
  }

  getPeriodLabel(): string {
    const p = this.perioder.find(p => p.val === this.selectedWeeks);
    return p ? p.label : `${this.selectedWeeks} veckor`;
  }

  get sistaVeckaLabel(): string {
    if (!this.weeklyData?.veckonycklar.length) return '';
    const last = this.weeklyData.veckonycklar[this.weeklyData.veckonycklar.length - 1];
    return last.replace(/(\d{4})-W(\d+)/, (_, y, w) => `V${parseInt(w, 10)} ${y}`);
  }

  trackByReason(_: number, row: SummaryRow): string {
    return row.reason;
  }

  trackByWeek(_: number, row: WeeklyRow): string {
    return row.week;
  }
  trackByIndex(index: number): number { return index; }
}
