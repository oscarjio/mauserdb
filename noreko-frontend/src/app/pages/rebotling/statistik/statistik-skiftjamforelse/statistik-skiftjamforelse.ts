import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, ShiftDayNightResponse, ShiftKpi, ShiftTrendPoint } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-skiftjamforelse',
  templateUrl: './statistik-skiftjamforelse.html',
  styleUrls: ['./statistik-skiftjamforelse.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikSkiftjamforelseComponent implements OnInit, OnDestroy {
  loading: boolean = false;
  error: string | null = null;

  days: number = 30;

  dag: ShiftKpi | null = null;
  natt: ShiftKpi | null = null;
  trend: ShiftTrendPoint[] = [];
  fromDate: string = '';

  // Aktiv graf (ibc / cykeltid / kvalitet)
  activeChart: 'ibc' | 'cykeltid' | 'kvalitet' = 'ibc';

  private barChart: Chart | null = null;
  private lineChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    try { this.barChart?.destroy(); } catch (_e) {}
    try { this.lineChart?.destroy(); } catch (_e) {}
    this.barChart = null;
    this.lineChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  onDaysChange(): void {
    this.load();
  }

  setActiveChart(c: 'ibc' | 'cykeltid' | 'kvalitet'): void {
    this.activeChart = c;
    this._timers.push(setTimeout(() => {
      if (!this.destroy$.closed) this.renderLineChart();
    }, 50));
  }

  load(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = null;

    this.rebotlingService.getShiftDayNightComparison(this.days).pipe(
      timeout(12000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: ShiftDayNightResponse | null) => {
      this.loading = false;
      if (res?.success) {
        this.dag = res.dag;
        this.natt = res.natt;
        this.trend = res.trend ?? [];
        this.fromDate = res.from ?? '';
        this._timers.push(setTimeout(() => {
          if (!this.destroy$.closed) {
            this.renderBarChart();
            this.renderLineChart();
          }
        }, 100));
      } else {
        this.error = 'Kunde inte ladda skiftjämförelse';
        this.dag = null;
        this.natt = null;
        this.trend = [];
      }
    });
  }

  private renderBarChart(): void {
    try { this.barChart?.destroy(); } catch (_e) {}
    this.barChart = null;

    const canvas = document.getElementById('skiftJamforelseBarChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.dag || !this.natt) return;

    const labels = ['IBC (totalt)', 'Snitt IBC/skift', 'Kvalitet %', 'OEE %', 'IBC/h'];
    const dagData = [
      this.dag.ibc_ok,
      this.dag.avg_ibc_per_skift,
      this.dag.kvalitet_pct ?? 0,
      this.dag.oee_pct ?? 0,
      this.dag.ibc_per_h ?? 0
    ];
    const nattData = [
      this.natt.ibc_ok,
      this.natt.avg_ibc_per_skift,
      this.natt.kvalitet_pct ?? 0,
      this.natt.oee_pct ?? 0,
      this.natt.ibc_per_h ?? 0
    ];

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Dagskift',
            data: dagData,
            backgroundColor: 'rgba(237, 137, 54, 0.75)',
            borderColor: 'rgba(246, 173, 85, 1)',
            borderWidth: 1,
            borderRadius: 4
          },
          {
            label: 'Nattskift',
            data: nattData,
            backgroundColor: 'rgba(99, 102, 241, 0.75)',
            borderColor: 'rgba(129, 140, 248, 1)',
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 12 }, boxWidth: 14, padding: 14 }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#ed8936',
            borderWidth: 1
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' }
          }
        }
      }
    });
  }

  private renderLineChart(): void {
    try { this.lineChart?.destroy(); } catch (_e) {}
    this.lineChart = null;

    const canvas = document.getElementById('skiftJamfLinjediagram') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.trend.length === 0) return;

    const labels = this.trend.map(t => {
      const parts = t.datum.split('-');
      return parts[2] + '/' + parts[1];
    });

    let dagValues: (number | null)[];
    let nattValues: (number | null)[];
    let yLabel: string;
    let dagColor: string;
    let nattColor: string;

    if (this.activeChart === 'ibc') {
      dagValues  = this.trend.map(t => t.dag_ibc);
      nattValues = this.trend.map(t => t.natt_ibc);
      yLabel = 'IBC OK';
      dagColor = '#ed8936';
      nattColor = '#818cf8';
    } else if (this.activeChart === 'cykeltid') {
      dagValues  = this.trend.map(t => t.dag_cykeltid);
      nattValues = this.trend.map(t => t.natt_cykeltid);
      yLabel = 'Cykeltid (min)';
      dagColor = '#f6ad55';
      nattColor = '#a5b4fc';
    } else {
      dagValues  = this.trend.map(t => t.dag_kvalitet);
      nattValues = this.trend.map(t => t.natt_kvalitet);
      yLabel = 'Kvalitet %';
      dagColor = '#f6e05e';
      nattColor = '#c4b5fd';
    }

    if (this.lineChart) { (this.lineChart as any).destroy(); }
    this.lineChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Dagskift',
            data: dagValues,
            borderColor: dagColor,
            backgroundColor: dagColor + '22',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: dagColor,
            tension: 0.3,
            fill: false,
            spanGaps: true
          },
          {
            label: 'Nattskift',
            data: nattValues,
            borderColor: nattColor,
            backgroundColor: nattColor + '22',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: nattColor,
            tension: 0.3,
            fill: false,
            spanGaps: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 12 }, boxWidth: 14, padding: 14 }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#6366f1',
            borderWidth: 1
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: this.activeChart !== 'cykeltid',
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: yLabel, color: '#8fa3b8', font: { size: 11 } }
          }
        }
      }
    });
  }

  // Hjälpare för KPI-diff
  getDiff(dagVal: number | null, nattVal: number | null): number | null {
    if (dagVal === null || nattVal === null) return null;
    return Math.round((dagVal - nattVal) * 10) / 10;
  }

  getDiffPct(dagVal: number | null, nattVal: number | null): number | null {
    if (dagVal === null || nattVal === null || nattVal === 0) return null;
    return Math.round(((dagVal - nattVal) / nattVal) * 1000) / 10;
  }

  getDiffClass(diff: number | null): string {
    if (diff === null) return '';
    return diff > 0 ? 'text-success' : diff < 0 ? 'text-danger' : 'text-muted';
  }

  // Formatera minuter som h min
  formatMin(min: number | null): string {
    if (min === null) return '-';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}min` : `${m}min`;
  }
}
