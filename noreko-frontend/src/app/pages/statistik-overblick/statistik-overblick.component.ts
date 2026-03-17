import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  StatistikOverblickService,
  KpiData,
  WeeklyChartData,
} from '../../services/statistik-overblick.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-statistik-overblick',
  templateUrl: './statistik-overblick.component.html',
  styleUrls: ['./statistik-overblick.component.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikOverblickPage implements OnInit, OnDestroy {
  // Period
  months = 3;
  readonly monthOptions = [3, 6, 12];

  // Loading
  loadingKpi = false;
  loadingProduktion = false;
  loadingOee = false;
  loadingKassation = false;

  // Errors
  errorKpi = false;
  errorProduktion = false;
  errorOee = false;
  errorKassation = false;

  // Data
  kpi: KpiData | null = null;

  // Charts
  private produktionChart: Chart | null = null;
  private oeeChart: Chart | null = null;
  private kassationChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private produktionChartTimer: ReturnType<typeof setTimeout> | null = null;
  private oeeChartTimer: ReturnType<typeof setTimeout> | null = null;
  private kassationChartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: StatistikOverblickService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshInterval) { clearInterval(this.refreshInterval); this.refreshInterval = null; }
    if (this.produktionChartTimer) { clearTimeout(this.produktionChartTimer); this.produktionChartTimer = null; }
    if (this.oeeChartTimer) { clearTimeout(this.oeeChartTimer); this.oeeChartTimer = null; }
    if (this.kassationChartTimer) { clearTimeout(this.kassationChartTimer); this.kassationChartTimer = null; }
  }

  onPeriodChange(): void {
    this.loadCharts();
  }

  loadAll(): void {
    this.loadKpi();
    this.loadCharts();
  }

  private loadCharts(): void {
    this.loadProduktion();
    this.loadOee();
    this.loadKassation();
  }

  // ---- KPI ----
  private loadKpi(): void {
    this.loadingKpi = true;
    this.errorKpi = false;
    this.svc.getKpi().pipe(
      timeout(15000),
      catchError(() => { this.errorKpi = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingKpi = false;
      if (res?.success) {
        this.kpi = res.data;
      } else if (res !== null) {
        this.errorKpi = true;
      }
    });
  }

  // ---- Produktion per vecka ----
  private loadProduktion(): void {
    this.loadingProduktion = true;
    this.errorProduktion = false;
    this.svc.getProduktion(this.months).pipe(
      timeout(15000),
      catchError(() => { this.errorProduktion = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingProduktion = false;
      if (res?.success) {
        if (this.produktionChartTimer) clearTimeout(this.produktionChartTimer);
        this.produktionChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildProduktionChart(res.data); }, 100);
      } else if (res !== null) {
        this.errorProduktion = true;
      }
    });
  }

  // ---- OEE per vecka ----
  private loadOee(): void {
    this.loadingOee = true;
    this.errorOee = false;
    this.svc.getOee(this.months).pipe(
      timeout(15000),
      catchError(() => { this.errorOee = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingOee = false;
      if (res?.success) {
        if (this.oeeChartTimer) clearTimeout(this.oeeChartTimer);
        this.oeeChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildOeeChart(res.data); }, 100);
      } else if (res !== null) {
        this.errorOee = true;
      }
    });
  }

  // ---- Kassation per vecka ----
  private loadKassation(): void {
    this.loadingKassation = true;
    this.errorKassation = false;
    this.svc.getKassation(this.months).pipe(
      timeout(15000),
      catchError(() => { this.errorKassation = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingKassation = false;
      if (res?.success) {
        if (this.kassationChartTimer) clearTimeout(this.kassationChartTimer);
        this.kassationChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildKassationChart(res.data); }, 100);
      } else if (res !== null) {
        this.errorKassation = true;
      }
    });
  }

  // ---- Chart builders ----

  private destroyCharts(): void {
    try { this.produktionChart?.destroy(); } catch (_) {}
    try { this.oeeChart?.destroy(); } catch (_) {}
    try { this.kassationChart?.destroy(); } catch (_) {}
    this.produktionChart = null;
    this.oeeChart = null;
    this.kassationChart = null;
  }

  private buildProduktionChart(data: WeeklyChartData): void {
    try { this.produktionChart?.destroy(); } catch (_) {}
    const canvas = document.getElementById('produktionChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.produktionChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'IBC per vecka',
          data: data.values,
          backgroundColor: '#4299e1',
          borderColor: '#3182ce',
          borderWidth: 1,
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => `${ctx.parsed.y} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Antal IBC', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  private buildOeeChart(data: WeeklyChartData): void {
    try { this.oeeChart?.destroy(); } catch (_) {}
    const canvas = document.getElementById('oeeChart') as HTMLCanvasElement;
    if (!canvas) return;

    const malValue = data.mal ?? 65;
    const malLine = data.labels.map(() => malValue);

    this.oeeChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: 'OEE %',
            data: data.values,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72, 187, 120, 0.1)',
            borderWidth: 2,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 6,
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: `Mal ${malValue}%`,
            data: malLine,
            borderColor: '#fc8181',
            borderWidth: 2,
            borderDash: [8, 4],
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 0,
          },
        ],
      },
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
              label: (ctx: any) => `${ctx.dataset.label}: ${ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) + '%' : 'N/A'}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'OEE %', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            beginAtZero: true,
            max: 100,
          },
        },
      },
    });
  }

  private buildKassationChart(data: WeeklyChartData): void {
    try { this.kassationChart?.destroy(); } catch (_) {}
    const canvas = document.getElementById('kassationChart') as HTMLCanvasElement;
    if (!canvas) return;

    const troskelValue = data.troskel ?? 3;
    const troskelLine = data.labels.map(() => troskelValue);

    this.kassationChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: 'Kassation %',
            data: data.values,
            borderColor: '#ecc94b',
            backgroundColor: 'rgba(236, 201, 75, 0.1)',
            borderWidth: 2,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 6,
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: `Troskel ${troskelValue}%`,
            data: troskelLine,
            borderColor: '#fc8181',
            borderWidth: 2,
            borderDash: [8, 4],
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 0,
          },
        ],
      },
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
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Kassationsrate %', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Helpers ----
  abs(val: number): number {
    return Math.abs(val);
  }

  getTrendClass(val: number, inverted: boolean = false): string {
    if (val > 0.01) return inverted ? 'text-danger' : 'text-success';
    if (val < -0.01) return inverted ? 'text-success' : 'text-danger';
    return 'text-muted';
  }

  getTrendIcon(val: number): string {
    if (val > 0.01) return 'fas fa-arrow-up';
    if (val < -0.01) return 'fas fa-arrow-down';
    return 'fas fa-minus';
  }
  trackByIndex(index: number): number { return index; }
}
