import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface PositionStat {
  ibc_per_h: number;
  team_avg: number;
  antal_skift: number;
  vs_avg_pct: number;
}

interface OperatorScore {
  number: number;
  name: string;
  score: number;
  perf_score: number;
  consistency_score: number;
  trend_score: number;
  antal_skift: number;
  ibc_per_h_avg: number;
  per_position: { [key: string]: PositionStat | undefined };
  trend_weeks: number[];
}

interface ApiResponse {
  success: boolean;
  data: {
    from: string;
    to: string;
    operatorer: OperatorScore[];
    team_avg_per_pos: { op1: number; op2: number; op3: number };
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-scores',
  imports: [CommonModule, FormsModule],
  templateUrl: './operator-scores.html',
  styleUrl: './operator-scores.css'
})
export class OperatorScoresPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private trendCharts: Map<number, Chart> = new Map();

  loading = false;
  error = '';
  operatorer: OperatorScore[] = [];
  teamAvgPerPos: { op1: number; op2: number; op3: number } = { op1: 0, op2: 0, op3: 0 };
  fromDate = '';
  toDate = '';
  sortField: 'score' | 'ibc_per_h_avg' | 'antal_skift' | 'consistency_score' = 'score';
  sortDir: 1 | -1 = -1;
  filterThreshold: 'all' | 'elite' | 'solid' | 'developing' | 'attention' = 'all';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    const now = new Date();
    this.toDate = this.dateStr(now);
    const from = new Date(now.getTime() - 90 * 86400000);
    this.fromDate = this.dateStr(from);
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.trendCharts.forEach(c => c.destroy());
    this.trendCharts.clear();
  }

  private dateStr(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  setPreset(days: number): void {
    const now = new Date();
    this.toDate = this.dateStr(now);
    this.fromDate = this.dateStr(new Date(now.getTime() - days * 86400000));
    this.fetchData();
  }

  fetchData(): void {
    if (this.loading) return;
    this.error = '';
    this.loading = true;
    this.trendCharts.forEach(c => c.destroy());
    this.trendCharts.clear();

    const url = `${environment.apiUrl}?action=rebotling&run=operator-scores&from=${this.fromDate}&to=${this.toDate}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.operatorer = res.data.operatorer;
        this.teamAvgPerPos = res.data.team_avg_per_pos;
        setTimeout(() => this.buildAllTrendCharts(), 80);
      });
  }

  get sorted(): OperatorScore[] {
    let list = [...this.operatorer];
    if (this.filterThreshold !== 'all') {
      list = list.filter(op => this.tierKey(op.score) === this.filterThreshold);
    }
    return list.sort((a, b) => {
      const av = a[this.sortField] as number;
      const bv = b[this.sortField] as number;
      return (av - bv) * this.sortDir;
    });
  }

  sort(field: typeof this.sortField): void {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 1 ? -1 : 1;
    } else {
      this.sortField = field;
      this.sortDir = -1;
    }
  }

  tierKey(score: number): string {
    if (score >= 75) return 'elite';
    if (score >= 50) return 'solid';
    if (score >= 25) return 'developing';
    return 'attention';
  }

  tierLabel(score: number): string {
    const map: Record<string, string> = {
      elite: 'Elite', solid: 'Solid', developing: 'Utveckling', attention: 'Behöver stöd'
    };
    return map[this.tierKey(score)] ?? '';
  }

  tierClass(score: number): string {
    const map: Record<string, string> = {
      elite: 'tier-elite', solid: 'tier-solid',
      developing: 'tier-developing', attention: 'tier-attention'
    };
    return map[this.tierKey(score)] ?? '';
  }

  scoreBg(score: number): string {
    if (score >= 75) return '#276749';
    if (score >= 50) return '#2b4c7e';
    if (score >= 25) return '#744210';
    return '#742a2a';
  }

  posLabel(pos: string): string {
    const map: Record<string, string> = { op1: 'Tvätt', op2: 'Kontroll', op3: 'Truck' };
    return map[pos] ?? pos;
  }

  posKeys(op: OperatorScore): string[] {
    return Object.keys(op.per_position).filter(k => op.per_position[k] != null);
  }

  vsAvgClass(pct: number): string {
    if (pct >= 10) return 'text-success';
    if (pct <= -10) return 'text-danger';
    return 'text-warning';
  }

  tierCounts(): Record<string, number> {
    const c: Record<string, number> = { elite: 0, solid: 0, developing: 0, attention: 0 };
    this.operatorer.forEach(op => c[this.tierKey(op.score)]++);
    return c;
  }

  private buildAllTrendCharts(): void {
    this.operatorer.forEach(op => {
      if (!op.trend_weeks?.length) return;
      const canvas = document.getElementById(`trend-${op.number}`) as HTMLCanvasElement | null;
      if (!canvas) return;
      this.trendCharts.get(op.number)?.destroy();

      const data = op.trend_weeks;
      const avg = data.reduce((s, v) => s + v, 0) / data.length;
      const last = data[data.length - 1];
      const color = last >= avg * 1.05 ? '#68d391' : (last < avg * 0.95 ? '#fc8181' : '#f6ad55');

      const chart = new Chart(canvas, {
        type: 'line',
        data: {
          labels: data.map((_, i) => `V${i + 1}`),
          datasets: [{
            data,
            borderColor: color,
            backgroundColor: color.replace(')', ', 0.15)').replace('rgb', 'rgba'),
            borderWidth: 2,
            pointRadius: 2,
            tension: 0.3,
            fill: true,
          }]
        },
        options: {
          responsive: false,
          animation: false,
          plugins: { legend: { display: false }, tooltip: { enabled: false } },
          scales: { x: { display: false }, y: { display: false } }
        }
      });
      this.trendCharts.set(op.number, chart);
    });
  }
}
