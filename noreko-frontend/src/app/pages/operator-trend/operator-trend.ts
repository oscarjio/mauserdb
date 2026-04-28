import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';
import { localToday } from '../../utils/date-utils';

Chart.register(...registerables);

interface Operator {
  id: number;
  name: string;
  number: number;
}

interface TrendRow {
  year: number;
  week_num: number;
  vecka_label: string;
  ibc_per_h: number | null;
  kvalitet_pct: number | null;
  antal_skift: number;
  team_ibc_per_h: number | null;
  vs_lag: number | null;
}

interface TrendResponse {
  success: boolean;
  op_id: number;
  op_name: string;
  op_number: number;
  weeks: number;
  trend: TrendRow[];
  trend_arrow: 'up' | 'down' | 'flat' | null;
  trend_pct: number | null;
  error?: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-trend',
  imports: [CommonModule, FormsModule],
  templateUrl: './operator-trend.html',
  styleUrl: './operator-trend.css'
})
export class OperatorTrendPage implements OnInit, OnDestroy {
  Math = Math;

  operators: Operator[] = [];
  selectedOpId: number | null = null;
  selectedWeeks: 8 | 16 | 26 | 52 = 8;
  weekOptions: (8 | 16 | 26 | 52)[] = [8, 16, 26, 52];

  loading = false;
  loadingOps = false;
  errorMsg = '';

  trendData: TrendRow[] = [];
  opName = '';
  trendArrow: 'up' | 'down' | 'flat' | null = null;
  trendPct: number | null = null;

  private chart: Chart | null = null;
  private chartTimer: any = null;
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;
  /** Versionsnummer — förhindrar att gamla HTTP-svar skriver över nya vid snabb navigering */
  private loadVersion = 0;

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
    this.loadOperators();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) {
      clearTimeout(this.chartTimer);
      this.chartTimer = null;
    }
    try { this.chart?.destroy(); } catch (e) {}
    this.chart = null;
  }

  loadOperators(): void {
    this.loadingOps = true;
    this.http
      .get<any>(`${this.apiBase}?action=rebotling&run=operator-list-trend`, {
        withCredentials: true
      })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loadingOps = false;
        if (res?.success) {
          this.operators = res.operators || [];
          if (this.operators.length > 0 && !this.selectedOpId) {
            this.selectedOpId = this.operators[0].id;
            this.loadTrend();
          }
        }
      });
  }

  setWeeks(w: 8 | 16 | 26 | 52): void {
    this.selectedWeeks = w;
    if (this.selectedOpId) {
      this.loadTrend();
    }
  }

  onOperatorChange(): void {
    if (this.selectedOpId) {
      this.loadTrend();
    }
  }

  loadTrend(): void {
    if (!this.selectedOpId) return;
    const version = ++this.loadVersion;
    this.loading = true;
    this.errorMsg = '';

    const timeoutMs = this.selectedWeeks >= 52 ? 20000 : 10000;

    this.http
      .get<TrendResponse>(
        `${this.apiBase}?action=rebotling&run=operator-weekly-trend` +
        `&op_id=${this.selectedOpId}&weeks=${this.selectedWeeks}`,
        { withCredentials: true }
      )
      .pipe(
        timeout(timeoutMs),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        // Ignorera svar från inaktuell förfrågan (användaren bytte operatör/period)
        if (version !== this.loadVersion) return;
        this.loading = false;
        if (res?.success) {
          this.trendData  = res.trend || [];
          this.opName     = res.op_name || '';
          this.trendArrow = res.trend_arrow ?? null;
          this.trendPct   = res.trend_pct ?? null;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildChart(); }, 100);
        } else {
          this.errorMsg = res?.error || 'Kunde inte hämta trenddata.';
          this.trendData = [];
        }
      });
  }

  // ---- Linjär regression & prognos ----

  linearRegression(data: number[]): { slope: number; intercept: number } {
    const n = data.length;
    if (n < 2) return { slope: 0, intercept: data[0] ?? 0 };
    const xs = Array.from({ length: n }, (_, i) => i);
    const sumX  = xs.reduce((a, b) => a + b, 0);
    const sumY  = data.reduce((a, b) => a + b, 0);
    const sumXY = xs.reduce((acc, x, i) => acc + x * data[i], 0);
    const sumXX = xs.reduce((acc, x) => acc + x * x, 0);
    const denom = n * sumXX - sumX * sumX;
    if (denom === 0) return { slope: 0, intercept: sumY / n };
    const slope     = (n * sumXY - sumX * sumY) / denom;
    const intercept = (sumY - slope * sumX) / n;
    return { slope, intercept };
  }

  private buildForecastLabels(baseLabels: string[], extraWeeks: number = 3): string[] {
    const extra: string[] = [];
    for (let i = 1; i <= extraWeeks; i++) {
      extra.push(`+${i}v`);
    }
    return [...baseLabels, ...extra];
  }

  private buildChart(): void {
    try { this.chart?.destroy(); } catch (e) {}
    this.chart = null;

    const canvas = document.getElementById('trendMainChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.trendData.length === 0) return;

    const EXTRA_WEEKS = 3;
    const baseLabels = this.trendData.map(r => r.vecka_label);
    const labels     = this.buildForecastLabels(baseLabels, EXTRA_WEEKS);

    const ibcH     = this.trendData.map(r => r.ibc_per_h);
    const teamIbcH = this.trendData.map(r => r.team_ibc_per_h);

    // Filtrera bort null för regression
    const validIbc: number[] = ibcH.filter((v): v is number => v !== null);
    let forecastPadded: (number | null)[] | null = null;

    if (validIbc.length >= 2) {
      const reg = this.linearRegression(validIbc);
      const n   = validIbc.length;
      // Hitta index för sista giltiga ibc_per_h
      const lastValidIdx = [...ibcH].reverse().findIndex(v => v !== null);
      const lastIdx = lastValidIdx >= 0 ? ibcH.length - 1 - lastValidIdx : ibcH.length - 1;

      forecastPadded = new Array(this.trendData.length + EXTRA_WEEKS).fill(null) as (number | null)[];
      // Överlappspunkt vid sista faktiska värde
      forecastPadded[lastIdx] = ibcH[lastIdx];
      // Prognosvärden
      for (let i = 0; i < EXTRA_WEEKS; i++) {
        forecastPadded[this.trendData.length + i] = Math.max(0, reg.intercept + reg.slope * (n + i));
      }
    }

    // Utöka faktiska dataserier med null för prognosveckorna
    const ibcHExtended:     (number | null)[] = [...ibcH,     ...new Array(EXTRA_WEEKS).fill(null)];
    const teamIbcHExtended: (number | null)[] = [...teamIbcH, ...new Array(EXTRA_WEEKS).fill(null)];

    const datasets: any[] = [
      {
        label: this.opName,
        data: ibcHExtended,
        borderColor: '#4299e1',
        backgroundColor: 'rgba(66,153,225,0.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 5,
        pointBackgroundColor: '#4299e1',
        borderWidth: 2.5,
        spanGaps: true
      },
      {
        label: 'Lagsnitt',
        data: teamIbcHExtended,
        borderColor: '#ecc94b',
        backgroundColor: 'rgba(236,201,75,0.06)',
        fill: false,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: '#ecc94b',
        borderWidth: 2,
        borderDash: [6, 4],
        spanGaps: true
      }
    ];

    if (forecastPadded) {
      datasets.push({
        label: 'Prognos',
        data: forecastPadded,
        borderColor: '#8fa3b8',
        backgroundColor: 'transparent',
        fill: false,
        tension: 0.2,
        pointRadius: 4,
        pointBackgroundColor: '#8fa3b8',
        borderWidth: 2,
        borderDash: [4, 4],
        spanGaps: true,
        pointStyle: 'circle'
      });
    }

    if (this.chart) { (this.chart as any).destroy(); }
    this.chart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: {
              color: '#a0aec0',
              font: { size: 12 },
              usePointStyle: true
            }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                const suffix = ctx.dataset.label === 'Prognos' ? ' (prognos)' : '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)} IBC/h${suffix}`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.06)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC / timme',
              color: '#8fa3b8',
              font: { size: 11 }
            }
          }
        }
      }
    });
  }

  // ---- CSV-export ----

  exportTrendCSV(): void {
    if (!this.trendData || this.trendData.length === 0) return;
    const operatorName = this.opName || this.getSelectedOpName() || 'Operatör';
    const headers = ['Vecka', `${operatorName} IBC/h`, 'Lagsnitt IBC/h', 'Diff', 'Kvalitet %', 'Skift'];
    const rows = this.trendData.map((row: TrendRow) => [
      row.vecka_label || '',
      row.ibc_per_h !== null ? row.ibc_per_h.toFixed(2) : '',
      row.team_ibc_per_h !== null ? row.team_ibc_per_h.toFixed(2) : '',
      row.ibc_per_h !== null && row.team_ibc_per_h !== null
        ? (row.ibc_per_h - row.team_ibc_per_h).toFixed(2)
        : '',
      row.kvalitet_pct !== null ? row.kvalitet_pct.toFixed(1) : '',
      row.antal_skift.toString()
    ]);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `trend-${operatorName.replace(/\s+/g, '-')}-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ---- KPI-hjälpmetoder ----

  getRecentAvg(): number | null {
    const recent = this.trendData
      .slice(-4)
      .map(r => r.ibc_per_h)
      .filter((v): v is number => v !== null);
    if (recent.length === 0) return null;
    return recent.reduce((a, b) => a + b, 0) / recent.length;
  }

  getVsTeamPct(): number | null {
    const recentOp = this.getRecentAvg();
    const recentTeam = this.trendData
      .slice(-4)
      .map(r => r.team_ibc_per_h)
      .filter((v): v is number => v !== null);
    if (recentOp === null || recentTeam.length === 0) return null;
    const teamAvg = recentTeam.reduce((a, b) => a + b, 0) / recentTeam.length;
    if (teamAvg === 0) return null;
    return ((recentOp - teamAvg) / teamAvg) * 100;
  }

  getVsTeamClass(): string {
    const pct = this.getVsTeamPct();
    if (pct === null) return 'kpi-card-neutral';
    if (pct > 0) return 'kpi-card-good';
    if (pct < 0) return 'kpi-card-bad';
    return 'kpi-card-neutral';
  }

  getVsTeamLabel(): string {
    const pct = this.getVsTeamPct();
    if (pct === null) return '–';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  getTrendKpiLabel(): string {
    switch (this.trendArrow) {
      case 'up':   return 'Uppåt ↑';
      case 'down': return 'Nedåt ↓';
      case 'flat': return 'Stabil →';
      default:     return '–';
    }
  }

  getTrendKpiClass(): string {
    switch (this.trendArrow) {
      case 'up':   return 'kpi-card-good';
      case 'down': return 'kpi-card-bad';
      case 'flat': return 'kpi-card-neutral';
      default:     return 'kpi-card-neutral';
    }
  }

  // ---- Hjälpmetoder ----

  getVsClass(vs: number | null): string {
    if (vs === null) return 'vs-neutral';
    if (vs > 0) return 'vs-positive';
    if (vs < 0) return 'vs-negative';
    return 'vs-neutral';
  }

  getIbcClass(val: number | null, teamVal: number | null): string {
    if (val === null) return 'cell-neutral';
    if (teamVal === null) return 'cell-neutral';
    if (val > teamVal) return 'cell-good';
    if (val < teamVal) return 'cell-bad';
    return 'cell-neutral';
  }

  getTrendArrowIcon(): string {
    switch (this.trendArrow) {
      case 'up':   return '↑';
      case 'down': return '↓';
      case 'flat': return '→';
      default:     return '';
    }
  }

  getTrendArrowClass(): string {
    switch (this.trendArrow) {
      case 'up':   return 'trend-up';
      case 'down': return 'trend-down';
      case 'flat': return 'trend-flat';
      default:     return '';
    }
  }

  getTrendLabel(): string {
    if (this.trendPct === null || this.trendArrow === null) return '';
    const abs = Math.abs(this.trendPct);
    if (this.trendArrow === 'up')   return `↑ ${abs}% senaste 4 veckorna`;
    if (this.trendArrow === 'down') return `↓ ${abs}% senaste 4 veckorna`;
    return 'Stabilt senaste 4 veckorna';
  }

  getSelectedOpName(): string {
    if (!this.selectedOpId) return '';
    return this.operators.find(o => o.id === this.selectedOpId)?.name || '';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
