import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, forkJoin, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ProduktionsDashboardService,
  OversiktData,
  ProduktionsDag,
  OeeDag,
  StationStatus,
  AlarmRad,
  IbcRad,
} from '../../../services/produktions-dashboard.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-produktions-dashboard',
  templateUrl: './produktions-dashboard.component.html',
  styleUrls: ['./produktions-dashboard.component.css'],
  imports: [CommonModule],
})
export class ProduktionsDashboardPage implements OnInit, OnDestroy {

  // Data
  oversikt: OversiktData | null = null;
  veckoProd: ProduktionsDag[]   = [];
  veckoOee:  OeeDag[]           = [];
  stationer: StationStatus[]    = [];
  alarm:     AlarmRad[]         = [];
  senIbc:    IbcRad[]           = [];

  // Loading / Error
  loadingOversikt  = false;
  loadingGrafer    = false;
  loadingStationer = false;
  loadingAlarm     = false;
  loadingIbc       = false;
  errorOversikt    = false;
  errorGrafer      = false;
  errorStationer   = false;
  errorAlarm       = false;
  errorIbc         = false;

  // Live puls
  livePuls = false;

  // Tidpunkt senaste uppdatering
  senastUppdaterad: Date | null = null;

  // Charts
  private prodChart: Chart | null = null;
  private oeeChart: Chart | null  = null;

  // Polling-interval
  private pollInterval: ReturnType<typeof setInterval> | null = null;
  private readonly POLL_INTERVAL_MS = 30000;

  // Timers
  private graferChartTimer: ReturnType<typeof setTimeout> | null = null;
  private pulsTimer: ReturnType<typeof setTimeout> | null = null;

  private destroy$ = new Subject<void>();

  constructor(private svc: ProduktionsDashboardService) {}

  ngOnInit(): void {
    this.laddaAllt();
    this.startPolling();
  }

  ngOnDestroy(): void {
    if (this.prodChart) { this.prodChart.destroy(); this.prodChart = null as any; }
    if (this.oeeChart) { this.oeeChart.destroy(); this.oeeChart = null as any; }
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval !== null) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    if (this.graferChartTimer !== null) { clearTimeout(this.graferChartTimer); this.graferChartTimer = null; }
    if (this.pulsTimer !== null) { clearTimeout(this.pulsTimer); this.pulsTimer = null; }
    try { this.prodChart?.destroy(); } catch (_) {}
    try { this.oeeChart?.destroy();  } catch (_) {}
    this.prodChart = null;
    this.oeeChart  = null;
  }

  // ============================================================
  // Polling
  // ============================================================

  private startPolling(): void {
    this.pollInterval = setInterval(() => {
      if (!this.destroy$.closed) {
        this.laddaAllt();
      }
    }, this.POLL_INTERVAL_MS);
  }

  // ============================================================
  // Ladda all data
  // ============================================================

  laddaAllt(): void {
    this.laddaOversikt();
    this.laddaGrafer();
    this.laddaStationer();
    this.laddaAlarm();
    this.laddaIbc();
  }

  laddaOversikt(): void {
    if (this.loadingOversikt) return;
    this.loadingOversikt = true;
    this.errorOversikt   = false;

    this.svc.getOversikt()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOversikt = false;
        if (res?.success) {
          this.oversikt = res.data;
          this.senastUppdaterad = new Date();
          this.pulsera();
        } else {
          this.errorOversikt = true;
        }
      });
  }

  laddaGrafer(): void {
    if (this.loadingGrafer) return;
    this.loadingGrafer = true;
    this.errorGrafer   = false;

    forkJoin([
      this.svc.getVeckoProduktion().pipe(timeout(15000), catchError(() => of(null))),
      this.svc.getVeckoOee().pipe(timeout(15000), catchError(() => of(null))),
    ]).pipe(takeUntil(this.destroy$))
      .subscribe(([prodRes, oeeRes]) => {
        this.loadingGrafer = false;
        if (prodRes?.success) this.veckoProd = prodRes.data.dagar;
        if (oeeRes?.success)  this.veckoOee  = oeeRes.data.dagar;

        if (prodRes?.success || oeeRes?.success) {
          if (this.graferChartTimer !== null) { clearTimeout(this.graferChartTimer); }
          this.graferChartTimer = setTimeout(() => {
            if (!this.destroy$.closed) {
              this.byggProdChart();
              this.byggOeeChart();
            }
          }, 0);
        } else {
          this.errorGrafer = true;
        }
      });
  }

  laddaStationer(): void {
    if (this.loadingStationer) return;
    this.loadingStationer = true;
    this.errorStationer   = false;
    this.svc.getStationerStatus()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStationer = false;
        if (res?.success) { this.stationer = res.data.stationer; }
        else { this.errorStationer = true; }
      });
  }

  laddaAlarm(): void {
    if (this.loadingAlarm) return;
    this.loadingAlarm = true;
    this.errorAlarm   = false;
    this.svc.getSenasteAlarm()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingAlarm = false;
        if (res?.success) { this.alarm = res.data.alarm; }
        else { this.errorAlarm = true; }
      });
  }

  laddaIbc(): void {
    if (this.loadingIbc) return;
    this.loadingIbc = true;
    this.errorIbc   = false;
    this.svc.getSenasteIbc()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingIbc = false;
        if (res?.success) { this.senIbc = res.data.ibc; }
        else { this.errorIbc = true; }
      });
  }

  // ============================================================
  // Pulsanimation pa live-indikatorn
  // ============================================================

  private pulsera(): void {
    this.livePuls = true;
    if (this.pulsTimer !== null) { clearTimeout(this.pulsTimer); }
    this.pulsTimer = setTimeout(() => { if (!this.destroy$.closed) this.livePuls = false; }, 800);
  }

  // ============================================================
  // Graf: Veckovis produktion (stapeldiagram)
  // ============================================================

  private byggProdChart(): void {
    try { this.prodChart?.destroy(); } catch (_) {}
    this.prodChart = null;

    const canvas = document.getElementById('prodChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.veckoProd.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels  = this.veckoProd.map(d => d.veckodag + ' ' + d.datum.slice(5));
    const totalt  = this.veckoProd.map(d => d.total);
    const mal     = this.veckoProd.map(d => d.mal);
    const harMal  = mal.some(m => m > 0);

    const datasets: any[] = [
      {
        label: 'Producerade IBC',
        data: totalt,
        backgroundColor: this.veckoProd.map((_d, i) =>
          i === this.veckoProd.length - 1
            ? 'rgba(79, 209, 197, 0.85)'
            : 'rgba(79, 209, 197, 0.45)'
        ),
        borderColor: '#4fd1c5',
        borderWidth: 1,
        borderRadius: 4,
        order: 2,
      },
    ];

    if (harMal) {
      datasets.push({
        label: 'Mal',
        data: mal,
        type: 'line' as const,
        borderColor: '#f6ad55',
        backgroundColor: 'transparent',
        borderWidth: 2,
        borderDash: [6, 3],
        pointRadius: 3,
        tension: 0,
        order: 1,
      });
    }

    if (this.prodChart) { (this.prodChart as any).destroy(); }
    this.prodChart = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
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
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal IBC', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Graf: OEE-trend (linjediagram med T/P/K)
  // ============================================================

  private byggOeeChart(): void {
    try { this.oeeChart?.destroy(); } catch (_) {}
    this.oeeChart = null;

    const canvas = document.getElementById('oeeChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.veckoOee.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.veckoOee.map(d => d.veckodag + ' ' + d.datum.slice(5));
    const oee    = this.veckoOee.map(d => d.oee_pct);
    const tillg  = this.veckoOee.map(d => d.tillganglighet_pct);
    const prest  = this.veckoOee.map(d => d.prestanda_pct);
    const kval   = this.veckoOee.map(d => d.kvalitet_pct);

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
            backgroundColor: 'rgba(79,209,197,0.12)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 4,
            fill: true,
            order: 1,
          },
          {
            label: 'T (Tillg.) %',
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
            label: 'P (Prest.) %',
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
            label: 'K (Kval.) %',
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
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid:  { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Procent (%)', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Hjalpmetoder (template-utmatning)
  // ============================================================

  oeeFarg(pct: number): string {
    if (pct >= 85) return '#68d391';
    if (pct >= 60) return '#f6ad55';
    return '#fc8181';
  }

  kassationsFarg(farg: string): string {
    if (farg === 'green')  return '#68d391';
    if (farg === 'yellow') return '#f6ad55';
    return '#fc8181';
  }

  kassationsFargText(pct: number): string {
    if (pct <= 2.0) return '#68d391';
    if (pct <= 5.0) return '#f6ad55';
    return '#fc8181';
  }

  trendPil(riktning: string): string {
    if (riktning === 'upp') return '▲';
    if (riktning === 'ned') return '▼';
    return '—';
  }

  trendFarg(riktning: string, invertera = false): string {
    const uppFarg = invertera ? '#fc8181' : '#68d391';
    const nedFarg = invertera ? '#68d391' : '#fc8181';
    if (riktning === 'upp') return uppFarg;
    if (riktning === 'ned') return nedFarg;
    return '#a0aec0';
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

  formatTid(dt: string | null): string {
    if (!dt) return '—';
    return dt.slice(11, 16);
  }

  formatDatum(dt: string | null): string {
    if (!dt) return '—';
    return dt.slice(0, 10);
  }

  formatDatumTid(dt: string | null): string {
    if (!dt) return '—';
    return dt.slice(0, 16).replace('T', ' ');
  }

  kvarvarandeText(min: number): string {
    if (min <= 0) return 'Skiftet slutar snart';
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0) return `${h}h ${m}m kvar`;
    return `${m}m kvar`;
  }

  drifttidFarg(pct: number): string {
    if (pct >= 70) return '#68d391';
    if (pct >= 40) return '#f6ad55';
    return '#fc8181';
  }

  aktivFarg(status: string): string {
    return status === 'kor' ? '#68d391' : '#fc8181';
  }

  aktivText(status: string): string {
    return status === 'kor' ? 'Kor' : 'Stopp';
  }

  senastUppdateradText(): string {
    if (!this.senastUppdaterad) return '';
    const t = this.senastUppdaterad;
    const hh = String(t.getHours()).padStart(2, '0');
    const mm = String(t.getMinutes()).padStart(2, '0');
    const ss = String(t.getSeconds()).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
  }

  // trackBy-funktioner for ngFor-prestanda
  trackByStation(_i: number, s: StationStatus): string { return s.station; }
  trackByAlarm(_i: number, a: AlarmRad): string { return a.start_time; }
  trackByIbc(_i: number, ibc: IbcRad): string { return ibc.datum; }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
