import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingStationsdetaljService,
  KpiIdagData,
  IbcRad,
  StoppRad,
  OeeTrendDag,
  RealtidOeeData,
} from '../../../services/rebotling-stationsdetalj.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-rebotling-stationsdetalj',
  templateUrl: './stationsdetalj.component.html',
  styleUrls: ['./stationsdetalj.component.css'],
  imports: [CommonModule, FormsModule],
})
export class RebotlingStationsdetaljPage implements OnInit, OnDestroy {

  // Laddningsstatus
  loadingStationer    = false;
  loadingKpi          = false;
  loadingIbc          = false;
  loadingStopp        = false;
  loadingTrend        = false;
  loadingRealtid      = false;

  // Felstatus
  errorStationer      = false;
  errorKpi            = false;
  errorIbc            = false;
  errorStopp          = false;
  errorTrend          = false;
  errorRealtid        = false;

  // Data
  stationer: string[]          = [];
  valdStation: string          = '';
  kpiData: KpiIdagData | null  = null;
  ibcLista: IbcRad[]           = [];
  stoppLista: StoppRad[]       = [];
  trendData: OeeTrendDag[]     = [];
  realtidData: RealtidOeeData | null = null;

  // Trendperiod
  trendDagar = 30;
  readonly trendAlternativ = [
    { varde: 7,  etikett: '7 dagar'  },
    { varde: 14, etikett: '14 dagar' },
    { varde: 30, etikett: '30 dagar' },
    { varde: 60, etikett: '60 dagar' },
  ];

  // Graf
  private trendChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private pollingInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: RebotlingStationsdetaljService) {}

  ngOnInit(): void {
    this.laddaStationer();
    // Polling var 30:e sekund
    this.pollingInterval = setInterval(() => {
      if (!this.destroy$.closed && this.valdStation) {
        this.laddaRealtidOee();
        this.laddaKpiIdag();
        this.laddaSenasteIbc();
      }
    }, 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollingInterval !== null) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  // ============================================================
  // Laddning
  // ============================================================

  laddaStationer(): void {
    this.loadingStationer = true;
    this.errorStationer   = false;
    this.svc.getStationer()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStationer = false;
        if (res?.success) {
          this.stationer = res.data.stationer;
          if (this.stationer.length > 0 && !this.valdStation) {
            this.valdStation = this.stationer[0];
            this.laddaAlltForStation();
          }
        } else {
          this.errorStationer = true;
        }
      });
  }

  valjStation(station: string): void {
    if (this.valdStation === station) return;
    this.valdStation = station;
    this.laddaAlltForStation();
  }

  laddaAlltForStation(): void {
    if (!this.valdStation) return;
    this.laddaKpiIdag();
    this.laddaSenasteIbc();
    this.laddaStopphistorik();
    this.laddaOeeTrend();
    this.laddaRealtidOee();
  }

  laddaKpiIdag(): void {
    this.loadingKpi = true;
    this.errorKpi   = false;
    this.svc.getKpiIdag(this.valdStation)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingKpi = false;
        if (res?.success) {
          this.kpiData = res.data;
        } else {
          this.errorKpi = true;
        }
      });
  }

  laddaSenasteIbc(): void {
    this.loadingIbc = true;
    this.errorIbc   = false;
    this.svc.getSenasteIbc(this.valdStation, 25)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingIbc = false;
        if (res?.success) {
          this.ibcLista = res.data.ibc;
        } else {
          this.errorIbc = true;
        }
      });
  }

  laddaStopphistorik(): void {
    this.loadingStopp = true;
    this.errorStopp   = false;
    this.svc.getStopphistorik(this.valdStation, 20)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStopp = false;
        if (res?.success) {
          this.stoppLista = res.data.stopp;
        } else {
          this.errorStopp = true;
        }
      });
  }

  laddaOeeTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.trendData    = [];
    this.destroyCharts();
    this.svc.getOeeTrend(this.valdStation, this.trendDagar)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data.trend;
          setTimeout(() => { if (!this.destroy$.closed) this.byggTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
        }
      });
  }

  laddaRealtidOee(): void {
    this.loadingRealtid = true;
    this.errorRealtid   = false;
    this.svc.getRealtidOee(this.valdStation)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingRealtid = false;
        if (res?.success) {
          this.realtidData = res.data;
        } else {
          this.errorRealtid = true;
        }
      });
  }

  byttPeriod(dagar: number): void {
    if (this.trendDagar === dagar) return;
    this.trendDagar = dagar;
    this.laddaOeeTrend();
  }

  // ============================================================
  // Trendgraf
  // ============================================================

  private byggTrendChart(): void {
    this.destroyCharts();
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || this.trendData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels  = this.trendData.map(d => d.datum.slice(5));
    const oee     = this.trendData.map(d => d.oee_pct);
    const tillg   = this.trendData.map(d => d.tillganglighet_pct);
    const kval    = this.trendData.map(d => d.kvalitet_pct);
    const ibcData = this.trendData.map(d => d.total_ibc);

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oee,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.08)',
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            yAxisID: 'yPct',
            fill: false,
            order: 1,
          },
          {
            label: 'Tillgänglighet %',
            data: tillg,
            borderColor: '#48bb78',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [4, 3],
            pointRadius: 0,
            tension: 0.3,
            yAxisID: 'yPct',
            fill: false,
            order: 2,
          },
          {
            label: 'Kvalitet %',
            data: kval,
            borderColor: '#ed8936',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [4, 3],
            pointRadius: 0,
            tension: 0.3,
            yAxisID: 'yPct',
            fill: false,
            order: 3,
          },
          {
            label: 'IBC antal',
            data: ibcData,
            borderColor: '#9f7aea',
            backgroundColor: 'rgba(159,122,234,0.08)',
            borderWidth: 1.5,
            pointRadius: 0,
            tension: 0.3,
            yAxisID: 'yIbc',
            fill: true,
            type: 'bar' as any,
            order: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        animation: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item: any) => {
                const v = item.raw as number | null;
                if (v === null) return '';
                if (item.dataset.yAxisID === 'yIbc') return ` ${item.dataset.label}: ${v} st`;
                return ` ${item.dataset.label}: ${v}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          yPct: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: { display: true, text: 'Procent (%)', color: '#a0aec0', font: { size: 10 } },
          },
          yIbc: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v: any) => v + ' st' },
            grid: { display: false },
            title: { display: true, text: 'IBC antal', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Template-hjälpare
  // ============================================================

  oeeFarg(pct: number | undefined): string {
    if (pct === undefined || pct === null) return '#a0aec0';
    if (pct >= 75) return '#48bb78';
    if (pct >= 50) return '#ecc94b';
    return '#fc8181';
  }

  oeeKlass(pct: number | undefined): string {
    if (pct === undefined || pct === null) return 'text-secondary';
    if (pct >= 75) return 'text-success';
    if (pct >= 50) return 'text-warning';
    return 'text-danger';
  }

  formatSekunder(sek: number): string {
    if (!sek || sek <= 0) return '—';
    const m = Math.floor(sek / 60);
    const s = sek % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
  }

  formatDatum(dt: string): string {
    if (!dt) return '—';
    return dt.replace('T', ' ').slice(0, 16);
  }

  isAktivStation(station: string): boolean {
    return station === this.valdStation;
  }

  trackByIndex(index: number): number {
    return index;
  }
}
