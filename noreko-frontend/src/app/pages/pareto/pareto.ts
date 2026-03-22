import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ParetoService,
  ParetoItem,
  ParetoSummaryData,
} from '../../services/pareto.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-pareto',
  templateUrl: './pareto.html',
  styleUrls: ['./pareto.css'],
  imports: [CommonModule, FormsModule],
})
export class ParetoPage implements OnInit, OnDestroy {
  // -- Period --
  days = 30;
  readonly dayOptions = [
    { value: 7,  label: '7 dagar' },
    { value: 14, label: '14 dagar' },
    { value: 30, label: '30 dagar' },
    { value: 90, label: '90 dagar' },
  ];

  // -- Laddning --
  loadingSummary = false;
  loadingPareto  = false;

  // -- Fel --
  errorSummary = false;
  errorPareto  = false;

  // -- Data --
  summary: ParetoSummaryData | null = null;
  paretoItems: ParetoItem[] = [];
  totalMinutes = 0;

  // -- Chart --
  private paretoChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private svc: ParetoService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    this.paretoChart = null;
  }

  // =================================================================
  // Period
  // =================================================================

  onDaysChange(d: number): void {
    this.days = d;
    this.loadAll();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadSummary();
    this.loadParetoData();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getSummary(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success) {
          this.summary = res.data;
        } else {
          this.errorSummary = true;
          this.summary = null;
        }
      });
  }

  loadParetoData(): void {
    this.loadingPareto = true;
    this.errorPareto   = false;
    this.destroyChart();
    this.svc.getParetoData(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingPareto = false;
        if (res?.success) {
          this.paretoItems  = res.data.items ?? [];
          this.totalMinutes = res.data.total_minutes ?? 0;
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.buildParetoChart(); }, 0));
        } else {
          this.errorPareto  = true;
          this.paretoItems  = [];
          this.totalMinutes = 0;
        }
      });
  }

  // =================================================================
  // Chart.js — Combo: staplar (stopptid) + linje (kumulativ %)
  // =================================================================

  private buildParetoChart(): void {
    this.destroyChart();
    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas || this.paretoItems.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels     = this.paretoItems.map(i => i.reason);
    const minutes    = this.paretoItems.map(i => i.minutes);
    const cumulative = this.paretoItems.map(i => i.cumulative_pct);

    // Farg per stapel: de inom 80% faar blaa, ovriga orange
    const barColors = this.paretoItems.map(i =>
      i.in_80pct ? 'rgba(66, 153, 225, 0.85)' : 'rgba(237, 137, 54, 0.75)'
    );
    const barBorders = this.paretoItems.map(i =>
      i.in_80pct ? 'rgba(66, 153, 225, 1)' : 'rgba(237, 137, 54, 1)'
    );

    // Streckad 80%-linje som annotation (via dataset pa hojd 80)
    const line80 = new Array(labels.length).fill(80);

    if (this.paretoChart) { (this.paretoChart as any).destroy(); }
    this.paretoChart = new Chart(ctx, {
      data: {
        labels,
        datasets: [
          {
            type: 'bar',
            label: 'Stopptid (min)',
            data: minutes,
            backgroundColor: barColors,
            borderColor: barBorders,
            borderWidth: 1,
            yAxisID: 'yLeft',
            order: 2,
          } as any,
          {
            type: 'line',
            label: 'Kumulativ %',
            data: cumulative,
            borderColor: '#fc8181',
            backgroundColor: 'rgba(252, 129, 129, 0.15)',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#fc8181',
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            tension: 0.2,
            fill: false,
            yAxisID: 'yRight',
            order: 1,
          } as any,
          {
            type: 'line',
            label: '80%-grans',
            data: line80,
            borderColor: 'rgba(255, 255, 100, 0.7)',
            borderWidth: 2,
            borderDash: [8, 4],
            pointRadius: 0,
            fill: false,
            yAxisID: 'yRight',
            order: 0,
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
            labels: {
              color: '#e2e8f0',
              boxWidth: 12,
              padding: 12,
              font: { size: 11 },
              filter: (item: any) => item.text !== '80%-grans' ? true : true,
            },
          },
          tooltip: {
            callbacks: {
              label: (item: any) => {
                if (item.dataset.label === 'Stopptid (min)') {
                  const mins = item.raw as number;
                  return ` Stopptid: ${this.formatMinutes(mins)}`;
                }
                if (item.dataset.label === 'Kumulativ %') {
                  return ` Kumulativ: ${(item.raw as number).toFixed(1)}%`;
                }
                if (item.dataset.label === '80%-grans') {
                  return ` 80%-grans`;
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
              autoSkip: false,
              font: { size: 10 },
              callback: (val: any, idx: number) => {
                const lbl = labels[idx] ?? '';
                return lbl.length > 16 ? lbl.substring(0, 14) + '…' : lbl;
              },
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Stopptid (min)', color: '#a0aec0', font: { size: 11 } },
          },
          yRight: {
            type: 'linear',
            position: 'right',
            min: 0,
            max: 100,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => `${v}%`,
            },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kumulativ %', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjalpmetoder
  // =================================================================

  formatMinutes(min: number): string {
    if (!min || min <= 0) return '0 min';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  get harData(): boolean {
    return this.paretoItems.length > 0 && this.totalMinutes > 0;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
