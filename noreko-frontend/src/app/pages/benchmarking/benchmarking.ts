import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingService,
  BenchmarkingResponse,
  BenchmarkingWeek,
  BenchmarkingTopWeek,
  BenchmarkingMonthly,
  BenchmarkingBestDay,
} from '../../services/rebotling.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-benchmarking',
  templateUrl: './benchmarking.html',
  styleUrl: './benchmarking.css',
  imports: [CommonModule],
})
export class BenchmarkingPage implements OnInit, OnDestroy {
  Math = Math;

  loading = true;
  error = '';

  currentWeek: BenchmarkingWeek | null = null;
  bestWeekEver: BenchmarkingWeek | null = null;
  bestDayEver: BenchmarkingBestDay | null = null;
  topWeeks: BenchmarkingTopWeek[] = [];
  monthlyTotals: BenchmarkingMonthly[] = [];

  // Innevarande veckolabel för att markera i topp-10
  currentWeekLabel = '';
  bestWeekLabel = '';

  // Progress mot rekordveckan (0–100)
  progressPct = 0;
  ibcDiffToRecord = 0;
  isNewRecord = false;

  // Chart
  @ViewChild('monthlyChart') monthlyChartRef!: ElementRef<HTMLCanvasElement>;
  private monthlyChartInstance: Chart | null = null;

  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.load();
    this.pollInterval = setInterval(() => this.load(), 60_000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    this.monthlyChartInstance?.destroy();
    this.monthlyChartInstance = null;
  }

  private load(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.rebotlingService
      .getBenchmarking()
      .pipe(
        timeout(10_000),
        catchError(() => of({ success: false } as BenchmarkingResponse)),
        takeUntil(this.destroy$)
      )
      .subscribe((res) => {
        this.isFetching = false;
        if (!res.success) {
          this.error = 'Kunde inte hämta benchmarking-data.';
          this.loading = false;
          return;
        }
        this.error = '';
        this.currentWeek = res.current_week ?? null;
        this.bestWeekEver = res.best_week_ever ?? null;
        this.bestDayEver = res.best_day_ever ?? null;
        this.topWeeks = res.top_weeks ?? [];
        this.monthlyTotals = res.monthly_totals ?? [];

        this.currentWeekLabel = this.currentWeek?.week_label ?? '';
        this.bestWeekLabel = this.bestWeekEver?.week_label ?? '';

        // Diff mot rekord
        if (this.currentWeek && this.bestWeekEver) {
          const cur = this.currentWeek.ibc_total;
          const best = this.bestWeekEver.ibc_total;
          this.ibcDiffToRecord = best - cur;
          this.isNewRecord = cur > best;
          this.progressPct = best > 0 ? Math.min(100, Math.round((cur / best) * 100)) : 0;
        }

        this.loading = false;
        // Bygger chart efter att data är satt
        setTimeout(() => this.buildMonthlyChart(), 50);
      });
  }

  private buildMonthlyChart(): void {
    if (!this.monthlyChartRef) return;

    this.monthlyChartInstance?.destroy();
    this.monthlyChartInstance = null;

    const months = this.monthlyTotals;
    if (!months.length) return;

    const labels = months.map((m) => this.formatMonth(m.month));
    const values = months.map((m) => m.ibc_total);

    // Snitt
    const avg = values.length > 0 ? Math.round(values.reduce((a, b) => a + b, 0) / values.length) : 0;

    // Nuvarande månad och bästa månad
    const now = new Date();
    const curMonthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    const maxVal = Math.max(...values);
    const maxIdx = values.indexOf(maxVal);
    const curIdx = months.findIndex((m) => m.month === curMonthStr);

    const bgColors = values.map((v, i) => {
      if (i === curIdx) return 'rgba(66, 153, 225, 0.85)';
      if (i === maxIdx) return 'rgba(214, 158, 46, 0.85)';
      return 'rgba(99, 179, 237, 0.45)';
    });

    const borderColors = values.map((v, i) => {
      if (i === curIdx) return 'rgba(66, 153, 225, 1)';
      if (i === maxIdx) return 'rgba(214, 158, 46, 1)';
      return 'rgba(99, 179, 237, 0.7)';
    });

    const ctx = this.monthlyChartRef.nativeElement.getContext('2d')!;

    this.monthlyChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC per månad',
            data: values,
            backgroundColor: bgColors,
            borderColor: borderColors,
            borderWidth: 2,
            borderRadius: 4,
          },
          {
            label: `Snitt (${avg} IBC)`,
            data: Array(values.length).fill(avg),
            type: 'line',
            borderColor: 'rgba(252, 129, 74, 0.9)',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && months[idx]) {
                  return [`Kvalitet: ${months[idx].avg_quality}%`];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
          },
        },
      },
    });
  }

  formatMonth(m: string): string {
    const parts = m.split('-');
    if (parts.length < 2) return m;
    const monthNames = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun',
      'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec',
    ];
    const idx = parseInt(parts[1], 10) - 1;
    return `${monthNames[idx] ?? parts[1]} ${parts[0].slice(2)}`;
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  rankLabel(i: number): string {
    if (i === 0) return '1';
    if (i === 1) return '2';
    if (i === 2) return '3';
    return `${i + 1}`;
  }

  recordDiffPct(ibc: number): number {
    if (!this.bestWeekEver || this.bestWeekEver.ibc_total === 0) return 0;
    return Math.round((ibc / this.bestWeekEver.ibc_total) * 100);
  }
}
