import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../../utils/date-utils';

import {
  VdVeckorapportService,
  KpiJamforelseData,
  TrenderAnomalierData,
  TopBottomData,
  StopporsakerData,
  VeckaSammanfattningData,
  Stopporsak,
} from '../../../services/vd-veckorapport.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-vd-veckorapport',
  templateUrl: './vd-veckorapport.component.html',
  styleUrls: ['./vd-veckorapport.component.css'],
  imports: [CommonModule],
})
export class VdVeckorapportPage implements OnInit, OnDestroy {

  // ---- Laddning ----
  loadingKpi        = false;
  loadingTrender    = false;
  loadingOperatorer = false;
  loadingStopp      = false;

  // ---- Fel ----
  errorKpi        = false;
  errorTrender    = false;
  errorOperatorer = false;
  errorStopp      = false;

  // ---- Data ----
  kpiData:      KpiJamforelseData | null      = null;
  trenderData:  TrenderAnomalierData | null   = null;
  topBottomData: TopBottomData | null         = null;
  stopporsakData: StopporsakerData | null     = null;

  // ---- Period ----
  valdPeriod = 7;
  readonly periodAlternativ = [
    { val: 7,  label: '7 dagar' },
    { val: 14, label: '14 dagar' },
    { val: 30, label: '30 dagar' },
  ];

  // ---- Utskrift / sammanfattning ----
  loadingSammanfattning = false;
  errorSammanfattning   = false;
  sammanfattningData: VeckaSammanfattningData | null = null;
  visaSammanfattning    = false;

  // ---- Chart ----
  private dagligChart: Chart | null = null;

  // ---- Timers ----
  private dagligChartTimer: ReturnType<typeof setTimeout> | null = null;
  private scrollTimer: ReturnType<typeof setTimeout> | null = null;

  private destroy$ = new Subject<void>();

  constructor(private svc: VdVeckorapportService) {}

  ngOnInit(): void {
    this.laddaAllt();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.dagligChartTimer !== null) { clearTimeout(this.dagligChartTimer); this.dagligChartTimer = null; }
    if (this.scrollTimer !== null) { clearTimeout(this.scrollTimer); this.scrollTimer = null; }
    try { this.dagligChart?.destroy(); } catch (_) {}
    this.dagligChart = null;
  }

  // ================================================================
  // Laddning
  // ================================================================

  laddaAllt(): void {
    this.laddaKpi();
    this.laddaTrender();
    this.laddaOperatorer();
    this.laddaStopp();
  }

  onPeriodChange(val: number): void {
    this.valdPeriod = val;
    this.laddaOperatorer();
    this.laddaStopp();
  }

  private laddaKpi(): void {
    this.loadingKpi = true;
    this.errorKpi   = false;

    this.svc.getKpiJamforelse()
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingKpi = false;
          if (res?.success) {
            this.kpiData = res.data;
            if (this.dagligChartTimer !== null) { clearTimeout(this.dagligChartTimer); }
            this.dagligChartTimer = setTimeout(() => {
              if (!this.destroy$.closed) this.byggDagligChart();
            }, 0);
          } else {
            this.errorKpi = true;
          }
        },
        error: () => {
          this.loadingKpi = false;
          this.errorKpi   = true;
        },
      });
  }

  private laddaTrender(): void {
    this.loadingTrender = true;
    this.errorTrender   = false;

    this.svc.getTrenderAnomalier()
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingTrender = false;
          if (res?.success) {
            this.trenderData = res.data;
          } else {
            this.errorTrender = true;
          }
        },
        error: () => {
          this.loadingTrender = false;
          this.errorTrender   = true;
        },
      });
  }

  private laddaOperatorer(): void {
    this.loadingOperatorer = true;
    this.errorOperatorer   = false;

    this.svc.getTopBottomOperatorer(this.valdPeriod)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingOperatorer = false;
          if (res?.success) {
            this.topBottomData = res.data;
          } else {
            this.errorOperatorer = true;
          }
        },
        error: () => {
          this.loadingOperatorer = false;
          this.errorOperatorer   = true;
        },
      });
  }

  private laddaStopp(): void {
    this.loadingStopp = true;
    this.errorStopp   = false;

    this.svc.getStopporsaker(this.valdPeriod)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingStopp = false;
          if (res?.success) {
            this.stopporsakData = res.data;
          } else {
            this.errorStopp = true;
          }
        },
        error: () => {
          this.loadingStopp = false;
          this.errorStopp   = true;
        },
      });
  }

  // ================================================================
  // Utskriftsvänlig sammanfattning
  // ================================================================

  genereraSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning   = false;
    this.visaSammanfattning    = false;

    this.svc.getVeckaSammanfattning()
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingSammanfattning = false;
          if (res?.success) {
            this.sammanfattningData = res.data;
            this.visaSammanfattning = true;
            if (this.scrollTimer !== null) { clearTimeout(this.scrollTimer); }
            this.scrollTimer = setTimeout(() => { window.scrollTo({ top: 0, behavior: 'smooth' }); }, 50);
          } else {
            this.errorSammanfattning = true;
          }
        },
        error: () => {
          this.loadingSammanfattning = false;
          this.errorSammanfattning   = true;
        },
      });
  }

  skrivUt(): void {
    window.print();
  }

  stangSammanfattning(): void {
    this.visaSammanfattning = false;
    this.sammanfattningData = null;
  }

  // ================================================================
  // Chart.js — Daglig produktion
  // ================================================================

  private byggDagligChart(): void {
    try { this.dagligChart?.destroy(); } catch (_) {}
    this.dagligChart = null;

    const canvas = document.getElementById('dagligProduktionChart') as HTMLCanvasElement | null;
    if (!canvas || !this.kpiData?.daglig_produktion?.length) return;

    const daglig  = this.kpiData.daglig_produktion;
    const labels  = daglig.map(d => this.formatDatum(d.dag));
    const ibc     = daglig.map(d => d.ibc);
    const kassation = daglig.map(d => d.kassation);

    if (this.dagligChart) { (this.dagligChart as any).destroy(); }
    this.dagligChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC producerade',
            data: ibc,
            backgroundColor: '#63b3ed88',
            borderColor: '#63b3ed',
            borderWidth: 1,
            yAxisID: 'y',
          },
          {
            type: 'line' as any,
            label: 'Kassation %',
            data: kassation,
            borderColor: '#fc8181',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 4,
            yAxisID: 'y2',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          tooltip: { intersect: false, mode: 'nearest' },
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: '#374151' },
          },
          y: {
            position: 'left',
            ticks: { color: '#63b3ed' },
            grid:  { color: '#374151' },
            title: { display: true, text: 'IBC', color: '#63b3ed' },
            beginAtZero: true,
          },
          y2: {
            position: 'right',
            ticks: { color: '#fc8181', callback: (v: any) => v + ' %' },
            grid:  { drawOnChartArea: false },
            title: { display: true, text: 'Kassation %', color: '#fc8181' },
            beginAtZero: true,
            max: 100,
          },
        },
      },
    });
  }

  // ================================================================
  // Template-hjälpare
  // ================================================================

  getTrendIkon(trend: string): string {
    if (trend === 'upp')   return '↑';
    if (trend === 'ned')   return '↓';
    return '→';
  }

  getTrendFarg(kpi: string, trend: string): string {
    // För kassation är ned bättre
    const positivtUpp = kpi !== 'kassation';
    if (trend === 'upp') return positivtUpp ? '#48bb78' : '#fc8181';
    if (trend === 'ned') return positivtUpp ? '#fc8181' : '#48bb78';
    return '#a0aec0';
  }

  getAnomaliFarg(allvarlighet: string): string {
    if (allvarlighet === 'positiv')  return '#48bb78';
    if (allvarlighet === 'kritisk')  return '#fc8181';
    return '#f6ad55';
  }

  getAnomaliIkon(allvarlighet: string): string {
    if (allvarlighet === 'positiv') return '↑';
    if (allvarlighet === 'kritisk') return '!';
    return '⚠';
  }

  getTrendTextklass(trend: string): string {
    if (trend === 'stiger' || trend === 'forbattras') return 'trend-positiv';
    if (trend === 'sjunker' || trend === 'forsamras') return 'trend-negativ';
    return 'trend-stabil';
  }

  formatDatum(datum: string): string {
    if (!datum) return '';
    const delar = datum.split('-');
    if (delar.length < 3) return datum;
    const dagar = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    const d = parseLocalDate(datum);
    return `${dagar[d.getDay()]} ${delar[2]}/${delar[1]}`;
  }

  formatKpiNamn(kpi: string): string {
    const map: Record<string, string> = {
      oee:        'OEE',
      produktion: 'Produktion (IBC)',
      kassation:  'Kassation',
      drifttid_h: 'Drifttid (h)',
    };
    return map[kpi] ?? kpi;
  }

  formatKpiEnhet(kpi: string): string {
    if (kpi === 'oee' || kpi === 'kassation') return ' %';
    if (kpi === 'drifttid_h') return ' h';
    return '';
  }

  getStopparAndel(stopp: Stopporsak, data: StopporsakerData): number {
    const total = data.stopporsaker.reduce((sum, s) => sum + s.total_min, 0);
    return total > 0 ? (stopp.total_min / total) * 100 : 0;
  }

  diffPlusMinus(val: number): string {
    return val >= 0 ? '+' + val : '' + val;
  }

  readonly kpiLista: readonly string[] = ['oee', 'produktion', 'kassation', 'drifttid_h'] as const;

  sammanfattningKpiDiff(kpi: string): number {
    if (!this.sammanfattningData) return 0;
    const denna = (this.sammanfattningData.kpi_denna as any)[kpi] ?? 0;
    const forra = (this.sammanfattningData.kpi_forra as any)[kpi] ?? 0;
    return round2(denna - forra);
  }

  sammanfattningDiffPct(kpi: string): number | null {
    if (!this.sammanfattningData) return null;
    const denna = (this.sammanfattningData.kpi_denna as any)[kpi] ?? 0;
    const forra = (this.sammanfattningData.kpi_forra as any)[kpi] ?? 0;
    if (forra === 0) return null;
    return round2(((denna - forra) / forra) * 100);
  }

  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}

function round2(v: number): number {
  return Math.round(v * 100) / 100;
}
