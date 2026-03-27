import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  UtnyttjandegradService,
  SummaryData,
  DailyData,
  DailyRow,
  LossesData,
  LossItem,
} from '../../services/utnyttjandegrad.service';
import { parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-utnyttjandegrad',
  templateUrl: './utnyttjandegrad.html',
  styleUrls: ['./utnyttjandegrad.css'],
  imports: [CommonModule],
})
export class UtnyttjandegradComponent implements OnInit, OnDestroy {
  Math = Math;

  // ---- Perioder ----
  selectedDays: 7 | 14 | 30 | 90 = 30;
  perioder: { val: 7 | 14 | 30 | 90; label: string }[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 14, label: '14 dagar' },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Laddningsstate ----
  summaryLoading = false;
  summaryLoaded  = false;
  summaryError   = false;
  dailyLoading   = false;
  dailyLoaded    = false;
  dailyError     = false;
  lossesLoading  = false;
  lossesLoaded   = false;
  lossesError    = false;

  // ---- Data ----
  summaryData: SummaryData | null = null;
  dailyData: DailyData | null     = null;
  lossesData: LossesData | null   = null;

  lastRefreshed: Date | null = null;

  private destroy$      = new Subject<void>();
  private barChart: Chart | null       = null;
  private doughnutChart: Chart | null  = null;
  private chartTimer: ReturnType<typeof setTimeout> | null  = null;
  private chartTimer2: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: UtnyttjandegradService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer)  { clearTimeout(this.chartTimer);  this.chartTimer = null; }
    if (this.chartTimer2) { clearTimeout(this.chartTimer2); this.chartTimer2 = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.barChart = null;
    this.doughnutChart = null;
  }

  // ================================================================
  // DATA LOADING
  // ================================================================

  setPeriod(days: 7 | 14 | 30 | 90): void {
    if (this.selectedDays === days) return;
    this.selectedDays = days;
    this.dailyLoaded  = false;
    this.lossesLoaded = false;
    this.dailyData    = null;
    this.lossesData   = null;
    this.loadDaily();
    this.loadLosses();
  }

  private loadAll(): void {
    this.loadSummary();
    this.loadDaily();
    this.loadLosses();
  }

  private loadSummary(): void {
    if (this.summaryLoading) return;
    this.summaryLoading = true;
    this.summaryError   = false;

    this.service.getSummary()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
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
        this.lastRefreshed = new Date();
      });
  }

  private loadDaily(): void {
    if (this.dailyLoading) return;
    this.dailyLoading = true;
    this.dailyError   = false;

    this.service.getDaily(this.selectedDays)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.dailyLoading = false;
        this.dailyLoaded  = true;
        if (res?.success) {
          this.dailyData  = res.data;
          this.dailyError = false;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderBarChart();
          }, 150);
        } else {
          this.dailyData  = null;
          this.dailyError = true;
        }
      });
  }

  private loadLosses(): void {
    if (this.lossesLoading) return;
    this.lossesLoading = true;
    this.lossesError   = false;

    this.service.getLosses(this.selectedDays)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.lossesLoading = false;
        this.lossesLoaded  = true;
        if (res?.success) {
          this.lossesData  = res.data;
          this.lossesError = false;
          if (this.chartTimer2) clearTimeout(this.chartTimer2);
          this.chartTimer2 = setTimeout(() => {
            if (!this.destroy$.closed) this.renderDoughnutChart();
          }, 200);
        } else {
          this.lossesData  = null;
          this.lossesError = true;
        }
      });
  }

  // ================================================================
  // CHART: Staplad bar chart — daglig drifttid/stopptid/okänd
  // ================================================================

  private renderBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;

    const canvas = document.getElementById('utnyttjandegradBarChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.dailyData) return;

    const rows = this.dailyData.daily.filter(r => r.tillganglig_h > 0);
    const labels = rows.map(r => this.formatDatumKort(r.date));
    const drifttid  = rows.map(r => r.drifttid_h);
    const stopptid  = rows.map(r => r.stopptid_h);
    const okandTid  = rows.map(r => r.okand_tid_h);

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Drifttid (h)',
            data: drifttid,
            backgroundColor: '#48bb78',
            borderRadius: 2,
          },
          {
            label: 'Stopptid (h)',
            data: stopptid,
            backgroundColor: '#e53e3e',
            borderRadius: 2,
          },
          {
            label: 'Okand tid (h)',
            data: okandTid,
            backgroundColor: '#718096',
            borderRadius: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)} h`;
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 10 },
              maxTicksLimit: 15,
              maxRotation: 45,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => `${val} h`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Timmar',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // CHART: Doughnut — tidsförlustfördelning
  // ================================================================

  private renderDoughnutChart(): void {
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.doughnutChart = null;

    const canvas = document.getElementById('utnyttjandegradDoughnutChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.lossesData) return;

    // Exkludera drifttid från doughnut (visa bara förluster)
    const forluster = this.lossesData.losses.filter(l => l.typ !== 'produktion' && l.timmar > 0);

    if (forluster.length === 0) return;

    if (this.doughnutChart) { (this.doughnutChart as any).destroy(); }
    this.doughnutChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: forluster.map(l => l.kategori),
        datasets: [{
          data: forluster.map(l => l.timmar),
          backgroundColor: forluster.map(l => l.farg),
          borderColor: '#2d3748',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#a0aec0',
              font: { size: 11 },
              usePointStyle: true,
              padding: 16,
            },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed;
                const total = (ctx.dataset.data as number[]).reduce((a, b) => a + b, 0);
                const pct = total > 0 ? ((v / total) * 100).toFixed(1) : '0';
                return ` ${ctx.label}: ${v.toFixed(1)} h (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getTrendLabel(): string {
    if (!this.summaryData) return '-';
    switch (this.summaryData.trend) {
      case 'improving': return 'Forbattras';
      case 'declining': return 'Forsamras';
      default:          return 'Stabilt';
    }
  }

  getTrendPil(): string {
    if (!this.summaryData) return '->';
    switch (this.summaryData.trend) {
      case 'improving': return '|';
      case 'declining': return '|';
      default:          return '->';
    }
  }

  getTrendKlass(): string {
    if (!this.summaryData) return 'trend-stable';
    switch (this.summaryData.trend) {
      case 'improving': return 'trend-up';
      case 'declining': return 'trend-down';
      default:          return 'trend-stable';
    }
  }

  getTrendBadgeKlass(): string {
    if (!this.summaryData) return 'badge-stable';
    switch (this.summaryData.trend) {
      case 'improving': return 'badge-forbattras';
      case 'declining': return 'badge-forsamras';
      default:          return 'badge-stable';
    }
  }

  getChangeText(): string {
    const pct = this.summaryData?.change_pct ?? null;
    if (pct === null) return '';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}% vs foregaende 7d`;
  }

  formatPct(v: number | null): string {
    if (v === null || v === undefined) return '-';
    return v.toFixed(1) + '%';
  }

  formatTimmar(v: number | null): string {
    if (v === null || v === undefined) return '-';
    return v.toFixed(1) + ' h';
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '-';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatDatumKort(datum: string): string {
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { month: 'numeric', day: 'numeric' });
  }

  getUtnyttjandegradKlass(pct: number | null): string {
    if (pct === null) return 'text-muted';
    if (pct >= 80) return 'utn-god';
    if (pct >= 60) return 'utn-medel';
    return 'utn-lag';
  }

  getProgressColor(pct: number | null): string {
    if (pct === null) return '#718096';
    if (pct >= 80) return '#48bb78';
    if (pct >= 60) return '#ecc94b';
    return '#e53e3e';
  }

  getProgressDash(pct: number | null): string {
    const val = pct ?? 0;
    const circumference = 2 * Math.PI * 54;
    const filled = (val / 100) * circumference;
    return `${filled} ${circumference}`;
  }

  get dagligaTabelRader(): DailyRow[] {
    if (!this.dailyData) return [];
    return [...this.dailyData.daily]
      .filter(r => r.tillganglig_h > 0)
      .reverse();
  }

  get forlustLista(): LossItem[] {
    if (!this.lossesData) return [];
    return this.lossesData.losses.filter(l => l.typ !== 'produktion');
  }

  isLoading(): boolean {
    return this.summaryLoading || this.dailyLoading;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
