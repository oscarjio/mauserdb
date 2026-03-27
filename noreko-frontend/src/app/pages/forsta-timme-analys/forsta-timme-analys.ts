import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ForstaTimmeAnalysService,
  AnalysData,
  SkiftStart,
  TrendPoint,
} from '../../services/forsta-timme-analys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-forsta-timme-analys',
  templateUrl: './forsta-timme-analys.html',
  styleUrls: ['./forsta-timme-analys.css'],
  imports: [CommonModule, FormsModule],
})
export class ForstaTimmeAnalysPage implements OnInit, OnDestroy {
  // -- Period --
  period = 30;
  readonly periodOptions = [
    { value: 7,  label: '7 dagar' },
    { value: 30, label: '30 dagar' },
    { value: 90, label: '90 dagar' },
  ];

  // -- Laddning --
  loadingAnalys = false;
  loadingTrend  = false;

  // -- Fel --
  errorAnalys = false;
  errorTrend  = false;

  // -- Data --
  analys: AnalysData | null = null;
  skiftStarter: SkiftStart[] = [];
  trendData: TrendPoint[] = [];

  // -- Charts --
  private rampupChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private svc: ForstaTimmeAnalysService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.rampupChart?.destroy(); } catch (_) {}
    this.rampupChart = null;
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  private destroyRampupChart(): void {
    try { this.rampupChart?.destroy(); } catch (_) {}
    this.rampupChart = null;
  }

  private destroyTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  // =================================================================
  // Period
  // =================================================================

  onPeriodChange(p: number): void {
    this.period = p;
    this.loadAll();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadAnalys();
    this.loadTrend();
  }

  loadAnalys(): void {
    this.loadingAnalys = true;
    this.errorAnalys   = false;
    this.destroyRampupChart();

    this.svc.getAnalysis(this.period)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingAnalys = false;
        if (res?.success) {
          this.analys      = res.data;
          this.skiftStarter = res.data.skift_starter ?? [];
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.buildRampupChart(); }, 0));
        } else {
          this.errorAnalys  = true;
          this.analys       = null;
          this.skiftStarter = [];
        }
      });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.destroyTrendChart();

    this.svc.getTrend(this.period)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data.trend ?? [];
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 0));
        } else {
          this.errorTrend = true;
          this.trendData  = [];
        }
      });
  }

  // =================================================================
  // Chart.js — Ramp-up-kurva (genomsnittlig IBC/10-min)
  // =================================================================

  private buildRampupChart(): void {
    this.destroyRampupChart();
    const canvas = document.getElementById('rampupChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.analys) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.analys.interval_labels ?? ['0-10', '10-20', '20-30', '30-40', '40-50', '50-60'];
    const avgData = this.analys.avg_kurva ?? [];

    if (this.rampupChart) { (this.rampupChart as any).destroy(); }
    this.rampupChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt IBC per 10 min (genomsnitt)',
            data: avgData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.15)',
            borderWidth: 2,
            pointRadius: 5,
            pointBackgroundColor: '#63b3ed',
            pointBorderColor: '#1a202c',
            pointBorderWidth: 2,
            tension: 0.3,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (item: any) => ` IBC: ${(item.raw as number).toFixed(2)} snitt`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.06)' },
            title: { display: true, text: 'Minut efter skiftstart', color: '#a0aec0', font: { size: 11 } },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Genomsnittligt antal IBC', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Chart.js — Stapeldiagram: tid till forsta IBC per dag
  // =================================================================

  private buildTrendChart(): void {
    this.destroyTrendChart();
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.trendData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels  = this.trendData.map(t => t.date);
    const snitt   = this.trendData.map(t => t.snitt_tid_till_forsta);
    const minVals = this.trendData.map(t => t.min_tid);
    const maxVals = this.trendData.map(t => t.max_tid);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            type: 'bar',
            label: 'Snitt tid (min)',
            data: snitt,
            backgroundColor: 'rgba(99, 179, 237, 0.55)',
            borderColor: 'rgba(99, 179, 237, 1)',
            borderWidth: 1,
            order: 2,
          } as any,
          {
            type: 'line',
            label: 'Snabbaste start (min)',
            data: minVals,
            borderColor: '#68d391',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#68d391',
            tension: 0.3,
            fill: false,
            order: 1,
          } as any,
          {
            type: 'line',
            label: 'Langsamma start (min)',
            data: maxVals,
            borderColor: '#fc8181',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#fc8181',
            tension: 0.3,
            fill: false,
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
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (item: any) => ` ${item.dataset.label}: ${item.raw} min`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', callback: (v: any) => `${v} min` },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Minuter', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjälpmetoder
  // =================================================================

  bedomningLabel(b: string): string {
    const map: Record<string, string> = {
      snabb:    'Snabb',
      normal:   'Normal',
      langssam: 'Langssam',
    };
    return map[b] ?? b;
  }

  bedomningClass(b: string): string {
    const map: Record<string, string> = {
      snabb:    'badge-snabb',
      normal:   'badge-normal',
      langssam: 'badge-langssam',
    };
    return 'bedomning-badge ' + (map[b] ?? '');
  }

  shiftLabel(s: string): string {
    const map: Record<string, string> = {
      dag:   'Dag (06:00)',
      kväll: 'Kväll (14:00)',
      natt:  'Natt (22:00)',
    };
    return map[s] ?? s;
  }

  formatDate(d: string): string {
    if (!d) return '';
    return d;
  }

  get harAnalysData(): boolean {
    return !!this.analys && this.skiftStarter.length > 0;
  }

  get harTrendData(): boolean {
    return this.trendData.length > 0;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
