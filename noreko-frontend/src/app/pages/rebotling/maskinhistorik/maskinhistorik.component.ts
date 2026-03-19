import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  MaskinhistorikService,
  StationKpiData,
  DrifttidDag,
  OeeTrendDag,
  StoppRad,
  JamforelseRad,
} from '../../../services/maskinhistorik.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-maskinhistorik',
  templateUrl: './maskinhistorik.component.html',
  styleUrls: ['./maskinhistorik.component.scss'],
  imports: [CommonModule],
})
export class MaskinhistorikPage implements OnInit, OnDestroy {

  // Stationer
  stationer: string[] = [];
  valdStation = '';

  // Period
  period = 30;
  readonly periodAlternativ = [
    { varde: 7,  etikett: '7 dagar' },
    { varde: 30, etikett: '30 dagar' },
    { varde: 90, etikett: '90 dagar' },
  ];

  // Loading / Error
  loadingStationer = false;
  loadingKpi       = false;
  loadingDrifttid  = false;
  loadingOeeTrend  = false;
  loadingStopp     = false;
  loadingJamforelse= false;
  errorKpi         = false;
  errorDrifttid    = false;
  errorOeeTrend    = false;
  errorStopp       = false;
  errorJamforelse  = false;

  // Data
  kpi: StationKpiData | null = null;
  drifttidData: DrifttidDag[]    = [];
  oeeTrendData: OeeTrendDag[]    = [];
  stoppData: StoppRad[]          = [];
  jamforelseData: JamforelseRad[]= [];

  // Charts
  private drifttidChart: Chart | null = null;
  private oeeChart: Chart | null      = null;

  // Timers
  private drifttidChartTimer: ReturnType<typeof setTimeout> | null = null;
  private oeeChartTimer: ReturnType<typeof setTimeout> | null = null;

  private destroy$ = new Subject<void>();

  constructor(private svc: MaskinhistorikService) {}

  ngOnInit(): void {
    this.laddaStationer();
    this.laddaJamforelse();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.drifttidChartTimer !== null) { clearTimeout(this.drifttidChartTimer); this.drifttidChartTimer = null; }
    if (this.oeeChartTimer !== null) { clearTimeout(this.oeeChartTimer); this.oeeChartTimer = null; }
    try { this.drifttidChart?.destroy(); } catch (_) {}
    try { this.oeeChart?.destroy(); }     catch (_) {}
    this.drifttidChart = null;
    this.oeeChart      = null;
  }

  // ============================================================
  // Ladda stationer
  // ============================================================

  laddaStationer(): void {
    this.loadingStationer = true;
    this.svc.getStationer()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStationer = false;
        if (res?.success && res.data.stationer.length > 0) {
          this.stationer = res.data.stationer;
          this.valdStation = this.stationer[0];
          this.laddaAllaForStation();
        }
      });
  }

  // ============================================================
  // Val av station / period
  // ============================================================

  valjStation(station: string): void {
    if (this.valdStation === station) return;
    this.valdStation = station;
    this.laddaAllaForStation();
  }

  byttPeriod(p: number): void {
    this.period = p;
    this.laddaAllaForStation();
    this.laddaJamforelse();
  }

  laddaAllaForStation(): void {
    if (!this.valdStation) return;
    this.laddaKpi();
    this.laddaDrifttid();
    this.laddaOeeTrend();
    this.laddaStopp();
  }

  // ============================================================
  // KPI
  // ============================================================

  laddaKpi(): void {
    this.loadingKpi = true;
    this.errorKpi   = false;
    this.kpi        = null;

    this.svc.getStationKpi(this.valdStation, this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingKpi = false;
        if (res?.success) {
          this.kpi = res.data;
        } else {
          this.errorKpi = true;
        }
      });
  }

  // ============================================================
  // Drifttids-graf
  // ============================================================

  laddaDrifttid(): void {
    this.loadingDrifttid = true;
    this.errorDrifttid   = false;
    this.drifttidData    = [];

    this.svc.getStationDrifttid(this.valdStation, this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDrifttid = false;
        if (res?.success) {
          this.drifttidData = res.data.dagdata;
          if (this.drifttidChartTimer !== null) { clearTimeout(this.drifttidChartTimer); }
          this.drifttidChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.byggDrifttidChart(); }, 0);
        } else {
          this.errorDrifttid = true;
        }
      });
  }

  private byggDrifttidChart(): void {
    try { this.drifttidChart?.destroy(); } catch (_) {}
    this.drifttidChart = null;

    const canvas = document.getElementById('drifttidChart') as HTMLCanvasElement;
    if (!canvas || this.drifttidData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels  = this.drifttidData.map(d => d.datum.slice(5));
    const drifttid= this.drifttidData.map(d => d.drifttid_h);
    const ibc     = this.drifttidData.map(d => d.total_ibc);

    if (this.drifttidChart) { (this.drifttidChart as any).destroy(); }
    this.drifttidChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Drifttid (h)',
            data: drifttid,
            backgroundColor: 'rgba(79, 209, 197, 0.55)',
            borderColor: '#4fd1c5',
            borderWidth: 1,
            yAxisID: 'y',
            order: 2,
          },
          {
            label: 'Producerade IBC',
            data: ibc,
            type: 'line' as any,
            borderColor: '#f6ad55',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 2,
            tension: 0.3,
            yAxisID: 'y2',
            order: 1,
          },
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
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            position: 'left',
            ticks: { color: '#a0aec0', callback: (v: string | number) => v + 'h' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Drifttid (h)', color: '#a0aec0', font: { size: 10 } },
          },
          y2: {
            beginAtZero: true,
            position: 'right',
            ticks: { color: '#f6ad55' },
            grid: { display: false },
            title: { display: true, text: 'IBC', color: '#f6ad55', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // OEE-trend
  // ============================================================

  laddaOeeTrend(): void {
    this.loadingOeeTrend = true;
    this.errorOeeTrend   = false;
    this.oeeTrendData    = [];

    this.svc.getStationOeeTrend(this.valdStation, this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOeeTrend = false;
        if (res?.success) {
          this.oeeTrendData = res.data.dagdata;
          if (this.oeeChartTimer !== null) { clearTimeout(this.oeeChartTimer); }
          this.oeeChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.byggOeeChart(); }, 0);
        } else {
          this.errorOeeTrend = true;
        }
      });
  }

  private byggOeeChart(): void {
    try { this.oeeChart?.destroy(); } catch (_) {}
    this.oeeChart = null;

    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas || this.oeeTrendData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.oeeTrendData.map(d => d.datum.slice(5));
    const oee    = this.oeeTrendData.map(d => d.oee_pct);
    const tillg  = this.oeeTrendData.map(d => d.tillganglighet_pct);
    const prest  = this.oeeTrendData.map(d => d.prestanda_pct);
    const kval   = this.oeeTrendData.map(d => d.kvalitet_pct);

    if (this.oeeChart) { (this.oeeChart as any).destroy(); }
    this.oeeChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oee,
            borderColor: '#4fd1c5',
            backgroundColor: 'rgba(79, 209, 197, 0.1)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 3,
            fill: true,
            order: 1,
          },
          {
            label: 'Tillganglighet %',
            data: tillg,
            borderColor: '#63b3ed',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 2,
          },
          {
            label: 'Prestanda %',
            data: prest,
            borderColor: '#f6ad55',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 3,
          },
          {
            label: 'Kvalitet %',
            data: kval,
            borderColor: '#68d391',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 4,
          },
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
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'OEE (%)', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Stopphistorik
  // ============================================================

  laddaStopp(): void {
    this.loadingStopp = true;
    this.errorStopp   = false;
    this.stoppData    = [];

    this.svc.getStationStopp(this.valdStation, 20)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStopp = false;
        if (res?.success) {
          this.stoppData = res.data.stopp;
        } else {
          this.errorStopp = true;
        }
      });
  }

  // ============================================================
  // Jamforelsematris
  // ============================================================

  laddaJamforelse(): void {
    this.loadingJamforelse = true;
    this.errorJamforelse   = false;
    this.jamforelseData    = [];

    this.svc.getJamforelse(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingJamforelse = false;
        if (res?.success) {
          this.jamforelseData = res.data.jamforelse;
        } else {
          this.errorJamforelse = true;
        }
      });
  }

  // ============================================================
  // Hjalpmetoder
  // ============================================================

  oeeFarg(pct: number): string {
    if (pct >= 85) return '#68d391';
    if (pct >= 60) return '#f6ad55';
    return '#fc8181';
  }

  kassationsFarg(pct: number): string {
    if (pct <= 2)  return '#68d391';
    if (pct <= 5)  return '#f6ad55';
    return '#fc8181';
  }

  rangFarg(rang: string): string {
    if (rang === 'bast')   return '#68d391';
    if (rang === 'samst')  return '#fc8181';
    return '';
  }

  formatSek(sek: number): string {
    if (sek <= 0) return '—';
    if (sek < 60) return `${sek}s`;
    const min = Math.floor(sek / 60);
    const rem = sek % 60;
    if (min < 60) return rem > 0 ? `${min}m ${rem}s` : `${min}m`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
  }

  formatCykeltid(sek: number): string {
    if (!sek || sek <= 0) return '—';
    const s = Math.round(sek);
    if (s < 60) return `${s}s`;
    return `${Math.floor(s / 60)}m ${s % 60}s`;
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
