import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  DagligBriefingService,
  SammanfattningData,
  StopporsakItem,
  StationsstatusItem,
  VeckotrendItem,
  BemanningOperator,
} from '../daglig-briefing.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-daglig-briefing',
  templateUrl: './daglig-briefing.component.html',
  styleUrls: ['./daglig-briefing.component.css'],
  imports: [CommonModule, FormsModule],
})
export class DagligBriefingPage implements OnInit, OnDestroy {
  // Datum-val
  datumVal: 'igar' | 'idag' | 'specifikt' = 'igar';
  specifiktDatum = '';
  visatDatum = '';

  // Loading
  loadingSammanfattning = false;
  loadingStopp = false;
  loadingStationer = false;
  loadingTrend = false;
  loadingBemanning = false;

  // Error
  errorSammanfattning = false;
  errorStopp = false;
  errorStationer = false;
  errorTrend = false;
  errorBemanning = false;

  // Data
  sammanfattning: SammanfattningData | null = null;
  stopporsaker: StopporsakItem[] = [];
  stoppTotalMin = 0;
  stationer: StationsstatusItem[] = [];
  trend: VeckotrendItem[] = [];
  operatorer: BemanningOperator[] = [];
  bemanningAntal = 0;

  // Chart
  private trendChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: any = null;

  constructor(private svc: DagligBriefingService) {}

  ngOnInit(): void {
    this.loadAll();
    // Auto-refresh var 5:e minut
    this.refreshInterval = setInterval(() => this.loadAll(), 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  onDatumChange(): void {
    this.loadAll();
  }

  private getDatum(): string | undefined {
    if (this.datumVal === 'idag') {
      return new Date().toISOString().slice(0, 10);
    }
    if (this.datumVal === 'specifikt' && this.specifiktDatum) {
      return this.specifiktDatum;
    }
    return undefined; // igar = default pa backend
  }

  loadAll(): void {
    const datum = this.getDatum();
    this.loadSammanfattning(datum);
    this.loadStopp(datum);
    this.loadStationer(datum);
    this.loadTrend(datum);
    this.loadBemanning();
  }

  private loadSammanfattning(datum?: string): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning(datum).pipe(
      timeout(15000),
      catchError(() => { this.errorSammanfattning = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingSammanfattning = false;
      if (res?.success) {
        this.sammanfattning = res.data;
        this.visatDatum = res.data.datum;
      } else if (res !== null) {
        this.errorSammanfattning = true;
      }
    });
  }

  private loadStopp(datum?: string): void {
    this.loadingStopp = true;
    this.errorStopp = false;
    this.svc.getStopporsaker(datum).pipe(
      timeout(15000),
      catchError(() => { this.errorStopp = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingStopp = false;
      if (res?.success) {
        this.stopporsaker = res.data.orsaker;
        this.stoppTotalMin = res.data.total_min;
      } else if (res !== null) {
        this.errorStopp = true;
      }
    });
  }

  private loadStationer(datum?: string): void {
    this.loadingStationer = true;
    this.errorStationer = false;
    this.svc.getStationsstatus(datum).pipe(
      timeout(15000),
      catchError(() => { this.errorStationer = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingStationer = false;
      if (res?.success) {
        this.stationer = res.data.stationer;
      } else if (res !== null) {
        this.errorStationer = true;
      }
    });
  }

  private loadTrend(datum?: string): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getVeckotrend(datum).pipe(
      timeout(15000),
      catchError(() => { this.errorTrend = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trend = res.data.trend;
        setTimeout(() => this.buildTrendChart(), 100);
      } else if (res !== null) {
        this.errorTrend = true;
      }
    });
  }

  private loadBemanning(): void {
    this.loadingBemanning = true;
    this.errorBemanning = false;
    this.svc.getBemanning().pipe(
      timeout(15000),
      catchError(() => { this.errorBemanning = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingBemanning = false;
      if (res?.success) {
        this.operatorer = res.data.operatorer;
        this.bemanningAntal = res.data.antal;
      } else if (res !== null) {
        this.errorBemanning = true;
      }
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    const canvas = document.getElementById('briefingTrendChart') as HTMLCanvasElement;
    if (!canvas || this.trend.length === 0) return;

    this.trendChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.trend.map(t => t.dag_kort),
        datasets: [{
          label: 'IBC',
          data: this.trend.map(t => t.total_ibc),
          backgroundColor: this.trend.map((_, i) =>
            i === this.trend.length - 1 ? '#4fd1c5' : 'rgba(66, 153, 225, 0.7)'
          ),
          borderColor: this.trend.map((_, i) =>
            i === this.trend.length - 1 ? '#38b2ac' : '#3182ce'
          ),
          borderWidth: 1,
          borderRadius: 3,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items: any) => {
                const idx = items[0]?.dataIndex;
                return idx != null ? this.trend[idx].datum : '';
              },
              label: (ctx: any) => `${ctx.parsed.y} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { display: false },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // Helpers
  getStatusClass(status: string): string {
    switch (status) {
      case 'OK': return 'badge-ok';
      case 'Varning': return 'badge-warning';
      case 'Kritisk': return 'badge-critical';
      default: return 'badge-none';
    }
  }

  getOeeClass(oee: number): string {
    if (oee >= 65) return 'text-success';
    if (oee >= 40) return 'text-warning';
    return 'text-danger';
  }

  getKassClass(kass: number): string {
    if (kass <= 3) return 'text-success';
    if (kass <= 5) return 'text-warning';
    return 'text-danger';
  }

  getMalClass(pct: number): string {
    if (pct >= 90) return 'text-success';
    if (pct >= 70) return 'text-warning';
    return 'text-danger';
  }

  printReport(): void {
    window.print();
  }
  trackByIndex(index: number): number { return index; }
}
