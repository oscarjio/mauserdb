import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  ProduktionsmalService,
  SummaryData,
  DailyData,
  DailyRow,
} from '../../services/produktionsmal.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-produktionsmal',
  templateUrl: './produktionsmal.html',
  styleUrls: ['./produktionsmal.css'],
  imports: [CommonModule],
})
export class ProduktionsmalComponent implements OnInit, OnDestroy {
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

  // ---- Data ----
  summaryData: SummaryData | null = null;
  dailyData: DailyData | null     = null;

  lastRefreshed: Date | null = null;

  private destroy$    = new Subject<void>();
  private kumChart: Chart | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private service: ProduktionsmalService) {}

  ngOnInit(): void {
    this.loadAll();
    // Auto-refresh var 5:e minut
    this.refreshTimer = setInterval(() => {
      if (!this.destroy$.closed) this.loadAll();
    }, 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.refreshTimer) { clearInterval(this.refreshTimer); this.refreshTimer = null; }
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.kumChart?.destroy(); } catch (_) {}
    this.kumChart = null;
  }

  // ================================================================
  // DATA LOADING
  // ================================================================

  setPeriod(days: 7 | 14 | 30 | 90): void {
    if (this.selectedDays === days) return;
    this.selectedDays = days;
    this.dailyLoaded  = false;
    this.dailyData    = null;
    this.loadDaily();
  }

  private loadAll(): void {
    this.loadSummary();
    this.loadDaily();
  }

  private loadSummary(): void {
    if (this.summaryLoading) return;
    this.summaryLoading = true;
    this.summaryError   = false;

    this.service.getSummary()
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.dailyLoading = false;
        this.dailyLoaded  = true;
        if (res?.success) {
          this.dailyData  = res.data;
          this.dailyError = false;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderKumChart();
          }, 150);
        } else {
          this.dailyData  = null;
          this.dailyError = true;
        }
      });
  }

  // ================================================================
  // CHART: Kumulativ måluppfyllnad
  // ================================================================

  private renderKumChart(): void {
    this.destroyChart();

    const canvas = document.getElementById('kumMalChart') as HTMLCanvasElement;
    if (!canvas || !this.dailyData) return;

    const { daily } = this.dailyData;

    // Filtrera bort dagar utan mål (t.ex. helger med mål=0)
    const labels    = daily.map(r => this.formatDatumKort(r.datum));
    const kumMal    = daily.map(r => r.kum_mal);
    const kumFakt   = daily.map(r => r.kum_faktiskt);

    this.kumChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Kumulativt mal',
            data: kumMal,
            borderColor: '#a0aec0',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.1,
            pointRadius: 0,
            borderWidth: 2,
            borderDash: [8, 4],
          },
          {
            label: 'Kumulativ produktion',
            data: kumFakt,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72,187,120,0.10)',
            fill: true,
            tension: 0.2,
            pointRadius: 2,
            pointBackgroundColor: '#48bb78',
            borderWidth: 2.5,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toLocaleString('sv-SE')} IBC`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              font: { size: 10 },
              maxTicksLimit: 15,
              maxRotation: 45,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => val.toLocaleString('sv-SE'),
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Kumulativ IBC',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getStatusKlass(status: string): string {
    switch (status) {
      case 'ahead':    return 'status-ahead';
      case 'on_track': return 'status-on-track';
      case 'behind':   return 'status-behind';
      default:         return '';
    }
  }

  getStatusText(status: string): string {
    switch (status) {
      case 'ahead':    return 'Fore mal';
      case 'on_track': return 'I fas';
      case 'behind':   return 'Efter mal';
      default:         return '';
    }
  }

  getStatusIcon(status: string): string {
    switch (status) {
      case 'ahead':    return 'fa-check-circle';
      case 'on_track': return 'fa-minus-circle';
      case 'behind':   return 'fa-exclamation-circle';
      default:         return 'fa-question-circle';
    }
  }

  getProgressBarColor(pct: number): string {
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ecc94b';
    return '#fc5c65';
  }

  getRowColorClass(pct: number): string {
    if (pct >= 90) return 'row-green';
    if (pct >= 70) return 'row-yellow';
    return 'row-red';
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '-';
    const d = new Date(datum);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatDatumKort(datum: string): string {
    const d = new Date(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { month: 'numeric', day: 'numeric' });
  }

  formatNumber(n: number | null): string {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('sv-SE');
  }

  get dagligaRader(): DailyRow[] {
    if (!this.dailyData) return [];
    // Bara dagar med mal > 0 eller produktion > 0, senast forst
    return [...this.dailyData.daily]
      .filter(r => r.mal > 0 || r.faktiskt > 0)
      .reverse();
  }

  isLoading(): boolean {
    return this.summaryLoading || this.dailyLoading;
  }
}
