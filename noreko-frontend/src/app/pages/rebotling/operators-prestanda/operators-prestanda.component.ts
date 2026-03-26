import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  OperatorsPrestandaService,
  OperatorScatterPunkt,
  RankingRad,
  SkiftData,
  DagligDetalj,
  VeckaRad,
} from '../../../services/operators-prestanda.service';

Chart.register(...registerables);

interface PeriodAlternativ { val: number; label: string; }
interface SkiftAlternativ  { val: string; label: string; }
type SortKolumn = 'rank' | 'operator_namn' | 'antal_ibc' | 'medel_cykeltid' | 'kassationsgrad' | 'oee';

@Component({
  standalone: true,
  selector: 'app-operators-prestanda',
  templateUrl: './operators-prestanda.component.html',
  styleUrls: ['./operators-prestanda.component.css'],
  imports: [CommonModule, FormsModule],
})
export class OperatorsPrestandaPage implements OnInit, OnDestroy {

  // ---- Period & filter ----
  period = 30;
  readonly periodAlternativ: PeriodAlternativ[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  valtSkift = 'alla';
  readonly skiftAlternativ: SkiftAlternativ[] = [
    { val: 'alla',  label: 'Alla skift' },
    { val: 'dag',   label: 'Dag (06-14)' },
    { val: 'kvall', label: 'Kväll (14-22)' },
    { val: 'natt',  label: 'Natt (22-06)' },
  ];

  // ---- Laddning ----
  loadingScatter      = false;
  loadingRanking      = false;
  loadingTeam         = false;
  loadingDetalj       = false;
  loadingUtveckling   = false;

  // ---- Fel ----
  errorScatter     = false;
  errorRanking     = false;
  errorTeam        = false;
  errorDetalj      = false;
  errorUtveckling  = false;

  // ---- Data ----
  scatterData: OperatorScatterPunkt[] = [];
  medelCykeltid    = 0;
  medelKvalitet    = 0;
  rankingData: RankingRad[]   = [];
  rankingTotalt    = 0;
  teamData: SkiftData[]       = [];
  detaljOperator: number | null = null;
  detaljNamn       = '';
  detaljDaglig: DagligDetalj[] = [];
  detaljStreak     = 0;
  detaljBastaDag: DagligDetalj | null    = null;
  detaljSammstaDag: DagligDetalj | null  = null;
  utvecklingData: VeckaRad[]  = [];
  utvecklingTrend: string     = 'neutral';

  // ---- Ranking-sortering ----
  rankingSortBy: SortKolumn  = 'rank';
  rankingSortAsc             = true;
  sortedRanking: RankingRad[] = [];

  // ---- Charts ----
  private scatterChart:   Chart | null = null;
  private detaljChart:    Chart | null = null;
  private utvecklingChart: Chart | null = null;

  // ---- Timers ----
  private scatterChartTimer: ReturnType<typeof setTimeout> | null = null;
  private detaljChartTimer: ReturnType<typeof setTimeout> | null = null;
  private utvecklingChartTimer: ReturnType<typeof setTimeout> | null = null;

  private destroy$ = new Subject<void>();

  // Skift-färger
  readonly SKIFT_FARG: Record<string, string> = {
    dag:   '#63b3ed',
    kvall: '#48bb78',
    natt:  '#b794f4',
  };

  constructor(private svc: OperatorsPrestandaService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.scatterChartTimer !== null) { clearTimeout(this.scatterChartTimer); this.scatterChartTimer = null; }
    if (this.detaljChartTimer !== null) { clearTimeout(this.detaljChartTimer); this.detaljChartTimer = null; }
    if (this.utvecklingChartTimer !== null) { clearTimeout(this.utvecklingChartTimer); this.utvecklingChartTimer = null; }
    this.destroyAllCharts();
  }

  private destroyAllCharts(): void {
    try { this.scatterChart?.destroy();    } catch (_) {}
    try { this.detaljChart?.destroy();     } catch (_) {}
    try { this.utvecklingChart?.destroy(); } catch (_) {}
    this.scatterChart    = null;
    this.detaljChart     = null;
    this.utvecklingChart = null;
  }

  // ================================================================
  // Filter
  // ================================================================

  onPeriodChange(val: number): void {
    this.period = val;
    this.loadAll();
  }

  onSkiftChange(val: string): void {
    this.valtSkift = val;
    this.loadScatter();
  }

  private loadAll(): void {
    this.loadScatter();
    this.loadRanking('ibc');
    this.loadTeam();
  }

  // ================================================================
  // Scatter-data
  // ================================================================

  loadScatter(): void {
    this.loadingScatter = true;
    this.errorScatter   = false;

    this.svc.getScatterData(this.period, this.valtSkift)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingScatter = false;
          if (res?.success) {
            this.scatterData   = res.data?.operatorer ?? [];
            this.medelCykeltid = res.data?.medel_cykeltid ?? 0;
            this.medelKvalitet = res.data?.medel_kvalitet_pct ?? 0;
            if (this.scatterChartTimer !== null) { clearTimeout(this.scatterChartTimer); }
            this.scatterChartTimer = setTimeout(() => {
              if (!this.destroy$.closed) this.buildScatterChart();
            }, 0);
          } else {
            this.errorScatter = true;
            this.scatterData  = [];
          }
        },
        error: () => {
          this.loadingScatter = false;
          this.errorScatter   = true;
        },
      });
  }

  // ================================================================
  // Ranking
  // ================================================================

  loadRanking(sortBy: string): void {
    this.loadingRanking = true;
    this.errorRanking   = false;

    this.svc.getRanking(sortBy, this.period)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingRanking = false;
          if (res?.success) {
            this.rankingData   = res.data?.ranking ?? [];
            this.rankingTotalt = res.data?.totalt  ?? 0;
            this.sortedRanking = [...this.rankingData];
            this.rankingSortBy = 'rank';
            this.rankingSortAsc = true;
          } else {
            this.errorRanking = true;
            this.rankingData  = [];
            this.sortedRanking = [];
          }
        },
        error: () => {
          this.loadingRanking = false;
          this.errorRanking   = true;
        },
      });
  }

  sortRanking(kol: SortKolumn): void {
    if (this.rankingSortBy === kol) {
      this.rankingSortAsc = !this.rankingSortAsc;
    } else {
      this.rankingSortBy  = kol;
      this.rankingSortAsc = kol === 'kassationsgrad' || kol === 'medel_cykeltid';
    }

    this.sortedRanking = [...this.rankingData].sort((a, b) => {
      let aVal: number | string;
      let bVal: number | string;
      switch (kol) {
        case 'rank':            aVal = a.rank;            bVal = b.rank;            break;
        case 'operator_namn':   aVal = a.operator_namn;   bVal = b.operator_namn;   break;
        case 'antal_ibc':       aVal = a.antal_ibc;       bVal = b.antal_ibc;       break;
        case 'medel_cykeltid':  aVal = a.medel_cykeltid;  bVal = b.medel_cykeltid;  break;
        case 'kassationsgrad':  aVal = a.kassationsgrad;  bVal = b.kassationsgrad;  break;
        case 'oee':             aVal = a.oee;             bVal = b.oee;             break;
        default:                aVal = a.rank;            bVal = b.rank;
      }

      if (typeof aVal === 'string') {
        return this.rankingSortAsc
          ? (aVal as string).localeCompare(bVal as string, 'sv')
          : (bVal as string).localeCompare(aVal as string, 'sv');
      }
      return this.rankingSortAsc
        ? (aVal as number) - (bVal as number)
        : (bVal as number) - (aVal as number);
    });
  }

  getRadFarg(op: RankingRad): string {
    if (this.rankingTotalt < 7) return '';
    if (op.rank <= 3)                       return 'row-top';
    if (op.rank > this.rankingTotalt - 3)   return 'row-bottom';
    return '';
  }

  // ================================================================
  // Team-jämförelse
  // ================================================================

  loadTeam(): void {
    this.loadingTeam = true;
    this.errorTeam   = false;

    this.svc.getTeamjamforelse(this.period)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingTeam = false;
          if (res?.success) {
            this.teamData = res.data?.skift ?? [];
          } else {
            this.errorTeam = true;
            this.teamData  = [];
          }
        },
        error: () => {
          this.loadingTeam = false;
          this.errorTeam   = true;
        },
      });
  }

  // ================================================================
  // Detaljvy per operatör
  // ================================================================

  toggleDetalj(op: RankingRad): void {
    if (this.detaljOperator === op.operator_id) {
      this.detaljOperator = null;
      try { this.detaljChart?.destroy();     } catch (_) {}
      try { this.utvecklingChart?.destroy(); } catch (_) {}
      this.detaljChart     = null;
      this.utvecklingChart = null;
      return;
    }
    this.detaljOperator = op.operator_id;
    this.loadDetalj(op.operator_id);
    this.loadUtveckling(op.operator_id);
  }

  loadDetalj(id: number): void {
    this.loadingDetalj = true;
    this.errorDetalj   = false;

    this.svc.getOperatorDetalj(id)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingDetalj = false;
          if (res?.success) {
            this.detaljNamn     = res.data?.operator_namn ?? '';
            this.detaljDaglig   = res.data?.daglig         ?? [];
            this.detaljStreak   = res.data?.streak         ?? 0;
            this.detaljBastaDag = res.data?.basta_dag      ?? null;
            this.detaljSammstaDag = res.data?.sammsta_dag  ?? null;
            if (this.detaljChartTimer !== null) { clearTimeout(this.detaljChartTimer); }
            this.detaljChartTimer = setTimeout(() => {
              if (!this.destroy$.closed) this.buildDetaljChart();
            }, 0);
          } else {
            this.errorDetalj = true;
          }
        },
        error: () => {
          this.loadingDetalj = false;
          this.errorDetalj   = true;
        },
      });
  }

  loadUtveckling(id: number): void {
    this.loadingUtveckling = true;
    this.errorUtveckling   = false;

    this.svc.getUtveckling(id)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingUtveckling = false;
          if (res?.success) {
            this.utvecklingData  = res.data?.veckor ?? [];
            this.utvecklingTrend = res.data?.trend  ?? 'neutral';
            if (this.utvecklingChartTimer !== null) { clearTimeout(this.utvecklingChartTimer); }
            this.utvecklingChartTimer = setTimeout(() => {
              if (!this.destroy$.closed) this.buildUtvecklingChart();
            }, 0);
          } else {
            this.errorUtveckling = true;
          }
        },
        error: () => {
          this.loadingUtveckling = false;
          this.errorUtveckling   = true;
        },
      });
  }

  // ================================================================
  // Chart.js — Scatter plot
  // ================================================================

  private buildScatterChart(): void {
    try { this.scatterChart?.destroy(); } catch (_) {}
    this.scatterChart = null;

    const canvas = document.getElementById('operatorsScatterChart') as HTMLCanvasElement | null;
    if (!canvas || !this.scatterData.length) return;

    // Max IBC för skalning av punktstorlek
    const maxIbc = Math.max(...this.scatterData.map(o => o.antal_ibc), 1);

    // Gruppera per skift
    const skiftGrupper: Record<string, OperatorScatterPunkt[]> = {
      dag:   [],
      kvall: [],
      natt:  [],
    };
    for (const op of this.scatterData) {
      if (skiftGrupper[op.skift_typ]) {
        skiftGrupper[op.skift_typ].push(op);
      } else {
        skiftGrupper['dag'].push(op);
      }
    }

    const skiftLabels: Record<string, string> = {
      dag:   'Dagskift',
      kvall: 'Kvällsskift',
      natt:  'Nattskift',
    };

    const datasets = Object.entries(skiftGrupper)
      .filter(([, ops]) => ops.length > 0)
      .map(([skift, ops]) => {
        const farg = this.SKIFT_FARG[skift] ?? '#63b3ed';
        return {
          label: skiftLabels[skift] ?? skift,
          data: ops.map(op => ({
            x: op.medel_cykeltid,
            y: Math.max(0, 100 - op.kassationsgrad),
            operatorId:   op.operator_id,
            operatorNamn: op.operator_namn,
            antalIbc:     op.antal_ibc,
            kassation:    op.kassationsgrad,
            oee:          op.oee,
            cykeltid:     op.medel_cykeltid,
          })),
          backgroundColor: farg + 'cc',
          borderColor:     farg,
          borderWidth: 1,
          pointRadius: ops.map(op => Math.max(6, Math.min(20, (op.antal_ibc / maxIbc) * 20 + 4))),
          pointHoverRadius: ops.map(op => Math.max(8, Math.min(24, (op.antal_ibc / maxIbc) * 20 + 6))),
        };
      });

    const medCykeltid = this.medelCykeltid;
    const medKval     = this.medelKvalitet;

    if (this.scatterChart) { (this.scatterChart as any).destroy(); }
    this.scatterChart = new Chart(canvas, {
      type: 'scatter',
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const d = ctx.raw;
                return [
                  ` ${d.operatorNamn}`,
                  ` IBC: ${d.antalIbc}`,
                  ` Cykeltid: ${d.cykeltid > 0 ? d.cykeltid.toFixed(0) + ' s' : '—'}`,
                  ` Kassation: ${d.kassation.toFixed(1)} %`,
                  ` OEE: ${d.oee.toFixed(1)} %`,
                ];
              },
            },
          },
          // Referenslinjer via annotation (plugin)
        },
        scales: {
          x: {
            ticks:  { color: '#a0aec0' },
            grid:   { color: '#374151' },
            title:  { display: true, text: 'Genomsnittlig cykeltid (sekunder) — lägre = snabbare', color: '#a0aec0' },
            reverse: false,
          },
          y: {
            ticks:  { color: '#a0aec0', callback: (v: any) => v + ' %' },
            grid:   { color: '#374151' },
            title:  { display: true, text: 'Kvalitet (100% - kassation) — högre = bättre', color: '#a0aec0' },
            min: 0,
            max: 100,
          },
        },
      },
      plugins: [
        {
          id: 'referensLinjer',
          afterDraw: (chart: Chart) => {
            const ctx2 = chart.ctx;
            const xScale = (chart as any).scales['x'];
            const yScale = (chart as any).scales['y'];

            if (!xScale || !yScale || medCykeltid <= 0) return;

            const xMed = xScale.getPixelForValue(medCykeltid);
            const yMed = yScale.getPixelForValue(medKval);

            ctx2.save();
            ctx2.strokeStyle = 'rgba(160, 174, 192, 0.4)';
            ctx2.lineWidth   = 1.5;
            ctx2.setLineDash([6, 4]);

            // Vertikal referenslinje (cykeltid)
            if (xMed >= xScale.left && xMed <= xScale.right) {
              ctx2.beginPath();
              ctx2.moveTo(xMed, yScale.top);
              ctx2.lineTo(xMed, yScale.bottom);
              ctx2.stroke();
            }

            // Horisontell referenslinje (kvalitet)
            if (yMed >= yScale.top && yMed <= yScale.bottom) {
              ctx2.beginPath();
              ctx2.moveTo(xScale.left, yMed);
              ctx2.lineTo(xScale.right, yMed);
              ctx2.stroke();
            }

            ctx2.setLineDash([]);

            // Kvadrant-labels
            ctx2.font      = 'bold 11px sans-serif';
            ctx2.fillStyle = 'rgba(160, 174, 192, 0.5)';
            const pad = 8;

            if (xMed > xScale.left + 60 && yMed > yScale.top + 20) {
              ctx2.textAlign = 'left';
              ctx2.fillText('Snabb & Noggrann', xScale.left + pad, yScale.top + 20);
            }
            if (xMed < xScale.right - 60 && yMed > yScale.top + 20) {
              ctx2.textAlign = 'right';
              ctx2.fillText('Langsamm & Noggrann', xScale.right - pad, yScale.top + 20);
            }
            if (xMed > xScale.left + 60 && yMed < yScale.bottom - 10) {
              ctx2.textAlign = 'left';
              ctx2.fillText('Snabb & Slarvig', xScale.left + pad, yScale.bottom - 10);
            }
            if (xMed < xScale.right - 60 && yMed < yScale.bottom - 10) {
              ctx2.textAlign = 'right';
              ctx2.fillText('Behöver stöd', xScale.right - pad, yScale.bottom - 10);
            }

            ctx2.restore();
          },
        },
      ],
    });
  }

  // ================================================================
  // Chart.js — Detalj-linjediagram
  // ================================================================

  private buildDetaljChart(): void {
    try { this.detaljChart?.destroy(); } catch (_) {}
    this.detaljChart = null;

    const canvas = document.getElementById('operatorDetaljChart') as HTMLCanvasElement | null;
    if (!canvas || !this.detaljDaglig.length) return;

    const labels    = this.detaljDaglig.map(d => d.datum.substring(5));
    const ibcOk     = this.detaljDaglig.map(d => d.ibc_ok);
    const kassation = this.detaljDaglig.map(d => d.kassationsgrad);

    if (this.detaljChart) { (this.detaljChart as any).destroy(); }
    this.detaljChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC OK',
            data: ibcOk,
            backgroundColor: '#48bb7866',
            borderColor: '#48bb78',
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
            pointRadius: 3,
            yAxisID: 'y2',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
        },
        scales: {
          x:  { ticks: { color: '#a0aec0', maxTicksLimit: 15 }, grid: { color: '#374151' } },
          y:  {
            position: 'left',
            ticks: { color: '#48bb78' },
            grid:  { color: '#374151' },
            title: { display: true, text: 'IBC OK', color: '#48bb78' },
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
  // Chart.js — Utveckling 12 veckor
  // ================================================================

  private buildUtvecklingChart(): void {
    try { this.utvecklingChart?.destroy(); } catch (_) {}
    this.utvecklingChart = null;

    const canvas = document.getElementById('operatorUtvecklingChart') as HTMLCanvasElement | null;
    if (!canvas || !this.utvecklingData.length) return;

    const labels = this.utvecklingData.map(v => v.label);
    const ibc    = this.utvecklingData.map(v => v.har_data ? v.total_ibc : null);
    const kass   = this.utvecklingData.map(v => v.har_data ? v.kassationsgrad : null);

    if (this.utvecklingChart) { (this.utvecklingChart as any).destroy(); }
    this.utvecklingChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC totalt',
            data: ibc,
            borderColor: '#63b3ed',
            backgroundColor: '#63b3ed22',
            borderWidth: 2,
            pointRadius: 4,
            spanGaps: true,
            yAxisID: 'y',
          },
          {
            label: 'Kassation %',
            data: kass,
            borderColor: '#fc8181',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 4,
            spanGaps: true,
            borderDash: [4, 4],
            yAxisID: 'y2',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
        },
        scales: {
          x:  { ticks: { color: '#a0aec0' }, grid: { color: '#374151' } },
          y:  {
            position: 'left',
            ticks: { color: '#63b3ed' },
            grid:  { color: '#374151' },
            title: { display: true, text: 'IBC totalt', color: '#63b3ed' },
            beginAtZero: true,
          },
          y2: {
            position: 'right',
            ticks: { color: '#fc8181', callback: (v: any) => v + ' %' },
            grid:  { drawOnChartArea: false },
            title: { display: true, text: 'Kassation %', color: '#fc8181' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ================================================================
  // Template helpers
  // ================================================================

  formatCykeltid(sek: number): string {
    if (sek <= 0) return '—';
    if (sek < 60) return sek.toFixed(0) + ' s';
    const min = Math.floor(sek / 60);
    const s   = Math.round(sek % 60);
    return `${min}m ${s}s`;
  }

  skiftFarg(skift: string): string {
    return this.SKIFT_FARG[skift] ?? '#a0aec0';
  }

  trendIkon(trend: string): string {
    if (trend === 'forbattras') return '↑';
    if (trend === 'forsamras')  return '↓';
    return '→';
  }

  trendFarg(trend: string): string {
    if (trend === 'forbattras') return '#48bb78';
    if (trend === 'forsamras')  return '#fc8181';
    return '#a0aec0';
  }

  teamSkiftFarg(skift: string): string {
    return this.SKIFT_FARG[skift] ?? '#63b3ed';
  }

  sortIkon(kol: SortKolumn): string {
    if (this.rankingSortBy !== kol) return '↕';
    return this.rankingSortAsc ? '↑' : '↓';
  }
}
