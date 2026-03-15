import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingTrendanalysService,
  TrendKort,
  TrenderData,
  DagligHistorikRad,
  VeckoRad,
  Anomali,
  PrognisData,
  PrognosDag,
} from '../../../services/rebotling-trendanalys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-rebotling-trendanalys',
  templateUrl: './rebotling-trendanalys.component.html',
  styleUrls: ['./rebotling-trendanalys.component.css'],
  imports: [CommonModule],
})
export class RebotlingTrendanalysPage implements OnInit, OnDestroy {

  // Loading states
  loadingTrender      = false;
  loadingHistorik     = false;
  loadingVecko        = false;
  loadingAnomalier    = false;
  loadingPrognos      = false;

  // Error states
  errorTrender        = false;
  errorHistorik       = false;
  errorVecko          = false;
  errorAnomalier      = false;
  errorPrognos        = false;

  // Data
  trenderData: TrenderData | null         = null;
  historikData: DagligHistorikRad[]       = [];
  veckoData: VeckoRad[]                   = [];
  anomalierData: Anomali[]                = [];
  prognosData: PrognisData | null         = null;

  // Graf: aktiv period
  aktivPeriod = 90;
  readonly periodAlternativ = [
    { varde: 30, etikett: '30 dagar' },
    { varde: 60, etikett: '60 dagar' },
    { varde: 90, etikett: '90 dagar' },
  ];

  // Dataset toggle
  visaOee        = true;
  visaProduktion = true;
  visaKassation  = true;

  // Charts
  private huvudChart: Chart | null = null;
  private sparkOeeChart: Chart | null = null;
  private sparkProdChart: Chart | null = null;
  private sparkKassChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private pollingInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: RebotlingTrendanalysService) {}

  ngOnInit(): void {
    this.laddaAllt();
    this.pollingInterval = setInterval(() => {
      if (!this.destroy$.closed) this.laddaAllt();
    }, 60000);
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
    try { this.huvudChart?.destroy(); }     catch (_) {}
    try { this.sparkOeeChart?.destroy(); }  catch (_) {}
    try { this.sparkProdChart?.destroy(); } catch (_) {}
    try { this.sparkKassChart?.destroy(); } catch (_) {}
    this.huvudChart     = null;
    this.sparkOeeChart  = null;
    this.sparkProdChart = null;
    this.sparkKassChart = null;
  }

  // ============================================================
  // Laddning
  // ============================================================

  laddaAllt(): void {
    this.laddaTrender();
    this.laddaHistorik();
    this.laddaVecko();
    this.laddaAnomalier();
    this.laddaPrognos();
  }

  laddaTrender(): void {
    this.loadingTrender = true;
    this.errorTrender   = false;
    this.svc.getTrender()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrender = false;
        if (res?.success) {
          this.trenderData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggSparklines(); }, 0);
        } else {
          this.errorTrender = true;
        }
      });
  }

  laddaHistorik(): void {
    this.loadingHistorik = true;
    this.errorHistorik   = false;
    this.historikData    = [];
    this.svc.getDagligHistorik()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingHistorik = false;
        if (res?.success) {
          this.historikData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggHuvudChart(); }, 0);
        } else {
          this.errorHistorik = true;
        }
      });
  }

  laddaVecko(): void {
    this.loadingVecko = true;
    this.errorVecko   = false;
    this.veckoData    = [];
    this.svc.getVeckosammanfattning()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingVecko = false;
        if (res?.success) {
          this.veckoData = res.data;
        } else {
          this.errorVecko = true;
        }
      });
  }

  laddaAnomalier(): void {
    this.loadingAnomalier = true;
    this.errorAnomalier   = false;
    this.anomalierData    = [];
    this.svc.getAnomalier()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingAnomalier = false;
        if (res?.success) {
          this.anomalierData = res.data;
        } else {
          this.errorAnomalier = true;
        }
      });
  }

  laddaPrognos(): void {
    this.loadingPrognos = true;
    this.errorPrognos   = false;
    this.prognosData    = null;
    this.svc.getPrognos()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingPrognos = false;
        if (res?.success) {
          this.prognosData = res.data;
          // Lägg till prognosen i huvudgrafen om historik finns
          if (this.historikData.length > 0) {
            setTimeout(() => { if (!this.destroy$.closed) this.byggHuvudChart(); }, 0);
          }
        } else {
          this.errorPrognos = true;
        }
      });
  }

  // ============================================================
  // Period
  // ============================================================

  byttPeriod(p: number): void {
    if (this.aktivPeriod === p) return;
    this.aktivPeriod = p;
    try { this.huvudChart?.destroy(); } catch (_) {}
    this.huvudChart = null;
    setTimeout(() => { if (!this.destroy$.closed) this.byggHuvudChart(); }, 0);
  }

  toggleDataset(dataset: 'oee' | 'produktion' | 'kassation'): void {
    if (dataset === 'oee')        this.visaOee        = !this.visaOee;
    if (dataset === 'produktion') this.visaProduktion = !this.visaProduktion;
    if (dataset === 'kassation')  this.visaKassation  = !this.visaKassation;
    try { this.huvudChart?.destroy(); } catch (_) {}
    this.huvudChart = null;
    setTimeout(() => { if (!this.destroy$.closed) this.byggHuvudChart(); }, 0);
  }

  // ============================================================
  // Sparklines
  // ============================================================

  private byggSparklines(): void {
    if (!this.trenderData) return;
    this.byggSparkline('sparkOee',  this.trenderData.oee.sparkline,       '#4299e1', this.sparkOeeChart,  v => { this.sparkOeeChart  = v; });
    this.byggSparkline('sparkProd', this.trenderData.produktion.sparkline, '#48bb78', this.sparkProdChart, v => { this.sparkProdChart = v; });
    this.byggSparkline('sparkKass', this.trenderData.kassation.sparkline,  '#fc8181', this.sparkKassChart, v => { this.sparkKassChart = v; });
  }

  private byggSparkline(
    canvasId: string,
    data: { datum: string; varde: number }[],
    color: string,
    existing: Chart | null,
    setter: (c: Chart | null) => void
  ): void {
    try { existing?.destroy(); } catch (_) {}
    setter(null);

    const canvas = document.getElementById(canvasId) as HTMLCanvasElement;
    if (!canvas || data.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(d => d.datum.slice(5)),
        datasets: [{
          data: data.map(d => d.varde),
          borderColor: color,
          backgroundColor: color.replace(')', ', 0.1)').replace('rgb', 'rgba'),
          borderWidth: 1.5,
          pointRadius: 0,
          tension: 0.3,
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: { display: false },
          y: { display: false },
        },
      },
    });
    setter(chart);
  }

  // ============================================================
  // Huvudgraf
  // ============================================================

  private byggHuvudChart(): void {
    try { this.huvudChart?.destroy(); } catch (_) {}
    this.huvudChart = null;

    const canvas = document.getElementById('huvudChart') as HTMLCanvasElement;
    if (!canvas || this.historikData.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Filtrera på vald period
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - this.aktivPeriod);
    const filtered = this.historikData.filter(d => new Date(d.datum) >= cutoff);

    if (filtered.length === 0) return;

    const labels   = filtered.map(d => d.datum.slice(5));
    const datasets: any[] = [];

    // Linjär regression för OEE
    const oeeVarden = filtered.map(d => d.oee);
    const prodVarden = filtered.map(d => d.produktion);
    const kassVarden = filtered.map(d => d.kassation);

    const oeeReg  = this.linjarRegression(oeeVarden);
    const prodReg = this.linjarRegression(prodVarden);
    const kassReg = this.linjarRegression(kassVarden);

    const n = filtered.length;
    const oeeTrendlinje  = Array.from({length: n}, (_, i) => +(oeeReg.slope  * i + oeeReg.intercept).toFixed(2));
    const prodTrendlinje = Array.from({length: n}, (_, i) => +(prodReg.slope * i + prodReg.intercept).toFixed(1));
    const kassTrendlinje = Array.from({length: n}, (_, i) => +(kassReg.slope * i + kassReg.intercept).toFixed(2));

    // Prognos-data
    const prognosDagar: PrognosDag[] = this.prognosData?.dagar ?? [];
    const prognosLabels = prognosDagar.map(d => d.datum.slice(5));
    const allLabels = [...labels, ...prognosLabels];

    // Prognos-zon (nullar för historik, värden för prognos)
    const oeePrognos  = [...Array(n).fill(null), ...prognosDagar.map(d => d.oee)];
    const prodPrognos = [...Array(n).fill(null), ...prognosDagar.map(d => d.produktion)];
    const kassPrognos = [...Array(n).fill(null), ...prognosDagar.map(d => d.kassation)];
    const oeeHist     = [...oeeVarden,  ...Array(prognosDagar.length).fill(null)];
    const prodHist    = [...prodVarden, ...Array(prognosDagar.length).fill(null)];
    const kassHist    = [...kassVarden, ...Array(prognosDagar.length).fill(null)];
    const oeeMA7    = [...filtered.map(d => d.oee_ma7),  ...Array(prognosDagar.length).fill(null)];
    const prodMA7   = [...filtered.map(d => d.prod_ma7), ...Array(prognosDagar.length).fill(null)];
    const kassMA7   = [...filtered.map(d => d.kass_ma7), ...Array(prognosDagar.length).fill(null)];
    const oeeTrend  = [...oeeTrendlinje,  ...Array(prognosDagar.length).fill(null)];
    const prodTrend = [...prodTrendlinje, ...Array(prognosDagar.length).fill(null)];
    const kassTrend = [...kassTrendlinje, ...Array(prognosDagar.length).fill(null)];

    if (this.visaOee) {
      datasets.push({
        label: 'OEE %',
        data: oeeHist,
        borderColor: '#4299e1',
        backgroundColor: 'rgba(66,153,225,0.08)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yPct',
        fill: false,
        order: 3,
      });
      datasets.push({
        label: 'OEE 7d MA',
        data: oeeMA7,
        borderColor: '#63b3ed',
        backgroundColor: 'transparent',
        borderWidth: 1.5,
        borderDash: [4, 2],
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yPct',
        fill: false,
        order: 2,
      });
      datasets.push({
        label: 'OEE Trend',
        data: oeeTrend,
        borderColor: 'rgba(66,153,225,0.5)',
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderDash: [8, 4],
        pointRadius: 0,
        tension: 0,
        yAxisID: 'yPct',
        fill: false,
        order: 1,
      });
      if (prognosDagar.length > 0) {
        datasets.push({
          label: 'OEE Prognos',
          data: oeePrognos,
          borderColor: 'rgba(66,153,225,0.7)',
          backgroundColor: 'rgba(66,153,225,0.12)',
          borderWidth: 2,
          borderDash: [6, 3],
          pointRadius: 3,
          tension: 0.2,
          yAxisID: 'yPct',
          fill: true,
          order: 4,
        });
      }
    }

    if (this.visaProduktion) {
      datasets.push({
        label: 'Produktion (IBC)',
        data: prodHist,
        borderColor: '#48bb78',
        backgroundColor: 'rgba(72,187,120,0.08)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yProd',
        fill: false,
        order: 3,
      });
      datasets.push({
        label: 'Produktion 7d MA',
        data: prodMA7,
        borderColor: '#68d391',
        backgroundColor: 'transparent',
        borderWidth: 1.5,
        borderDash: [4, 2],
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yProd',
        fill: false,
        order: 2,
      });
      datasets.push({
        label: 'Produktion Trend',
        data: prodTrend,
        borderColor: 'rgba(72,187,120,0.5)',
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderDash: [8, 4],
        pointRadius: 0,
        tension: 0,
        yAxisID: 'yProd',
        fill: false,
        order: 1,
      });
      if (prognosDagar.length > 0) {
        datasets.push({
          label: 'Produktion Prognos',
          data: prodPrognos,
          borderColor: 'rgba(72,187,120,0.7)',
          backgroundColor: 'rgba(72,187,120,0.12)',
          borderWidth: 2,
          borderDash: [6, 3],
          pointRadius: 3,
          tension: 0.2,
          yAxisID: 'yProd',
          fill: true,
          order: 4,
        });
      }
    }

    if (this.visaKassation) {
      datasets.push({
        label: 'Kassation %',
        data: kassHist,
        borderColor: '#fc8181',
        backgroundColor: 'rgba(252,129,129,0.08)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yPct',
        fill: false,
        order: 3,
      });
      datasets.push({
        label: 'Kassation 7d MA',
        data: kassMA7,
        borderColor: '#feb2b2',
        backgroundColor: 'transparent',
        borderWidth: 1.5,
        borderDash: [4, 2],
        pointRadius: 0,
        tension: 0.2,
        yAxisID: 'yPct',
        fill: false,
        order: 2,
      });
      datasets.push({
        label: 'Kassation Trend',
        data: kassTrend,
        borderColor: 'rgba(252,129,129,0.5)',
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderDash: [8, 4],
        pointRadius: 0,
        tension: 0,
        yAxisID: 'yPct',
        fill: false,
        order: 1,
      });
      if (prognosDagar.length > 0) {
        datasets.push({
          label: 'Kassation Prognos',
          data: kassPrognos,
          borderColor: 'rgba(252,129,129,0.7)',
          backgroundColor: 'rgba(252,129,129,0.12)',
          borderWidth: 2,
          borderDash: [6, 3],
          pointRadius: 3,
          tension: 0.2,
          yAxisID: 'yPct',
          fill: true,
          order: 4,
        });
      }
    }

    this.huvudChart = new Chart(ctx, {
      type: 'line',
      data: { labels: allLabels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 10 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => {
                const v = item.raw as number | null;
                if (v === null || v === undefined) return '';
                return ` ${item.dataset.label}: ${v}`;
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
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: '% (OEE / Kassation)', color: '#a0aec0', font: { size: 10 } },
          },
          yProd: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v) => v + ' IBC' },
            grid: { display: false },
            title: { display: true, text: 'Produktion (IBC)', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // ============================================================
  // Linjär regression (frontend, för trendlinjer)
  // ============================================================

  private linjarRegression(values: number[]): { slope: number; intercept: number } {
    const n = values.length;
    if (n < 2) return { slope: 0, intercept: values[0] ?? 0 };
    let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
    for (let i = 0; i < n; i++) {
      sumX  += i;
      sumY  += values[i];
      sumXY += i * values[i];
      sumX2 += i * i;
    }
    const denom = n * sumX2 - sumX * sumX;
    if (denom === 0) return { slope: 0, intercept: sumY / n };
    const slope     = (n * sumXY - sumX * sumY) / denom;
    const intercept = (sumY - slope * sumX) / n;
    return { slope: +slope.toFixed(4), intercept: +intercept.toFixed(4) };
  }

  // ============================================================
  // Template-hjälpare
  // ============================================================

  trendPilKlass(kort: TrendKort, inverterad = false): string {
    const trend = inverterad
      ? (kort.trend === 'up' ? 'down' : kort.trend === 'down' ? 'up' : 'stable')
      : kort.trend;
    if (trend === 'up')   return 'fas fa-arrow-up trend-up';
    if (trend === 'down') return 'fas fa-arrow-down trend-down';
    return 'fas fa-minus trend-stable';
  }

  alertKlass(alert: string): string {
    if (alert === 'critical') return 'alert-critical';
    if (alert === 'warning')  return 'alert-warning';
    return '';
  }

  slopeFarg(slope: number, inverterad = false): string {
    const v = inverterad ? -slope : slope;
    if (v > 0) return '#48bb78';
    if (v < 0) return '#fc8181';
    return '#ecc94b';
  }

  formatSlope(slope: number): string {
    const sign = slope > 0 ? '+' : '';
    return `${sign}${slope.toFixed(2)}/dag`;
  }

  diffPilKlass(diff: number | null, inverterad = false): string {
    if (diff === null) return 'text-secondary';
    const pos = inverterad ? diff < 0 : diff > 0;
    return pos ? 'text-success' : diff === 0 ? 'text-warning' : 'text-danger';
  }

  diffPil(diff: number | null, inverterad = false): string {
    if (diff === null) return '—';
    const pos = inverterad ? diff < 0 : diff > 0;
    const pil = pos ? '▲' : diff === 0 ? '●' : '▼';
    const val = Math.abs(diff);
    return `${pil} ${val}`;
  }

  avvikelseKlass(anomali: Anomali): string {
    return anomali.positivt ? 'anomali-positiv' : 'anomali-negativ';
  }

  formatDatum(datum: string): string {
    return datum.slice(5); // MM-DD
  }

  veckaRubrik(rad: VeckoRad): string {
    return `V${rad.vecka} (${rad.from_datum.slice(5)} – ${rad.to_datum.slice(5)})`;
  }
  trackByIndex(index: number): number { return index; }
}
