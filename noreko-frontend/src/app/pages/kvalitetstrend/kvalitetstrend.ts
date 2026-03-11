import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  KvalitetstrendService,
  OverviewData,
  OperatorsData,
  OperatorRow,
  OperatorDetailData,
} from '../../services/kvalitetstrend.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kvalitetstrend',
  templateUrl: './kvalitetstrend.html',
  styleUrls: ['./kvalitetstrend.css'],
  imports: [CommonModule, FormsModule],
})
export class KvalitetstrendComponent implements OnInit, OnDestroy {
  Math = Math;

  // ---- Perioder ----
  selectedPeriod: 4 | 12 | 26 = 12;
  perioder: { val: 4 | 12 | 26; label: string }[] = [
    { val: 4,  label: '4 veckor'  },
    { val: 12, label: '12 veckor' },
    { val: 26, label: '26 veckor' },
  ];

  // ---- Toggle vecka/månad ----
  visningsLage: 'vecka' | 'manad' = 'vecka';

  // ---- Laddningsstate ----
  overviewLoading  = false;
  overviewLoaded   = false;
  overviewError    = false;
  operatorsLoading = false;
  operatorsLoaded  = false;
  operatorsError   = false;
  detailLoading    = false;

  // ---- Data ----
  overviewData: OverviewData | null = null;
  operatorsData: OperatorsData | null = null;
  detailData: OperatorDetailData | null = null;
  selectedOpId: number | null = null;

  // ---- Filter ----
  filterText = '';
  visaBaraLarm = false;

  lastRefreshed: Date | null = null;

  private destroy$          = new Subject<void>();
  private trendChart: Chart | null = null;
  private detailChart: Chart | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private detailChartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: KvalitetstrendService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.detailChartTimer) { clearTimeout(this.detailChartTimer); this.detailChartTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    try { this.detailChart?.destroy(); } catch (_) {}
    this.trendChart  = null;
    this.detailChart = null;
  }

  // ================================================================
  // DATA LOADING
  // ================================================================

  setPeriod(p: 4 | 12 | 26): void {
    if (this.selectedPeriod === p) return;
    this.selectedPeriod = p;
    this.overviewLoaded  = false;
    this.operatorsLoaded = false;
    this.overviewData    = null;
    this.operatorsData   = null;
    this.detailData      = null;
    this.selectedOpId    = null;
    this.loadAll();
  }

  setVisningsLage(lage: 'vecka' | 'manad'): void {
    this.visningsLage = lage;
    if (this.operatorsData) {
      if (this.chartTimer) clearTimeout(this.chartTimer);
      this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 100);
    }
  }

  private loadAll(): void {
    this.loadOverview();
    this.loadOperators();
  }

  private loadOverview(): void {
    if (this.overviewLoading) return;
    this.overviewLoading = true;
    this.overviewError   = false;

    this.service.getOverview(this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.overviewLoading = false;
        this.overviewLoaded  = true;
        if (res?.success) {
          this.overviewData  = res.data;
          this.overviewError = false;
        } else {
          this.overviewData  = null;
          this.overviewError = true;
        }
        this.lastRefreshed = new Date();
      });
  }

  private loadOperators(): void {
    if (this.operatorsLoading) return;
    this.operatorsLoading = true;
    this.operatorsError   = false;

    this.service.getOperators(this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.operatorsLoading = false;
        this.operatorsLoaded  = true;
        if (res?.success) {
          this.operatorsData  = res.data;
          this.operatorsError = false;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 150);
        } else {
          this.operatorsData  = null;
          this.operatorsError = true;
        }
      });
  }

  loadOperatorDetail(opId: number): void {
    if (this.selectedOpId === opId && this.detailData) return;
    this.selectedOpId = opId;
    this.detailData   = null;
    this.detailLoading = true;

    this.service.getOperatorDetail(opId, this.selectedPeriod)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.detailLoading = false;
        if (res?.success) {
          this.detailData = res.data;
          if (this.detailChartTimer) clearTimeout(this.detailChartTimer);
          this.detailChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderDetailChart(); }, 150);
        }
      });
  }

  closeDetail(): void {
    this.selectedOpId = null;
    this.detailData   = null;
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;
  }

  // ================================================================
  // FILTRADE OPERATÖRER
  // ================================================================

  get filtradeOperatorer(): OperatorRow[] {
    if (!this.operatorsData) return [];
    let list = this.operatorsData.operatorer;
    if (this.visaBaraLarm) {
      list = list.filter(o => o.utbildningslarm);
    }
    if (this.filterText.trim()) {
      const q = this.filterText.trim().toLowerCase();
      list = list.filter(o => o.namn.toLowerCase().includes(q));
    }
    return list;
  }

  // ================================================================
  // CHART: Trendgraf — alla operatörer + teamsnitt
  // ================================================================

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('kvalitetTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.operatorsData) return;

    const { operatorer, veckonycklar, team_snitt_per_vecka } = this.operatorsData;

    // Bygg labels baserat på visningsläge
    let labels: string[];

    if (this.visningsLage === 'manad') {
      // Aggregera till månader
      const manadMap: Record<string, number[]> = {};
      veckonycklar.forEach(key => {
        const [ar, wPart] = key.split('-W');
        const weekNum = parseInt(wPart, 10);
        // Approximera månad från vecka
        const d = this.veckaToDate(parseInt(ar, 10), weekNum);
        const manadKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        if (!manadMap[manadKey]) manadMap[manadKey] = [];
        manadMap[manadKey].push(veckonycklar.indexOf(key));
      });
      labels   = Object.keys(manadMap).sort().map(k => {
        const [y, m] = k.split('-');
        const manadNamn = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
        return `${manadNamn[parseInt(m, 10) - 1]} ${y}`;
      });
    } else {
      labels   = veckonycklar.map(k => {
        const [, wPart] = k.split('-W');
        return 'V' + parseInt(wPart, 10);
      });
    }

    const colors = [
      '#4299e1','#48bb78','#ecc94b','#ed8936','#e53e3e',
      '#9f7aea','#38b2ac','#f687b3','#68d391','#fc8181',
    ];

    // Välj max 8 operatörer med mest IBC-data (för att hålla grafen läsbar)
    const sortedByIbc = [...operatorer].sort((a, b) => b.ibc_totalt - a.ibc_totalt).slice(0, 8);

    const datasets: any[] = sortedByIbc.map((op, idx) => {
      const color = colors[idx % colors.length];
      let data: (number | null)[];

      if (this.visningsLage === 'manad') {
        data = this.aggregeraTillManader(op, veckonycklar, this.operatorsData!.team_snitt_per_vecka);
      } else {
        data = veckonycklar.map(key => {
          // Reconstruct from sparkdata proportionally isn't available here.
          // We need to refetch detail data per operatör — not feasible here.
          // Sparkdata är bara de senaste 6 veckorna. Returnera null för äldre veckor.
          // Faktum: operatorsData har bara sparkdata (6 veckor), inte full tidsserie.
          // Vi kan visa sparkdata för de senaste 6 veckorna, resten null.
          const sparkStart = veckonycklar.length - 6;
          const sparkIdx   = veckonycklar.indexOf(key) - sparkStart;
          if (sparkIdx >= 0 && sparkIdx < op.sparkdata.length) {
            return op.sparkdata[sparkIdx];
          }
          return null;
        });
      }

      return {
        label: op.namn,
        data,
        borderColor: color,
        backgroundColor: color + '20',
        fill: false,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: color,
        borderWidth: 2,
        spanGaps: true,
      };
    });

    // Teamsnitt — streckad linje
    const teamData = this.visningsLage === 'vecka'
      ? veckonycklar.map(k => team_snitt_per_vecka[k] ?? null)
      : this.aggregeraTeamTillManader(veckonycklar, team_snitt_per_vecka);

    datasets.push({
      label: 'Teamsnitt',
      data: teamData,
      borderColor: '#718096',
      backgroundColor: 'transparent',
      fill: false,
      tension: 0.3,
      pointRadius: 3,
      pointBackgroundColor: '#718096',
      borderWidth: 2,
      borderDash: [6, 4],
      spanGaps: true,
    });

    // Larmgräns — horisontell linje vid 85%
    datasets.push({
      label: 'Utbildningsgräns (85%)',
      data: labels.map(() => 85),
      borderColor: '#e53e3e',
      backgroundColor: 'transparent',
      fill: false,
      pointRadius: 0,
      borderWidth: 1.5,
      borderDash: [3, 3],
    });

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: false,
            min: 60,
            max: 100,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => `${val}%`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Kvalitet %',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  private veckaToDate(ar: number, vecka: number): Date {
    const d = new Date(ar, 0, 1 + (vecka - 1) * 7);
    const dayOfWeek = d.getDay();
    if (dayOfWeek <= 4) {
      d.setDate(d.getDate() - d.getDay() + 1);
    } else {
      d.setDate(d.getDate() + 8 - d.getDay());
    }
    return d;
  }

  private aggregeraTillManader(_op: OperatorRow, _veckonycklar: string[], _team: any): (number | null)[] {
    // Sparkdata är senaste 6 veckors kval. Returnera null för månadsvy.
    return [];
  }

  private aggregeraTeamTillManader(veckonycklar: string[], teamSnitt: Record<string, number | null>): (number | null)[] {
    const manadMap: Record<string, number[]> = {};
    veckonycklar.forEach(key => {
      const [ar, wPart] = key.split('-W');
      const d = this.veckaToDate(parseInt(ar, 10), parseInt(wPart, 10));
      const mKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      const v = teamSnitt[key];
      if (v !== null && v !== undefined) {
        if (!manadMap[mKey]) manadMap[mKey] = [];
        manadMap[mKey].push(v);
      }
    });
    return Object.keys(manadMap).sort().map(k => {
      const vals = manadMap[k];
      return vals.length > 0 ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 10) / 10 : null;
    });
  }

  // ================================================================
  // CHART: Detaljvy — en operatör
  // ================================================================

  private renderDetailChart(): void {
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;

    const canvas = document.getElementById('kvalitetDetailChart') as HTMLCanvasElement;
    if (!canvas || !this.detailData) return;

    const { tidslinje } = this.detailData;
    const labels  = tidslinje.map(r => r.vecka_label);
    const opKval  = tidslinje.map(r => r.kvalitet_pct);
    const teamKval = tidslinje.map(r => r.team_kvalitet);

    this.detailChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: this.detailData.op_namn,
            data: opKval,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 5,
            pointBackgroundColor: '#4299e1',
            borderWidth: 2.5,
            spanGaps: true,
          },
          {
            label: 'Teamsnitt',
            data: teamKval,
            borderColor: '#ecc94b',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.3,
            pointRadius: 3,
            pointBackgroundColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [6, 4],
            spanGaps: true,
          },
          {
            label: 'Utbildningsgräns (85%)',
            data: labels.map(() => 85),
            borderColor: '#e53e3e',
            backgroundColor: 'transparent',
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            borderDash: [3, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: false,
            min: 60,
            max: 100,
            ticks: {
              color: '#a0aec0',
              callback: (val: any) => `${val}%`,
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Kvalitet %',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getKvalitetKlass(pct: number | null): string {
    if (pct === null) return 'text-muted';
    if (pct >= 95) return 'text-success';
    if (pct >= 85) return 'text-warning';
    return 'text-danger';
  }

  getPilKlass(pil: string): string {
    if (pil === 'up')   return 'trend-up';
    if (pil === 'down') return 'trend-down';
    return 'trend-flat';
  }

  getPilSymbol(pil: string): string {
    if (pil === 'up')   return '↑';
    if (pil === 'down') return '↓';
    return '→';
  }

  getTrendBadgeKlass(status: string): string {
    if (status === 'förbättras') return 'badge-forbattras';
    if (status === 'försämras')  return 'badge-forsamras';
    return 'badge-stabil';
  }

  getTrendBadgeLabel(status: string): string {
    if (status === 'förbättras') return 'Förbättras';
    if (status === 'försämras')  return 'Försämras';
    return 'Stabil';
  }

  formatForandring(pct: number | null, _pil: string): string {
    if (pct === null) return '–';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  getVsTeamKlass(vs: number | null): string {
    if (vs === null) return 'text-muted';
    if (vs > 0) return 'text-success';
    if (vs < 0) return 'text-danger';
    return 'text-muted';
  }

  getVsTeamText(vs: number | null): string {
    if (vs === null) return '–';
    const sign = vs >= 0 ? '+' : '';
    return `${sign}${vs.toFixed(1)}%`;
  }

  getPeriodLabel(): string {
    if (this.selectedPeriod === 4)  return 'Senaste 4 veckorna';
    if (this.selectedPeriod === 12) return 'Senaste 12 veckorna';
    return 'Senaste 26 veckorna';
  }

  getLarmOrsak(op: OperatorRow): string {
    if (op.lag_kvalitet && op.konsekvent_nedgang) return 'Låg kvalitet + nedgångstrend';
    if (op.lag_kvalitet)      return 'Kvalitet under 85%';
    if (op.konsekvent_nedgang) return 'Nedgångstrend (3+ veckor)';
    return '';
  }

  get antalLarm(): number {
    return this.operatorsData?.operatorer.filter(o => o.utbildningslarm).length ?? 0;
  }

  trackByNummer(_: number, op: OperatorRow): number {
    return op.nummer;
  }
}
