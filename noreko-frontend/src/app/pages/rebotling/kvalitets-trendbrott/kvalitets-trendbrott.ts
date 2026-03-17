import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KvalitetsTrendbrottService,
  TrendbrottOverviewData,
  TrendbrottAlertsData,
  TrendbrottAlert,
  TrendbrottDailyDetailData,
  TrendbrottDailyItem,
} from '../../../services/kvalitets-trendbrott.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kvalitets-trendbrott',
  templateUrl: './kvalitets-trendbrott.html',
  styleUrls: ['./kvalitets-trendbrott.css'],
  imports: [CommonModule, FormsModule],
})
export class KvalitetsTrendbrottPage implements OnInit, OnDestroy {
  // Period
  period = 30;
  readonly periodOptions = [7, 30, 90];

  // Loading
  loadingOverview = false;
  loadingAlerts = false;
  loadingDetail = false;

  // Errors
  errorOverview = false;
  errorAlerts = false;
  errorDetail = false;

  // Data
  overview: TrendbrottOverviewData | null = null;
  alertsData: TrendbrottAlertsData | null = null;
  dailyDetail: TrendbrottDailyDetailData | null = null;
  selectedDate: string | null = null;

  // Charts
  private trendbrottChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: KvalitetsTrendbrottService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  onPeriodChange(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.selectedDate = null;
    this.dailyDetail = null;
    this.loadOverview();
    this.loadAlerts();
  }

  // ---- Overview ----
  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorOverview = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
        setTimeout(() => { if (!this.destroy$.closed) this.buildChart(); }, 100);
      } else if (res !== null) {
        this.errorOverview = true;
      }
    });
  }

  // ---- Alerts ----
  private loadAlerts(): void {
    this.loadingAlerts = true;
    this.errorAlerts = false;
    this.svc.getAlerts(this.period).pipe(
      timeout(15000),
      catchError(() => { this.errorAlerts = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingAlerts = false;
      if (res?.success) {
        this.alertsData = res.data;
      } else if (res !== null) {
        this.errorAlerts = true;
      }
    });
  }

  // ---- Daily detail (drill-down) ----
  openDailyDetail(date: string): void {
    this.selectedDate = date;
    this.loadingDetail = true;
    this.errorDetail = false;
    this.dailyDetail = null;

    this.svc.getDailyDetail(date).pipe(
      timeout(15000),
      catchError(() => { this.errorDetail = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingDetail = false;
      if (res?.success) {
        this.dailyDetail = res.data;
      } else if (res !== null) {
        this.errorDetail = true;
      }
    });
  }

  closeDailyDetail(): void {
    this.selectedDate = null;
    this.dailyDetail = null;
  }

  // ---- Chart ----
  private destroyCharts(): void {
    try { this.trendbrottChart?.destroy(); } catch (_) {}
    this.trendbrottChart = null;
  }

  private buildChart(): void {
    this.destroyCharts();
    if (!this.overview?.daily?.length) return;

    const canvas = document.getElementById('trendbrottChart') as HTMLCanvasElement;
    if (!canvas) return;

    const daily = this.overview.daily;
    const labels = daily.map(d => d.datum);
    const kassationData = daily.map(d => d.kassation_pct);
    const ma7Data = daily.map(d => d.ma7);
    const upperData = daily.map(() => this.overview!.upper_bound);
    const lowerData = daily.map(() => this.overview!.lower_bound);

    // Punkt-färger: röd för höga avvikelser, grön för låga, transparent annars
    const pointColors = daily.map(d => {
      if (!d.avvikelse) return 'transparent';
      return d.avvikelse_typ === 'hög' ? '#fc8181' : '#68d391';
    });
    const pointRadius = daily.map(d => d.avvikelse ? 6 : 2);
    const pointBorderColors = daily.map(d => {
      if (!d.avvikelse) return 'transparent';
      return d.avvikelse_typ === 'hög' ? '#e53e3e' : '#38a169';
    });

    this.trendbrottChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Kassation %',
            data: kassationData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.1)',
            borderWidth: 2,
            fill: false,
            pointBackgroundColor: pointColors,
            pointBorderColor: pointBorderColors,
            pointRadius: pointRadius,
            pointHoverRadius: 8,
            tension: 0.3,
          },
          {
            label: 'Rörligt medelvärde (7d)',
            data: ma7Data,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [5, 5],
            fill: false,
            pointRadius: 0,
            tension: 0.3,
          },
          {
            label: 'Övre gräns (+2\u03c3)',
            data: upperData,
            borderColor: '#fc8181',
            borderWidth: 1,
            borderDash: [3, 3],
            fill: false,
            pointRadius: 0,
          },
          {
            label: 'Undre gräns (-2\u03c3)',
            data: lowerData,
            borderColor: '#68d391',
            borderWidth: 1,
            borderDash: [3, 3],
            fill: false,
            pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event, elements) => {
          if (elements.length > 0) {
            const idx = elements[0].index;
            const datum = daily[idx]?.datum;
            if (datum) this.openDailyDetail(datum);
          }
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              afterBody: (items: any) => {
                const idx = items[0]?.dataIndex;
                if (idx !== undefined && daily[idx]?.avvikelse) {
                  return `AVVIKELSE: ${daily[idx].avvikelse_sigma}\u03c3 (${daily[idx].avvikelse_typ})`;
                }
                return '';
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Kassation %', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Helpers ----
  getTrendIcon(): string {
    if (!this.overview) return '';
    switch (this.overview.trend) {
      case 'bättre': return 'fas fa-arrow-down text-success';
      case 'sämre':  return 'fas fa-arrow-up text-danger';
      default:       return 'fas fa-minus text-muted';
    }
  }

  getTrendText(): string {
    if (!this.overview) return '';
    switch (this.overview.trend) {
      case 'bättre': return 'Förbättras';
      case 'sämre':  return 'Försämras';
      default:       return 'Stabil';
    }
  }

  getTypBadgeClass(typ: string): string {
    return typ === 'hög' ? 'badge bg-danger' : 'badge bg-success';
  }
  trackByIndex(index: number): number { return index; }
}
