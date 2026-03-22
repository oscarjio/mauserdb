import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../../utils/date-utils';
import {
  StopporsakerService,
  SammanfattningData,
  ParetoData,
  PerStationData,
  TrendData,
  OrsakerTabellData,
  DetaljerData,
} from '../../../services/stopporsaker.service';

Chart.register(...registerables);

const COLORS = [
  '#fc8181', '#f6ad55', '#ecc94b', '#68d391', '#63b3ed',
  '#9f7aea', '#f687b3', '#4fd1c5', '#fbd38d', '#b794f4',
];

@Component({
  standalone: true,
  selector: 'app-stopporsaker',
  templateUrl: './stopporsaker.component.html',
  imports: [CommonModule],
  styles: [`
    :host { display: block; color: #e2e8f0; }
    .page-title { color: #e2e8f0; font-size: 1.5rem; font-weight: 600; }
    .period-btn { background: #2d3748; color: #a0aec0; border: 1px solid #4a5568; padding: 0.35rem 0.9rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.85rem; transition: all 0.15s; }
    .period-btn:hover { background: #4a5568; color: #e2e8f0; }
    .period-btn.active { background: #4299e1; color: #fff; border-color: #4299e1; }
    .kpi-card { background: #2d3748; border-radius: 0.5rem; padding: 1.25rem; text-align: center; border: 1px solid #4a5568; }
    .kpi-label { color: #a0aec0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .kpi-value { color: #e2e8f0; font-size: 1.6rem; font-weight: 700; }
    .kpi-sub { color: #718096; font-size: 0.75rem; margin-top: 0.25rem; }
    .kpi-trend-up { color: #fc8181; }
    .kpi-trend-down { color: #68d391; }
    .chart-card { background: #2d3748; border-radius: 0.5rem; padding: 1.25rem; border: 1px solid #4a5568; }
    .chart-card h6 { color: #a0aec0; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .chart-wrapper { position: relative; height: 350px; }
    .chart-wrapper-sm { position: relative; height: 280px; }
    .table-dark-custom { background: #2d3748; border-radius: 0.5rem; overflow: hidden; }
    .table-dark-custom table { margin: 0; color: #e2e8f0; }
    .table-dark-custom th { background: #1a202c; color: #a0aec0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-color: #4a5568; white-space: nowrap; }
    .table-dark-custom td { border-color: #4a5568; font-size: 0.85rem; vertical-align: middle; }
    .table-dark-custom tr:hover td { background: rgba(66,153,225,0.08); }
    .badge-trend-up { background: rgba(252,129,129,0.2); color: #fc8181; }
    .badge-trend-down { background: rgba(104,211,145,0.2); color: #68d391; }
    .badge-trend-flat { background: rgba(160,174,192,0.2); color: #a0aec0; }
    .detail-row { background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 0.5rem; cursor: pointer; transition: background 0.15s; }
    .detail-row:hover { background: #374151; }
    .detail-expanded { background: #1a202c; border: 1px solid #4a5568; border-radius: 0 0 0.5rem 0.5rem; padding: 0.75rem 1rem; margin-top: -0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem; }
    .underhall-badge { background: rgba(79,209,197,0.2); color: #4fd1c5; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 0.25rem; }
    .loading-spinner { display: inline-block; width: 1.5rem; height: 1.5rem; border: 2px solid #4a5568; border-top-color: #63b3ed; border-radius: 50%; animation: spin 0.6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .error-msg { color: #fc8181; font-size: 0.85rem; }
    .section-title { color: #e2e8f0; font-size: 1.1rem; font-weight: 600; }
    .pct-bar { height: 6px; background: #4a5568; border-radius: 3px; overflow: hidden; }
    .pct-bar-fill { height: 100%; border-radius: 3px; }
  `],
})
export class StopporsakerPage implements OnInit, OnDestroy {

  // Period
  period = 30;
  readonly periodOptions = [
    { days: 7,  label: '7 dagar' },
    { days: 14, label: '14 dagar' },
    { days: 30, label: '30 dagar' },
    { days: 90, label: '90 dagar' },
  ];

  // Loading
  loadingSammanfattning = false;
  loadingPareto = false;
  loadingStation = false;
  loadingTrend = false;
  loadingOrsaker = false;
  loadingDetaljer = false;

  // Errors
  errorSammanfattning = false;

  // Data
  sammanfattning: SammanfattningData | null = null;
  paretoData: ParetoData | null = null;
  stationData: PerStationData | null = null;
  trendData: TrendData | null = null;
  orsakerData: OrsakerTabellData | null = null;
  detaljerData: DetaljerData | null = null;

  // Detail expansion
  expandedDetailId: number | null = null;

  // Charts
  private paretoChart: Chart | null = null;
  private stationChart: Chart | null = null;
  private trendChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private paretoChartTimer: ReturnType<typeof setTimeout> | null = null;
  private stationChartTimer: ReturnType<typeof setTimeout> | null = null;
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: StopporsakerService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    if (this.paretoChartTimer !== null) { clearTimeout(this.paretoChartTimer); this.paretoChartTimer = null; }
    if (this.stationChartTimer !== null) { clearTimeout(this.stationChartTimer); this.stationChartTimer = null; }
    if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
  }

  setPeriod(days: number): void {
    this.period = days;
    this.expandedDetailId = null;
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSammanfattning();
    this.loadPareto();
    this.loadPerStation();
    this.loadTrend();
    this.loadOrsaker();
    this.loadDetaljer();
  }

  toggleDetail(id: number): void {
    this.expandedDetailId = this.expandedDetailId === id ? null : id;
  }

  formatDuration(min: number | null): string {
    if (min === null || min === undefined) return 'Pagaende';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
  }

  formatDate(dt: string): string {
    if (!dt) return '-';
    const d = parseLocalDate(dt);
    return d.toLocaleDateString('sv-SE') + ' ' + d.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
  }

  getTrendClass(pct: number): string {
    if (pct > 5) return 'badge-trend-up';
    if (pct < -5) return 'badge-trend-down';
    return 'badge-trend-flat';
  }

  getTrendArrow(pct: number): string {
    if (pct > 5) return 'fas fa-arrow-up';
    if (pct < -5) return 'fas fa-arrow-down';
    return 'fas fa-minus';
  }

  // ---- Data loading ----

  private loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning(this.period).pipe(timeout(15000), catchError(() => { this.errorSammanfattning = true; return of(null); }), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingSammanfattning = false;
      this.isFetching = false;
      if (res?.success) {
        this.sammanfattning = res.data;
      } else {
        this.errorSammanfattning = true;
      }
    });
  }

  private loadPareto(): void {
    this.loadingPareto = true;
    this.svc.getPareto(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPareto = false;
      if (res?.success) {
        this.paretoData = res.data;
        if (this.paretoChartTimer !== null) { clearTimeout(this.paretoChartTimer); }
        this.paretoChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildParetoChart(); }, 100);
      }
    });
  }

  private loadPerStation(): void {
    this.loadingStation = true;
    this.svc.getPerStation(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingStation = false;
      if (res?.success) {
        this.stationData = res.data;
        if (this.stationChartTimer !== null) { clearTimeout(this.stationChartTimer); }
        this.stationChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildStationChart(); }, 100);
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); }
        this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 100);
      }
    });
  }

  private loadOrsaker(): void {
    this.loadingOrsaker = true;
    this.svc.getOrsakerTabell(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOrsaker = false;
      if (res?.success) {
        this.orsakerData = res.data;
      }
    });
  }

  private loadDetaljer(): void {
    this.loadingDetaljer = true;
    this.svc.getDetaljer(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingDetaljer = false;
      if (res?.success) {
        this.detaljerData = res.data;
      }
    });
  }

  // ---- Chart builders ----

  private destroyCharts(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    try { this.stationChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.paretoChart = null;
    this.stationChart = null;
    this.trendChart = null;
  }

  private buildParetoChart(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    this.paretoChart = null;

    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas || !this.paretoData?.pareto?.length) return;

    const items = this.paretoData.pareto;
    const labels = items.map(i => i.orsak);
    const antal = items.map(i => i.antal);
    const kumulativ = items.map(i => i.kumulativ_pct);

    if (this.paretoChart) { (this.paretoChart as any).destroy(); }
    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal stopp',
            data: antal,
            backgroundColor: items.map((_, i) => COLORS[i % COLORS.length]),
            borderRadius: 4,
            yAxisID: 'y',
            order: 2,
          },
          {
            label: 'Kumulativ %',
            data: kumulativ,
            type: 'line',
            borderColor: '#e2e8f0',
            backgroundColor: 'transparent',
            pointBackgroundColor: '#e2e8f0',
            pointRadius: 4,
            borderWidth: 2,
            yAxisID: 'y1',
            order: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                if (ctx.datasetIndex === 0) return `${ctx.parsed.y} stopp`;
                return `${ctx.parsed.y}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            position: 'left',
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
            title: { display: true, text: 'Antal', color: '#718096', font: { size: 11 } },
          },
          y1: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v) => v + '%' },
            grid: { display: false },
            title: { display: true, text: 'Kumulativ %', color: '#718096', font: { size: 11 } },
          },
        },
      },
    });
  }

  private buildStationChart(): void {
    try { this.stationChart?.destroy(); } catch (_) {}
    this.stationChart = null;

    const canvas = document.getElementById('stationChart') as HTMLCanvasElement;
    if (!canvas || !this.stationData?.stationer?.length) return;

    const items = this.stationData.stationer;
    const labels = items.map(s => s.station_namn);
    const data = items.map(s => s.total_min);

    if (this.stationChart) { (this.stationChart as any).destroy(); }
    this.stationChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Stopptid (min)',
          data,
          backgroundColor: items.map((_, i) => COLORS[i % COLORS.length]),
          borderRadius: 4,
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
              label: (ctx) => `${ctx.parsed.x} min`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
            title: { display: true, text: 'Minuter', color: '#718096', font: { size: 11 } },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { display: false },
          },
        },
      },
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData?.dates?.length) return;

    const dates = this.trendData.dates.map(d => {
      const dt = parseLocalDate(d);
      return dt.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
    });

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [
          {
            label: 'Antal stopp',
            data: this.trendData.antal,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2,
            yAxisID: 'y',
          },
          {
            label: 'Stopptid (min)',
            data: this.trendData.minuter,
            borderColor: '#fc8181',
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2,
            borderDash: [5, 3],
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxTicksLimit: 15, maxRotation: 45 },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            position: 'left',
            ticks: { color: '#a0aec0', font: { size: 10 }, stepSize: 1 },
            grid: { color: 'rgba(74,85,104,0.3)' },
            title: { display: true, text: 'Antal', color: '#718096', font: { size: 11 } },
          },
          y1: {
            position: 'right',
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { display: false },
            title: { display: true, text: 'Minuter', color: '#718096', font: { size: 11 } },
          },
        },
      },
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
