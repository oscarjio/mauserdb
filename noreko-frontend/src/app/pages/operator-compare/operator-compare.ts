import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

export interface PosData {
  ibc_per_h: number;
  team_avg: number;
  antal_skift: number;
  vs_avg_pct: number;
}

export interface MonthData {
  month: string;
  ibc_per_h: number;
  shifts: number;
}

export interface OpStats {
  number: number;
  name: string;
  ibc_per_h: number;
  vs_team_pct: number;
  tier: string;
  total_shifts: number;
  active_days: number;
  consistency: number;
  trend_direction: string;
  trend_slope: number;
  best_shift: number;
  worst_shift: number;
  kassation_pct: number | null;
  per_position: { op1: PosData | null; op2: PosData | null; op3: PosData | null };
  weekly_vals: number[];
  radar: { fart: number; konsistens: number; trend: number; narvaro: number };
  monthly: MonthData[];
}

interface OperatorItem {
  number: string;
  name: string;
}

interface ApiResponse {
  success: boolean;
  operators: OperatorItem[];
  op_a: OpStats | null;
  op_b: OpStats | null;
  team_avg_ibc_h: number;
  team_avg_per_pos: { op1: number; op2: number; op3: number };
  days: number;
  from: string;
  to: string;
}

export interface CompareData {
  a: OpStats;
  b: OpStats;
  teamAvg: number;
  teamAvgPos: { op1: number; op2: number; op3: number };
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-compare',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-compare.html',
  styleUrl: './operator-compare.css',
})
export class OperatorComparePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private radarChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorItem[] = [];
  selectedA = 0;
  selectedB = 0;
  days = 90;

  compareData: CompareData | null = null;

  readonly daysOptions = [30, 60, 90, 180];
  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };
  readonly posKeys: Array<'op1' | 'op2' | 'op3'> = ['op1', 'op2', 'op3'];

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadOperators();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    if (this.radarChart) { this.radarChart.destroy(); this.radarChart = null; }
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
  }

  loadOperators(): void {
    this.http
      .get<ApiResponse>(`${environment.apiUrl}?action=rebotling&run=operator-compare`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) this.operators = res.operators;
      });
  }

  get canCompare(): boolean {
    return +this.selectedA > 0 && +this.selectedB > 0 && +this.selectedA !== +this.selectedB;
  }

  get availableForB(): OperatorItem[] {
    return this.operators.filter(o => +o.number !== +this.selectedA);
  }

  get availableForA(): OperatorItem[] {
    return this.operators.filter(o => +o.number !== +this.selectedB);
  }

  compare(): void {
    if (this.isFetching || !this.canCompare) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.compareData = null;
    this.destroyCharts();

    const url = `${environment.apiUrl}?action=rebotling&run=operator-compare&op_a=${this.selectedA}&op_b=${this.selectedB}&days=${this.days}`;
    this.http
      .get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta jämförelsedata.'; return of(null); }),
        finalize(() => { this.loading = false; this.isFetching = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel vid jämförelse.'; return; }
        if (!res.op_a || !res.op_b) { this.error = 'Ingen data för en eller båda operatörer under perioden.'; return; }
        this.compareData = {
          a: res.op_a,
          b: res.op_b,
          teamAvg: res.team_avg_ibc_h,
          teamAvgPos: res.team_avg_per_pos,
          from: res.from,
          to: res.to,
        };
        setTimeout(() => { this.buildRadarChart(); this.buildTrendChart(); }, 80);
      });
  }

  private buildRadarChart(): void {
    const canvas = document.getElementById('radarChart') as HTMLCanvasElement | null;
    if (!canvas || !this.compareData) return;
    if (this.radarChart) this.radarChart.destroy();
    const { a, b } = this.compareData;
    this.radarChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Fart', 'Konsistens', 'Trend', 'Närvaro'],
        datasets: [
          {
            label: a.name,
            data: [a.radar.fart, a.radar.konsistens, a.radar.trend, a.radar.narvaro],
            backgroundColor: 'rgba(99,179,237,0.18)',
            borderColor: '#63b3ed',
            pointBackgroundColor: '#63b3ed',
            borderWidth: 2,
          },
          {
            label: b.name,
            data: [b.radar.fart, b.radar.konsistens, b.radar.trend, b.radar.narvaro],
            backgroundColor: 'rgba(246,173,85,0.18)',
            borderColor: '#f6ad55',
            pointBackgroundColor: '#f6ad55',
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          r: {
            min: 0,
            max: 100,
            ticks: { color: '#718096', stepSize: 25, backdropColor: 'transparent' },
            grid: { color: '#4a5568' },
            angleLines: { color: '#4a5568' },
            pointLabels: { color: '#e2e8f0', font: { size: 13 } },
          },
        },
        plugins: { legend: { labels: { color: '#e2e8f0' } } },
      },
    });
  }

  private buildTrendChart(): void {
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement | null;
    if (!canvas || !this.compareData) return;
    if (this.trendChart) this.trendChart.destroy();
    const { a, b, teamAvg } = this.compareData;

    const allMonths = [...new Set([
      ...a.monthly.map(m => m.month),
      ...b.monthly.map(m => m.month),
    ])].sort();

    const getVal = (monthly: MonthData[], month: string): number | null =>
      monthly.find(m => m.month === month)?.ibc_per_h ?? null;

    const monthLabel = (m: string): string => {
      const [y, mo] = m.split('-');
      const names = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
      return `${names[+mo - 1]} ${y.slice(2)}`;
    };

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: allMonths.map(monthLabel),
        datasets: [
          {
            label: a.name,
            data: allMonths.map(m => getVal(a.monthly, m)) as number[],
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.08)',
            tension: 0.3,
            spanGaps: true,
            pointRadius: 5,
            borderWidth: 2,
          },
          {
            label: b.name,
            data: allMonths.map(m => getVal(b.monthly, m)) as number[],
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246,173,85,0.08)',
            tension: 0.3,
            spanGaps: true,
            pointRadius: 5,
            borderWidth: 2,
          },
          {
            label: 'Lagsnitt',
            data: allMonths.map(() => teamAvg),
            borderColor: '#718096',
            borderDash: [5, 5],
            tension: 0,
            pointRadius: 0,
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#4a5568' } },
          y: {
            ticks: { color: '#718096', callback: (v) => `${v} IBC/h` },
            grid: { color: '#4a5568' },
            title: { display: true, text: 'IBC/h', color: '#718096' },
          },
        },
      },
    });
  }

  tierClass(tier: string): string {
    if (tier === 'Elite')      return 'badge-elite';
    if (tier === 'Solid')      return 'badge-solid';
    if (tier === 'Developing') return 'badge-developing';
    return 'badge-support';
  }

  vsSign(pct: number): string {
    return pct >= 0 ? `+${pct}%` : `${pct}%`;
  }

  trendIcon(dir: string): string {
    if (dir === 'okar')    return '↑';
    if (dir === 'minskar') return '↓';
    return '→';
  }

  trendClass(dir: string): string {
    if (dir === 'okar')    return 'text-success';
    if (dir === 'minskar') return 'text-danger';
    return 'text-warning';
  }

  winA(a: number, b: number, higherBetter = true): boolean {
    if (Math.abs(a - b) < 0.5) return false;
    return higherBetter ? a > b : a < b;
  }

  winB(a: number, b: number, higherBetter = true): boolean {
    if (Math.abs(a - b) < 0.5) return false;
    return higherBetter ? b > a : b < a;
  }

  getPos(op: OpStats, key: 'op1' | 'op2' | 'op3'): PosData | null {
    return op.per_position[key];
  }

  posWinA(key: 'op1' | 'op2' | 'op3'): boolean {
    if (!this.compareData) return false;
    const pa = this.compareData.a.per_position[key];
    const pb = this.compareData.b.per_position[key];
    if (!pa || !pb) return false;
    return pa.ibc_per_h > pb.ibc_per_h + 0.5;
  }

  posWinB(key: 'op1' | 'op2' | 'op3'): boolean {
    if (!this.compareData) return false;
    const pa = this.compareData.a.per_position[key];
    const pb = this.compareData.b.per_position[key];
    if (!pa || !pb) return false;
    return pb.ibc_per_h > pa.ibc_per_h + 0.5;
  }

  getTeamAvgPos(key: 'op1' | 'op2' | 'op3'): number {
    return this.compareData?.teamAvgPos[key] ?? 0;
  }

  getRecommendation(a: OpStats, b: OpStats, days: number): string {
    let scoreA = 0; let scoreB = 0;
    if (a.ibc_per_h - b.ibc_per_h > 0.5)       scoreA += 3;
    else if (b.ibc_per_h - a.ibc_per_h > 0.5)   scoreB += 3;
    if (a.consistency - b.consistency > 3)        scoreA += 2;
    else if (b.consistency - a.consistency > 3)   scoreB += 2;
    if (a.trend_slope - b.trend_slope > 3)        scoreA += 1;
    else if (b.trend_slope - a.trend_slope > 3)   scoreB += 1;
    if (a.total_shifts - b.total_shifts > 3)      scoreA += 1;
    else if (b.total_shifts - a.total_shifts > 3) scoreB += 1;

    if (Math.abs(scoreA - scoreB) <= 1) {
      return `${a.name} och ${b.name} är jämnstarka under de senaste ${days} dagarna. Titta på specifika skiftdetaljer för beslutsunderlag.`;
    }
    const winner = scoreA > scoreB ? a : b;
    const ws = Math.max(scoreA, scoreB);
    return `${winner.name} rekommenderas för bonus – starkare på ${ws} av ${scoreA + scoreB} poäng. IBC/h: ${winner.ibc_per_h} (${this.vsSign(winner.vs_team_pct)} vs lag). Konsistens: ${winner.consistency}%.`;
  }

  trackByNum(_: number, op: OperatorItem): string { return op.number; }
}
