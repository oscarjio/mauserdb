import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KapacitetsplaneringService,
  KpiData,
  DagligKapacitetRad,
  StationUtnyttjandeRad,
  StoppOrsakRad,
  TidFordelningRad,
  VeckaRad,
} from '../../../services/kapacitetsplanering.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kapacitetsplanering',
  templateUrl: './kapacitetsplanering.component.html',
  styleUrls: ['./kapacitetsplanering.component.css'],
  imports: [CommonModule, FormsModule],
})
export class KapacitetsplaneringPage implements OnInit, OnDestroy {

  // Period
  period = 30;
  readonly periodAlternativ = [
    { varde: 7,  etikett: '7 dagar' },
    { varde: 30, etikett: '30 dagar' },
    { varde: 90, etikett: '90 dagar' },
  ];

  // Loading
  loadingKpi             = false;
  loadingDaglig          = false;
  loadingStation         = false;
  loadingStopporsaker    = false;
  loadingTidFordelning   = false;
  loadingVecko           = false;

  // Error
  errorKpi             = false;
  errorDaglig          = false;
  errorStation         = false;
  errorStopporsaker    = false;
  errorTidFordelning   = false;
  errorVecko           = false;

  // Data
  kpiData: KpiData | null                   = null;
  dagligData: DagligKapacitetRad[]          = [];
  dagligGenomsni = 0;
  stationData: StationUtnyttjandeRad[]      = [];
  stopporsakData: StoppOrsakRad[]           = [];
  stoppInfo: { planerad_h: number; drifttid_h: number; stopp_h: number; antal_stopp: number; avg_stopp_min: number } | null = null;
  tidFordelningData: TidFordelningRad[]     = [];
  veckoData: VeckaRad[]                     = [];

  // Charts
  private kapacitetsChart: Chart | null     = null;
  private stationChart: Chart | null        = null;
  private stopporsakChart: Chart | null     = null;
  private tidFordelningChart: Chart | null  = null;

  private destroy$ = new Subject<void>();

  constructor(private svc: KapacitetsplaneringService) {}

  ngOnInit(): void {
    this.laddaAllt();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.kapacitetsChart?.destroy(); }    catch (_) {}
    try { this.stationChart?.destroy(); }       catch (_) {}
    try { this.stopporsakChart?.destroy(); }    catch (_) {}
    try { this.tidFordelningChart?.destroy(); } catch (_) {}
    this.kapacitetsChart    = null;
    this.stationChart       = null;
    this.stopporsakChart    = null;
    this.tidFordelningChart = null;
  }

  // ============================================================
  // Period
  // ============================================================

  byttPeriod(p: number): void {
    if (this.period === p) return;
    this.period = p;
    this.destroyCharts();
    this.laddaAllt();
  }

  laddaAllt(): void {
    this.laddaKpi();
    this.laddaDaglig();
    this.laddaStation();
    this.laddaStopporsaker();
    this.laddaTidFordelning();
    this.laddaVecko();
  }

  // ============================================================
  // KPI
  // ============================================================

  laddaKpi(): void {
    this.loadingKpi = true;
    this.errorKpi   = false;
    this.kpiData    = null;

    this.svc.getKpi()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingKpi = false;
        if (res?.success) {
          this.kpiData = res.data;
        } else {
          this.errorKpi = true;
        }
      });
  }

  // ============================================================
  // Daglig kapacitetsgraf
  // ============================================================

  laddaDaglig(): void {
    this.loadingDaglig = true;
    this.errorDaglig   = false;
    this.dagligData    = [];

    this.svc.getDagligKapacitet(this.period)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDaglig = false;
        if (res?.success) {
          this.dagligData     = res.data.dagdata;
          this.dagligGenomsni = res.data.genomsnitt;
          setTimeout(() => { if (!this.destroy$.closed) this.byggKapacitetsChart(); }, 0);
        } else {
          this.errorDaglig = true;
        }
      });
  }

  private byggKapacitetsChart(): void {
    try { this.kapacitetsChart?.destroy(); } catch (_) {}
    this.kapacitetsChart = null;

    const canvas = document.getElementById('kapacitetsChart') as HTMLCanvasElement;
    if (!canvas || this.dagligData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels      = this.dagligData.map(d => d.datum.slice(5));
    const faktisk     = this.dagligData.map(d => d.faktisk);
    const teorMax     = this.dagligData.map(d => d.teor_max);
    const mal         = this.dagligData.map(d => d.mal ?? null);
    const outnyttjad  = this.dagligData.map(d => d.outnyttjad);
    const genomsnitt  = this.dagligData.map(() => this.dagligGenomsni);

    const harMal = mal.some(v => v !== null);

    this.kapacitetsChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Faktisk produktion (IBC)',
            data: faktisk,
            backgroundColor: 'rgba(79, 209, 197, 0.7)',
            borderColor: '#4fd1c5',
            borderWidth: 1,
            order: 3,
          },
          {
            label: 'Outnyttjad kapacitet',
            data: outnyttjad,
            backgroundColor: 'rgba(160, 174, 192, 0.15)',
            borderColor: 'rgba(160,174,192,0.3)',
            borderWidth: 1,
            order: 4,
          },
          {
            label: 'Teoretisk maxkapacitet',
            data: teorMax,
            type: 'line' as const,
            borderColor: '#fc8181',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 0,
            tension: 0,
            order: 1,
          },
          ...(harMal ? [{
            label: 'Planerat mal',
            data: mal as (number | null)[],
            type: 'line' as const,
            borderColor: '#f6ad55',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [4, 4],
            pointRadius: 0,
            tension: 0,
            order: 2,
          }] : []),
          {
            label: 'Genomsnitt',
            data: genomsnitt,
            type: 'line' as const,
            borderColor: '#68d391',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [2, 4],
            pointRadius: 0,
            tension: 0,
            order: 2,
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
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw} IBC`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal IBC', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Station-utnyttjande
  // ============================================================

  laddaStation(): void {
    this.loadingStation = true;
    this.errorStation   = false;
    this.stationData    = [];

    this.svc.getStationUtnyttjande(this.period)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStation = false;
        if (res?.success) {
          this.stationData = res.data.stationer;
          setTimeout(() => { if (!this.destroy$.closed) this.byggStationChart(); }, 0);
        } else {
          this.errorStation = true;
        }
      });
  }

  private byggStationChart(): void {
    try { this.stationChart?.destroy(); } catch (_) {}
    this.stationChart = null;

    const canvas = document.getElementById('stationChart') as HTMLCanvasElement;
    if (!canvas || this.stationData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.stationData.map(s => s.station);
    const utnyttjande = this.stationData.map(s => s.utnyttjande_pct);
    const colors = utnyttjande.map(v =>
      v >= 80 ? 'rgba(104, 211, 145, 0.8)'
      : v >= 60 ? 'rgba(246, 173, 85, 0.8)'
      : 'rgba(252, 129, 129, 0.8)'
    );

    this.stationChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Kapacitetsutnyttjande %',
            data: utnyttjande,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('0.8', '1')),
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.raw}% kapacitetsutnyttjande`,
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            max: 100,
            ticks: {
              color: '#a0aec0',
              callback: (v) => v + '%',
              font: { size: 10 },
            },
            grid: { color: 'rgba(255,255,255,0.08)' },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 11 } },
            grid: { display: false },
          },
        },
      },
    });
  }

  // ============================================================
  // Stopporsaker
  // ============================================================

  laddaStopporsaker(): void {
    this.loadingStopporsaker = true;
    this.errorStopporsaker   = false;
    this.stopporsakData      = [];

    this.svc.getStopporsaker(this.period)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStopporsaker = false;
        if (res?.success) {
          this.stopporsakData = res.data.orsaker;
          this.stoppInfo = {
            planerad_h:   res.data.planerad_h,
            drifttid_h:   res.data.drifttid_h,
            stopp_h:      res.data.stopp_h,
            antal_stopp:  res.data.antal_stopp,
            avg_stopp_min: res.data.avg_stopp_min,
          };
          setTimeout(() => { if (!this.destroy$.closed) this.byggStopporsakChart(); }, 0);
        } else {
          this.errorStopporsaker = true;
        }
      });
  }

  private byggStopporsakChart(): void {
    try { this.stopporsakChart?.destroy(); } catch (_) {}
    this.stopporsakChart = null;

    const canvas = document.getElementById('stopporsakChart') as HTMLCanvasElement;
    if (!canvas || this.stopporsakData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const filtrad = this.stopporsakData.filter(o => o.sek > 0);
    const labels  = filtrad.map(o => o.namn);
    const data    = filtrad.map(o => o.andel_pct);
    const colors  = [
      'rgba(252, 129, 129, 0.85)',
      'rgba(246, 173, 85, 0.85)',
      'rgba(104, 211, 145, 0.85)',
      'rgba(160, 174, 192, 0.7)',
    ];

    this.stopporsakChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors.slice(0, filtrad.length),
          borderColor: '#1a202c',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.label}: ${item.raw}%`,
            },
          },
        },
      },
    });
  }

  // ============================================================
  // Tid-fordelning
  // ============================================================

  laddaTidFordelning(): void {
    this.loadingTidFordelning = true;
    this.errorTidFordelning   = false;
    this.tidFordelningData    = [];

    this.svc.getTidFordelning(this.period)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTidFordelning = false;
        if (res?.success) {
          this.tidFordelningData = res.data.dagdata;
          setTimeout(() => { if (!this.destroy$.closed) this.byggTidFordelningChart(); }, 0);
        } else {
          this.errorTidFordelning = true;
        }
      });
  }

  private byggTidFordelningChart(): void {
    try { this.tidFordelningChart?.destroy(); } catch (_) {}
    this.tidFordelningChart = null;

    const canvas = document.getElementById('tidFordelningChart') as HTMLCanvasElement;
    if (!canvas || this.tidFordelningData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels    = this.tidFordelningData.map(d => d.datum.slice(5));
    const produktiv = this.tidFordelningData.map(d => d.produktiv_h);
    const idle      = this.tidFordelningData.map(d => d.idle_h);
    const stopp     = this.tidFordelningData.map(d => d.stopp_h);

    this.tidFordelningChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Produktiv tid (h)',
            data: produktiv,
            backgroundColor: 'rgba(104, 211, 145, 0.8)',
            borderColor: '#68d391',
            borderWidth: 1,
          },
          {
            label: 'Idle (h)',
            data: idle,
            backgroundColor: 'rgba(246, 173, 85, 0.7)',
            borderColor: '#f6ad55',
            borderWidth: 1,
          },
          {
            label: 'Stopp (h)',
            data: stopp,
            backgroundColor: 'rgba(252, 129, 129, 0.7)',
            borderColor: '#fc8181',
            borderWidth: 1,
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
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}h`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            max: 8,
            ticks: { color: '#a0aec0', callback: (v) => v + 'h', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Timmar per dag', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Vecko-oversikt
  // ============================================================

  laddaVecko(): void {
    this.loadingVecko = true;
    this.errorVecko   = false;
    this.veckoData    = [];

    this.svc.getVeckoOversikt()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingVecko = false;
        if (res?.success) {
          this.veckoData = res.data.veckor;
        } else {
          this.errorVecko = true;
        }
      });
  }

  // ============================================================
  // Hjalpmetoder
  // ============================================================

  utnyttjandeFarg(pct: number): string {
    if (pct >= 80) return '#68d391';
    if (pct >= 60) return '#f6ad55';
    return '#fc8181';
  }

  utnyttjandeKlass(pct: number): string {
    if (pct >= 80) return 'utnyttjande-hog';
    if (pct >= 60) return 'utnyttjande-medel';
    return 'utnyttjande-lag';
  }

  trendIkon(trend: string): string {
    if (trend === 'upp') return 'fas fa-arrow-up text-success';
    if (trend === 'ned') return 'fas fa-arrow-down text-danger';
    return 'fas fa-minus text-warning';
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '—';
    return datum.slice(5); // MM-DD
  }
}
