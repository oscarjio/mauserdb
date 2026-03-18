import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  HistoriskSammanfattningService,
  PeriodOption,
  RapportData,
  TrendData,
  OperatorRow,
  StationRow,
  StopporsakRow,
} from '../../services/historisk-sammanfattning.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-historisk-sammanfattning',
  templateUrl: './historisk-sammanfattning.component.html',
  imports: [CommonModule, FormsModule],
})
export class HistoriskSammanfattningPage implements OnInit, OnDestroy {

  // Period selection
  typOptions = [
    { value: 'manad', label: 'Manad' },
    { value: 'kvartal', label: 'Kvartal' },
  ];
  selectedTyp = 'manad';
  selectedPeriod = '';
  manadOptions: PeriodOption[] = [];
  kvartalOptions: PeriodOption[] = [];

  // Loading
  loadingPerioder = false;
  loadingRapport = false;
  loadingTrend = false;
  loadingOperatorer = false;
  loadingStationer = false;
  loadingStopporsaker = false;

  // Errors
  errorPerioder = false;
  errorRapport = false;
  errorTrend = false;
  errorOperatorer = false;
  errorStationer = false;
  errorStopporsaker = false;

  // Data
  rapport: RapportData | null = null;
  trendData: TrendData | null = null;
  operatorer: OperatorRow[] = [];
  stationer: StationRow[] = [];
  stopporsaker: StopporsakRow[] = [];
  stopporsakerTotalH = 0;

  // Charts
  private trendChart: Chart | null = null;
  private paretoChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;
  private paretoChartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: HistoriskSammanfattningService) {}

  ngOnInit(): void {
    this.loadPerioder();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.trendChartTimer) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    if (this.paretoChartTimer) { clearTimeout(this.paretoChartTimer); this.paretoChartTimer = null; }
  }

  private destroyCharts(): void {
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
    if (this.paretoChart) { this.paretoChart.destroy(); this.paretoChart = null; }
  }

  cachedPeriodOptions: PeriodOption[] = [];

  private rebuildPeriodOptions(): void {
    this.cachedPeriodOptions = this.selectedTyp === 'kvartal' ? this.kvartalOptions : this.manadOptions;
  }

  loadPerioder(): void {
    this.loadingPerioder = true;
    this.errorPerioder = false;
    this.svc.getPerioder().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPerioder = false;
      if (res?.success) {
        this.manadOptions = res.data.manader || [];
        this.kvartalOptions = res.data.kvartal || [];
        this.rebuildPeriodOptions();
        // Default: forsta alternativet
        if (this.manadOptions.length > 0) {
          this.selectedPeriod = this.manadOptions[0].value;
        }
        this.loadAll();
      } else {
        this.errorPerioder = true;
      }
    });
  }

  onTypChange(): void {
    this.rebuildPeriodOptions();
    const opts = this.cachedPeriodOptions;
    if (opts.length > 0) {
      this.selectedPeriod = opts[0].value;
    }
    this.loadAll();
  }

  onPeriodChange(): void {
    this.loadAll();
  }

  loadAll(): void {
    if (!this.selectedPeriod) return;
    this.loadRapport();
    this.loadTrend();
    this.loadOperatorer();
    this.loadStationer();
    this.loadStopporsaker();
  }

  private loadRapport(): void {
    this.loadingRapport = true;
    this.errorRapport = false;
    this.svc.getRapport(this.selectedTyp, this.selectedPeriod)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingRapport = false;
        if (res?.success) {
          this.rapport = res.data;
        } else {
          this.errorRapport = true;
        }
      });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getTrend(this.selectedTyp, this.selectedPeriod)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
          this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 100);
        } else {
          this.errorTrend = true;
        }
      });
  }

  private loadOperatorer(): void {
    this.loadingOperatorer = true;
    this.errorOperatorer = false;
    this.svc.getOperatorer(this.selectedTyp, this.selectedPeriod)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingOperatorer = false;
        if (res?.success) {
          this.operatorer = res.data.operatorer || [];
        } else {
          this.errorOperatorer = true;
        }
      });
  }

  private loadStationer(): void {
    this.loadingStationer = true;
    this.errorStationer = false;
    this.svc.getStationer(this.selectedTyp, this.selectedPeriod)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingStationer = false;
        if (res?.success) {
          this.stationer = res.data.stationer || [];
        } else {
          this.errorStationer = true;
        }
      });
  }

  private loadStopporsaker(): void {
    this.loadingStopporsaker = true;
    this.errorStopporsaker = false;
    this.svc.getStopporsaker(this.selectedTyp, this.selectedPeriod)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingStopporsaker = false;
        if (res?.success) {
          this.stopporsaker = res.data.stopporsaker || [];
          this.stopporsakerTotalH = res.data.total_h || 0;
          if (this.paretoChartTimer) clearTimeout(this.paretoChartTimer);
          this.paretoChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildParetoChart(); }, 100);
        } else {
          this.errorStopporsaker = true;
        }
      });
  }

  // ---- Charts ----

  private buildTrendChart(): void {
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
    if (!this.trendData?.trend?.length) return;

    const canvas = document.getElementById('trendChartCanvas') as HTMLCanvasElement;
    if (!canvas) return;

    const labels = this.trendData.trend.map(p => p.datum.substring(5));
    const oeeData = this.trendData.trend.map(p => p.oee_pct);
    const ma7Data = this.trendData.trend.map(p => p.oee_ma7);
    const ibcData = this.trendData.trend.map(p => p.ibc_ok);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oeeData,
            borderColor: '#4fd1c5',
            backgroundColor: 'rgba(79,209,197,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            yAxisID: 'y',
          },
          {
            label: 'OEE 7d snitt',
            data: ma7Data,
            borderColor: '#ecc94b',
            borderDash: [5, 5],
            fill: false,
            tension: 0.3,
            pointRadius: 0,
            yAxisID: 'y',
          },
          {
            label: 'IBC',
            data: ibcData,
            type: 'bar',
            backgroundColor: 'rgba(99,179,237,0.3)',
            borderColor: '#63b3ed',
            borderWidth: 1,
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            position: 'left',
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'OEE %', color: '#a0aec0' },
          },
          y1: {
            position: 'right',
            min: 0,
            ticks: { color: '#a0aec0' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'IBC', color: '#a0aec0' },
          },
        },
      },
    });
  }

  private buildParetoChart(): void {
    if (this.paretoChart) { this.paretoChart.destroy(); this.paretoChart = null; }
    if (!this.stopporsaker?.length) return;

    const canvas = document.getElementById('paretoChartCanvas') as HTMLCanvasElement;
    if (!canvas) return;

    const labels = this.stopporsaker.map(s => s.orsak);
    const barData = this.stopporsaker.map(s => s.total_h);
    const lineData = this.stopporsaker.map(s => s.cumulative_pct);

    if (this.paretoChart) { (this.paretoChart as any).destroy(); }
    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Stopptid (h)',
            data: barData,
            backgroundColor: [
              '#fc8181', '#f6ad55', '#ecc94b', '#68d391', '#63b3ed',
            ],
            borderWidth: 0,
            yAxisID: 'y',
          },
          {
            label: 'Kumulativ %',
            data: lineData,
            type: 'line',
            borderColor: '#e2e8f0',
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#e2e8f0',
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            position: 'left',
            min: 0,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Timmar', color: '#a0aec0' },
          },
          y1: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kumulativ %', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  printReport(): void {
    window.print();
  }

  deltaClass(val: number): string {
    if (val > 0) return 'text-success';
    if (val < 0) return 'text-danger';
    return 'text-warning';
  }

  deltaIcon(val: number): string {
    if (val > 0) return 'bi bi-arrow-up-short';
    if (val < 0) return 'bi bi-arrow-down-short';
    return 'bi bi-dash';
  }

  trendIcon(trend: string): string {
    if (trend === 'up') return 'bi bi-arrow-up-short';
    if (trend === 'down') return 'bi bi-arrow-down-short';
    return 'bi bi-dash';
  }

  trendClass(trend: string): string {
    if (trend === 'up') return 'text-success';
    if (trend === 'down') return 'text-danger';
    return 'text-warning';
  }

  formatNum(val: number): string {
    return val.toLocaleString('sv-SE');
  }

  abs(val: number): number {
    return Math.abs(val);
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? item?.user_id ?? index; }
}
