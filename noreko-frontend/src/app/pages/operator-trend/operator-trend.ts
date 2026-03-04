import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';

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
  selectedWeeks: 8 | 16 | 26 = 8;

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
    this.chart?.destroy();
    this.chart = null;
  }

  loadOperators(): void {
    this.loadingOps = true;
    this.http
      .get<any>(`${this.apiBase}?action=rebotling&run=operator-list-trend`, {
        withCredentials: true
      })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loadingOps = false;
        if (res?.success) {
          this.operators = res.operators || [];
          // Välj första operatören automatiskt
          if (this.operators.length > 0 && !this.selectedOpId) {
            this.selectedOpId = this.operators[0].id;
            this.loadTrend();
          }
        }
      });
  }

  setWeeks(w: 8 | 16 | 26): void {
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
    this.loading = true;
    this.errorMsg = '';

    this.http
      .get<TrendResponse>(
        `${this.apiBase}?action=rebotling&run=operator-weekly-trend` +
        `&op_id=${this.selectedOpId}&weeks=${this.selectedWeeks}`,
        { withCredentials: true }
      )
      .pipe(
        timeout(10000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.trendData    = res.trend || [];
          this.opName       = res.op_name || '';
          this.trendArrow   = res.trend_arrow ?? null;
          this.trendPct     = res.trend_pct ?? null;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => this.buildChart(), 100);
        } else {
          this.errorMsg = res?.error || 'Kunde inte hämta trenddata.';
          this.trendData = [];
        }
      });
  }

  private buildChart(): void {
    this.chart?.destroy();
    this.chart = null;

    const canvas = document.getElementById('trendMainChart') as HTMLCanvasElement;
    if (!canvas || this.trendData.length === 0) return;

    const labels      = this.trendData.map(r => r.vecka_label);
    const ibcH        = this.trendData.map(r => r.ibc_per_h);
    const teamIbcH    = this.trendData.map(r => r.team_ibc_per_h);

    this.chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: this.opName,
            data: ibcH,
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
            data: teamIbcH,
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
        ]
      },
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
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)} IBC/h`;
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
              color: '#718096',
              font: { size: 11 }
            }
          }
        }
      }
    });
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
}
