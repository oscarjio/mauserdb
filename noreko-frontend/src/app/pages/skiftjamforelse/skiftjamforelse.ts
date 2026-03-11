import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  SkiftjamforelseService,
  ShiftComparisonData,
  ShiftTrendData,
  ShiftOperatorsData,
  SkiftTyp,
} from '../../services/skiftjamforelse.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-skiftjamforelse',
  templateUrl: './skiftjamforelse.html',
  styleUrls: ['./skiftjamforelse.css'],
  imports: [CommonModule, FormsModule],
})
export class SkiftjamforelseComponent implements OnInit, OnDestroy {

  // ---- State ----
  selectedPeriod: number = 30;
  perioder: number[] = [7, 30, 90];

  compLoading  = false;
  compLoaded   = false;
  compError    = false;
  trendLoading = false;
  trendLoaded  = false;

  compData:  ShiftComparisonData | null = null;
  trendData: ShiftTrendData | null = null;

  // Operatörsdata per skift
  opData: { [key in SkiftTyp]?: ShiftOperatorsData } = {};
  opLoading: { [key in SkiftTyp]?: boolean } = {};
  opExpanded: { [key in SkiftTyp]?: boolean } = { dag: false, kvall: false, natt: false };

  lastRefreshed: Date | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private barChart: Chart | null = null;
  private trendChart: Chart | null = null;

  constructor(private service: SkiftjamforelseService) {}

  ngOnInit(): void {
    this.loadAll();
    this.startAutoRefresh();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.barChart = null;
    this.trendChart = null;
  }

  setPeriod(period: number): void {
    if (this.selectedPeriod === period) return;
    this.selectedPeriod = period;
    this.compLoaded = false;
    this.trendLoaded = false;
    this.compData = null;
    this.trendData = null;
    this.opData = {};
    this.loadAll();
  }

  private loadAll(): void {
    this.loadComparison();
    this.loadTrend();
  }

  private loadComparison(): void {
    if (this.compLoading) return;
    this.compLoading = true;
    this.compError = false;

    this.service.getShiftComparison(this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.compLoading = false;
        if (res?.success) {
          this.compData = res.data;
          this.compError = false;
          setTimeout(() => { if (!this.destroy$.closed) this.renderBarChart(); }, 100);
        } else {
          this.compData = null;
          this.compError = true;
        }
        this.compLoaded = true;
        this.lastRefreshed = new Date();
      });
  }

  private loadTrend(): void {
    if (this.trendLoading) return;
    this.trendLoading = true;

    this.service.getShiftTrend(this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.trendLoading = false;
        this.trendData = res?.success ? res.data : null;
        this.trendLoaded = true;
        if (this.trendData) {
          setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 100);
        }
      });
  }

  toggleOperatorer(skift: SkiftTyp): void {
    const current = this.opExpanded[skift] ?? false;
    this.opExpanded[skift] = !current;

    if (!current && !this.opData[skift] && !this.opLoading[skift]) {
      this.loadOperatorer(skift);
    }
  }

  private loadOperatorer(skift: SkiftTyp): void {
    if (this.opLoading[skift]) return;
    this.opLoading[skift] = true;

    this.service.getShiftOperators(skift, this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.opLoading[skift] = false;
        if (res?.success) {
          this.opData[skift] = res.data;
        }
      });
  }

  private startAutoRefresh(): void {
    this.refreshInterval = setInterval(() => {
      this.loadAll();
    }, 120000); // var 2:e minut
  }

  // ================================================================
  // CHART-RENDERING
  // ================================================================

  private renderBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;

    const canvas = document.getElementById('skiftBarChart') as HTMLCanvasElement;
    if (!canvas || !this.compData) return;

    const skiften = this.compData.skift;
    const labels  = skiften.map(s => s.label);

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data: skiften.map(s => s.ibc_per_h),
            backgroundColor: 'rgba(237, 137, 54, 0.75)',
            borderColor: '#ed8936',
            borderWidth: 1,
            borderRadius: 4,
          },
          {
            label: 'Kvalitet %',
            data: skiften.map(s => s.kvalitet_pct),
            backgroundColor: 'rgba(72, 187, 120, 0.75)',
            borderColor: '#48bb78',
            borderWidth: 1,
            borderRadius: 4,
          },
          {
            label: 'OEE %',
            data: skiften.map(s => s.oee_pct),
            backgroundColor: 'rgba(99, 179, 237, 0.75)',
            borderColor: '#63b3ed',
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' },
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('skiftTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData) return;

    const veckor = this.trendData.veckor;
    const labels = veckor.map(v => v.label);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Dagskift',
            data: veckor.map(v => v.dag),
            borderColor: '#ecc94b',
            backgroundColor: 'rgba(236, 201, 75, 0.15)',
            pointBackgroundColor: '#ecc94b',
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: 'Kvällsskift',
            data: veckor.map(v => v.kvall),
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.15)',
            pointBackgroundColor: '#63b3ed',
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: 'Nattskift',
            data: veckor.map(v => v.natt),
            borderColor: '#b794f4',
            backgroundColor: 'rgba(183, 148, 244, 0.15)',
            pointBackgroundColor: '#b794f4',
            tension: 0.3,
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' },
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y ?? '-'} IBC/h`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
            title: {
              display: true,
              text: 'IBC/h',
              color: '#a0aec0',
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getSkiftIcon(skift: string): string {
    if (skift === 'dag')   return 'fas fa-sun';
    if (skift === 'kvall') return 'fas fa-cloud-sun';
    return 'fas fa-moon';
  }

  getSkiftColor(skift: string): string {
    if (skift === 'dag')   return '#ecc94b';
    if (skift === 'kvall') return '#63b3ed';
    return '#b794f4';
  }

  getKvalitetKlass(pct: number): string {
    if (pct >= 98) return 'text-success';
    if (pct >= 90) return 'text-warning';
    return 'text-danger';
  }

  getOeeKlass(pct: number): string {
    if (pct >= 85) return 'text-success';
    if (pct >= 60) return 'text-info';
    if (pct >= 40) return 'text-warning';
    return 'text-danger';
  }

  getIbcHKlass(ibcH: number): string {
    if (ibcH >= 30) return 'text-success';
    if (ibcH >= 20) return 'text-warning';
    return 'text-danger';
  }

  getDiffKlass(diff: number): string {
    if (diff > 2)  return 'text-success';
    if (diff < -2) return 'text-danger';
    return 'text-muted';
  }

  getDiffText(diff: number): string {
    if (diff > 0) return `+${diff.toFixed(1)}%`;
    return `${diff.toFixed(1)}%`;
  }

  formatMinuter(min: number): string {
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  getPeriodLabel(period: number): string {
    if (period === 7)  return 'Senaste 7 dagarna';
    if (period === 30) return 'Senaste 30 dagarna';
    return 'Senaste 90 dagarna';
  }
}
