import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { Chart } from 'chart.js';
import { localToday, localDateStr } from '../../../../utils/date-utils';
import { exportChartAsPng } from '../../../../shared/chart-export.util';
import { environment } from '../../../../../environments/environment';

interface OrsakItem {
  id: number;
  namn: string;
  antal: number;
  andel: number;
  kumulativ_pct: number;
  prev_antal: number;
  trend: 'up' | 'down' | 'stable';
}

interface BreakdownResponse {
  success: boolean;
  days: number;
  summary: {
    total_ibc: number;
    godkanda: number;
    godkand_pct: number;
    kasserade: number;
    kasserad_pct: number;
    kassation_trend: 'up' | 'down' | 'stable';
    kassation_trend_diff: number;
    prev_kasserad_pct: number;
  };
  orsaker: OrsakItem[];
  has_pareto_data: boolean;
  total_kassation_registrerad: number;
}

interface OrsakMeta {
  id: number;
  namn: string;
  key: string;
}

interface TrendResponse {
  success: boolean;
  days: number;
  orsaker: OrsakMeta[];
  trend: any[];
}

@Component({
  standalone: true,
  selector: 'app-statistik-kvalitet-deepdive',
  templateUrl: './statistik-kvalitet-deepdive.html',
  styleUrls: ['./statistik-kvalitet-deepdive.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikKvalitetDeepdiveComponent implements OnInit, OnDestroy {
  days: number = 30;
  loading: boolean = false;
  trendLoading: boolean = false;

  summary: BreakdownResponse['summary'] | null = null;
  orsaker: OrsakItem[] = [];
  hasParetoData: boolean = false;
  totalKassationRegistrerad: number = 0;

  trendOrsaker: OrsakMeta[] = [];
  trendData: any[] = [];
  exportFeedbackDonut: boolean = false;
  exportFeedbackBar: boolean = false;
  exportFeedbackTrend: boolean = false;

  private donutChart: Chart | null = null;
  private barChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  private readonly COLORS = [
    '#fc8181', '#f6ad55', '#f6e05e', '#68d391', '#4fd1c5',
    '#63b3ed', '#76e4f7', '#b794f4', '#f687b3', '#a0aec0'
  ];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    try { this.donutChart?.destroy(); } catch (e) {}
    try { this.barChart?.destroy(); } catch (e) {}
    try { this.trendChart?.destroy(); } catch (e) {}
    this.donutChart = null;
    this.barChart = null;
    this.trendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  onDaysChange(): void {
    this.loadAll();
  }

  private loadAll(): void {
    this.loadBreakdown();
    this.loadTrend();
  }

  private loadBreakdown(): void {
    this.loading = true;
    this.http.get<BreakdownResponse>(
      `${environment.apiUrl}?action=rebotling&run=quality-rejection-breakdown&days=` + this.days,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res) => {
      this.loading = false;
      if (res?.success) {
        this.summary = res.summary;
        this.orsaker = res.orsaker ?? [];
        this.hasParetoData = res.has_pareto_data;
        this.totalKassationRegistrerad = res.total_kassation_registrerad;
        this._timers.push(setTimeout(() => {
          if (!this.destroy$.closed) {
            this.buildDonutChart();
            this.buildBarChart();
          }
        }, 50));
      }
    });
  }

  private loadTrend(): void {
    this.trendLoading = true;
    this.http.get<TrendResponse>(
      `${environment.apiUrl}?action=rebotling&run=quality-rejection-trend&days=` + this.days,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res) => {
      this.trendLoading = false;
      if (res?.success) {
        this.trendOrsaker = res.orsaker ?? [];
        this.trendData = res.trend ?? [];
        this._timers.push(setTimeout(() => {
          if (!this.destroy$.closed) {
            this.buildTrendChart();
          }
        }, 50));
      }
    });
  }

  get trendArrow(): string {
    if (!this.summary) return '';
    if (this.summary.kassation_trend === 'up') return '\u2191';
    if (this.summary.kassation_trend === 'down') return '\u2193';
    return '\u2192';
  }

  get trendClass(): string {
    if (!this.summary) return '';
    if (this.summary.kassation_trend === 'up') return 'text-danger';
    if (this.summary.kassation_trend === 'down') return 'text-success';
    return 'text-muted';
  }

  get trendLabel(): string {
    if (!this.summary) return '';
    const diff = this.summary.kassation_trend_diff;
    const diffStr = diff > 0 ? '+' + diff : '' + diff;
    if (this.summary.kassation_trend === 'up') return this.trendArrow + ' Stigande ' + diffStr + ' pp';
    if (this.summary.kassation_trend === 'down') return this.trendArrow + ' Sjunkande ' + diffStr + ' pp';
    return this.trendArrow + ' Stabil';
  }

  private getDateRange(): { from: string; to: string } {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (this.days - 1));
    return { from: localDateStr(from), to: localDateStr(to) };
  }

  exportChartDonut(): void {
    const canvas = document.getElementById('kvalitetDeepDiveDonut') as HTMLCanvasElement;
    if (!canvas) return;
    const { from, to } = this.getDateRange();
    exportChartAsPng(canvas, { chartName: 'Kassation per avvisningsorsak - Donut', startDate: from, endDate: to });
    this.exportFeedbackDonut = true;
    this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.exportFeedbackDonut = false; }, 2000));
  }

  exportChartBar(): void {
    const canvas = document.getElementById('kvalitetDeepDiveBar') as HTMLCanvasElement;
    if (!canvas) return;
    const { from, to } = this.getDateRange();
    exportChartAsPng(canvas, { chartName: 'Topp avvisningsorsaker - Pareto', startDate: from, endDate: to });
    this.exportFeedbackBar = true;
    this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.exportFeedbackBar = false; }, 2000));
  }

  exportChartTrend(): void {
    const canvas = document.getElementById('kvalitetDeepDiveTrend') as HTMLCanvasElement;
    if (!canvas) return;
    const { from, to } = this.getDateRange();
    exportChartAsPng(canvas, { chartName: 'Kassationstrend per orsak', startDate: from, endDate: to });
    this.exportFeedbackTrend = true;
    this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.exportFeedbackTrend = false; }, 2000));
  }

  exportCSV(): void {
    if (!this.orsaker.length) return;
    const headers = ['Orsak', 'Antal', 'Andel %', 'Kumulativ %', 'Trend vs fg period'];
    const rows = this.orsaker.map(o => [
      o.namn,
      o.antal,
      o.andel.toFixed(1) + '%',
      o.kumulativ_pct.toFixed(1) + '%',
      o.trend === 'up' ? 'Upp' : o.trend === 'down' ? 'Ner' : 'Stabil'
    ]);
    rows.push(['TOTALT', this.totalKassationRegistrerad, '100.0%', '', '']);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'kassation-deepdive-' + localToday() + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  private buildDonutChart(): void {
    try { this.donutChart?.destroy(); } catch (e) {}
    this.donutChart = null;
    const canvas = document.getElementById('kvalitetDeepDiveDonut') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.orsaker.length || !this.hasParetoData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dataItems = this.orsaker.filter(o => o.antal > 0);
    if (!dataItems.length) return;

    if (this.donutChart) { (this.donutChart as any).destroy(); }
    this.donutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: dataItems.map(o => o.namn),
        datasets: [{
          data: dataItems.map(o => o.antal),
          backgroundColor: dataItems.map((_, i) => this.COLORS[i % this.COLORS.length]),
          borderColor: '#2d3748',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#e2e8f0', font: { size: 11 }, padding: 12 }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            callbacks: {
              label: (ctx: any) => {
                const item = dataItems[ctx.dataIndex];
                return [' ' + item.namn + ': ' + item.antal, ' Andel: ' + item.andel + '%'];
              }
            }
          }
        }
      }
    });
  }

  private buildBarChart(): void {
    try { this.barChart?.destroy(); } catch (e) {}
    this.barChart = null;
    const canvas = document.getElementById('kvalitetDeepDiveBar') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.orsaker.length || !this.hasParetoData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const top10 = this.orsaker.filter(o => o.antal > 0).slice(0, 10);
    if (!top10.length) return;

    const maxVal = Math.max(...top10.map(o => o.antal), 1);

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: top10.map(o => o.namn),
        datasets: [
          {
            label: 'Antal kassationer',
            data: top10.map(o => o.antal),
            backgroundColor: top10.map((o) => {
              const intensity = o.antal / maxVal;
              if (intensity >= 0.8) return 'rgba(252,129,129,0.85)';
              if (intensity >= 0.4) return 'rgba(237,137,54,0.75)';
              return 'rgba(74,85,104,0.7)';
            }),
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            borderRadius: 4
          },
          {
            label: 'Kumulativ %',
            data: top10.map(o => o.kumulativ_pct),
            type: 'line' as any,
            borderColor: '#ed8936',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#ed8936',
            tension: 0.2,
            yAxisID: 'yRight'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) {
                  const item = top10[ctx.dataIndex];
                  return ['Antal: ' + item.antal, 'Andel: ' + item.andel + '%'];
                }
                return 'Kumulativ: ' + (ctx.parsed.x ?? ctx.parsed.y) + '%';
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            title: { display: true, text: 'Antal', color: '#a0aec0' }
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.06)' }
          },
          yRight: {
            position: 'right' as const,
            min: 0,
            max: 100,
            ticks: { color: '#ed8936', callback: (v: any) => v + '%' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kumulativ %', color: '#ed8936' }
          }
        }
      },
      plugins: [{
        id: 'deepdive80Line',
        afterDraw(chart: any) {
          const yR = chart.scales['yRight'];
          const xAx = chart.scales['x'];
          if (!yR || !xAx) return;
          const y80 = yR.getPixelForValue(80);
          const c = chart.ctx;
          c.save();
          c.beginPath();
          c.moveTo(xAx.left, y80);
          c.lineTo(xAx.right, y80);
          c.strokeStyle = '#e53e3e';
          c.lineWidth = 1.5;
          c.setLineDash([6, 4]);
          c.stroke();
          c.setLineDash([]);
          c.fillStyle = '#e53e3e';
          c.font = '11px sans-serif';
          c.fillText('80%', xAx.right - 32, y80 - 5);
          c.restore();
        }
      }]
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;
    const canvas = document.getElementById('kvalitetDeepDiveTrend') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.trendData.length || !this.trendOrsaker.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.trendData.map((d: any) => d.datum.substring(5));
    const datasets = this.trendOrsaker.map((orsak, i) => ({
      label: orsak.namn,
      data: this.trendData.map((d: any) => d[orsak.key] ?? 0),
      borderColor: this.COLORS[i % this.COLORS.length],
      backgroundColor: 'transparent',
      borderWidth: 2,
      pointRadius: 2,
      tension: 0.3,
      fill: false
    }));

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 } }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0'
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#718096' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            title: { display: true, text: 'Antal kassationer', color: '#a0aec0', font: { size: 11 } }
          }
        }
      }
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
