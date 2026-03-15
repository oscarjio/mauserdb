import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  VdDashboardService,
  OversiktData,
  StoppNuData,
  TopOperatorerData,
  StationOeeData,
  VeckotrendData,
  SkiftstatusData,
} from '../../services/vd-dashboard.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-vd-dashboard',
  templateUrl: './vd-dashboard.component.html',
  imports: [CommonModule],
})
export class VdDashboardPage implements OnInit, OnDestroy {

  // Data
  oversikt: OversiktData | null = null;
  stoppNu: StoppNuData | null = null;
  topOperatorer: TopOperatorerData | null = null;
  stationOee: StationOeeData | null = null;
  veckotrend: VeckotrendData | null = null;
  skiftstatus: SkiftstatusData | null = null;

  // Loading / Error
  loading = true;
  lastUpdate = '';
  errorMessage = '';
  private isFetching = false;

  // Charts
  private trendChart: Chart | null = null;
  private stationChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshInterval: any = null;

  constructor(private svc: VdDashboardService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) clearInterval(this.refreshInterval);
    this.trendChart?.destroy();
    this.stationChart?.destroy();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.errorMessage = '';

    this.svc.getOversikt().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (res?.success) {
        this.oversikt = res.data;
        this.lastUpdate = new Date().toLocaleTimeString('sv-SE');
      } else if (!this.oversikt) {
        this.errorMessage = 'Kunde inte hamta produktionsdata';
      }
    });

    this.svc.getStoppNu().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) this.stoppNu = res.data;
    });

    this.svc.getTopOperatorer().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) this.topOperatorer = res.data;
    });

    this.svc.getStationOee().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.stationOee = res.data;
        setTimeout(() => this.renderStationChart(), 100);
      }
    });

    this.svc.getVeckotrend().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.veckotrend = res.data;
        setTimeout(() => this.renderTrendChart(), 100);
      }
    });

    this.svc.getSkiftstatus().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) this.skiftstatus = res.data;
    });
  }

  // ---- Helpers ----

  getOeeColor(oee: number): string {
    if (oee >= 80) return '#48bb78';
    if (oee >= 60) return '#ecc94b';
    return '#fc8181';
  }

  getOeeLabel(oee: number): string {
    if (oee >= 80) return 'Utmarkt';
    if (oee >= 60) return 'OK';
    return 'Kritiskt';
  }

  getMalBarWidth(): string {
    const pct = this.oversikt?.mal_procent ?? 0;
    return Math.min(100, pct) + '%';
  }

  getMalBarColor(): string {
    const pct = this.oversikt?.mal_procent ?? 0;
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ecc94b';
    return '#fc8181';
  }

  getSkiftIcon(): string {
    const s = this.skiftstatus?.skift;
    if (s === 'FM') return 'bi-sunrise';
    if (s === 'EM') return 'bi-sun';
    return 'bi-moon-stars';
  }

  getJamforelseText(): string {
    const j = this.skiftstatus?.jamforelse ?? 0;
    if (j > 0) return `+${j}%`;
    if (j < 0) return `${j}%`;
    return 'Lika';
  }

  getJamforelseColor(): string {
    const j = this.skiftstatus?.jamforelse ?? 0;
    if (j > 0) return '#48bb78';
    if (j < 0) return '#fc8181';
    return '#e2e8f0';
  }

  getPodiumIcon(rank: number): string {
    if (rank === 1) return 'bi-trophy-fill';
    if (rank === 2) return 'bi-award-fill';
    return 'bi-star-fill';
  }

  getPodiumColor(rank: number): string {
    if (rank === 1) return '#FFD700';
    if (rank === 2) return '#C0C0C0';
    return '#CD7F32';
  }

  // ---- Charts ----

  private renderTrendChart(): void {
    if (!this.veckotrend?.trend?.length) return;

    const canvas = document.getElementById('vdTrendChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.trendChart?.destroy();

    const labels = this.veckotrend.trend.map(t => t.dag_kort);
    const oeeData = this.veckotrend.trend.map(t => t.oee_pct);
    const ibcData = this.veckotrend.trend.map(t => t.total_ibc);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oeeData,
            borderColor: '#4fd1c5',
            backgroundColor: 'rgba(79, 209, 197, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            yAxisID: 'y',
            pointRadius: 4,
            pointBackgroundColor: '#4fd1c5',
          },
          {
            label: 'IBC',
            data: ibcData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: false,
            yAxisID: 'y1',
            pointRadius: 4,
            pointBackgroundColor: '#63b3ed',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            labels: { color: '#e2e8f0', font: { size: 11 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            position: 'left',
            min: 0,
            max: 100,
            ticks: { color: '#4fd1c5', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y1: {
            position: 'right',
            min: 0,
            ticks: { color: '#63b3ed' },
            grid: { drawOnChartArea: false },
          },
        },
      },
    });
  }

  private renderStationChart(): void {
    if (!this.stationOee?.stationer?.length) return;

    const canvas = document.getElementById('vdStationChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.stationChart?.destroy();

    const labels = this.stationOee.stationer.map(s => s.station_namn);
    const data = this.stationOee.stationer.map(s => s.oee_pct);
    const colors = data.map(d => {
      if (d >= 80) return '#48bb78';
      if (d >= 60) return '#ecc94b';
      return '#fc8181';
    });

    this.stationChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'OEE %',
          data,
          backgroundColor: colors,
          borderRadius: 4,
          barThickness: 28,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          x: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid: { display: false },
          },
        },
      },
    });
  }
  trackByIndex(index: number): number { return index; }
}
