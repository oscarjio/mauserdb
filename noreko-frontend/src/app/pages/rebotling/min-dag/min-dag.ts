import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Subject, forkJoin, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, ChartConfiguration, registerables } from 'chart.js';
import {
  RebotlingService,
  MinDagSummaryResponse,
  MinDagCycleTrendResponse,
  MinDagGoalsProgressResponse,
  MinDagCycleTrendPoint,
} from '../../../services/rebotling.service';
import { AuthService } from '../../../services/auth.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-min-dag',
  templateUrl: './min-dag.html',
  imports: [CommonModule, RouterModule],
})
export class MinDagPage implements OnInit, OnDestroy {
  loading = true;
  error: string | null = null;

  summary: MinDagSummaryResponse['data'] | null = null;
  goals: MinDagGoalsProgressResponse['data'] | null = null;
  trendData: MinDagCycleTrendPoint[] = [];
  malSek = 0;

  operatorId: number | null = null;

  // Cached computed properties
  cachedIbcVsSnittText = '';
  cachedCykelTrendText = '';

  private chart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(
    private rebotlingService: RebotlingService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.authService.user$.pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(user => {
      this.operatorId = user?.operator_id ?? null;
      this.loadAll();
    });

    // Auto-refresh var 2:a minut (personal stats, inte live-data)
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    if (this.refreshTimer !== null) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    try { this.chart?.destroy(); } catch (_e) { /* ignore */ }
    this.chart = null;
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = null;

    const opId = this.operatorId ?? undefined;

    forkJoin({
      summary: this.rebotlingService.getMinDagSummary(opId).pipe(timeout(15000), catchError(() => of(null))),
      goals:   this.rebotlingService.getMinDagGoalsProgress(opId).pipe(timeout(15000), catchError(() => of(null))),
      trend:   this.rebotlingService.getMinDagCycleTrend(opId).pipe(timeout(15000), catchError(() => of(null))),
    }).pipe(timeout(15000), catchError(() => of({ summary: null, goals: null, trend: null })), takeUntil(this.destroy$)).subscribe(({ summary, goals, trend }) => {
      this.loading = false;
      this.isFetching = false;

      if (summary?.success) {
        this.summary = summary.data ?? null;
        // Update cached template values
        if (this.summary) {
          this.cachedIbcVsSnittText = this.ibcVsSnittText(this.summary.ibc_today, this.summary.snitt_ibc_30d);
          this.cachedCykelTrendText = this.cykelTrendText(this.summary.vs_team_cykel);
        }
      } else if (!this.operatorId) {
        this.error = 'Inget operatör-ID kopplat till ditt konto. Gå till inställningar och ange ditt Operatör-ID.';
      } else {
        this.error = summary?.error ?? 'Kunde inte hämta dagens data.';
      }

      if (goals?.success) {
        this.goals = goals.data ?? null;
      }

      if (trend?.success && trend.data) {
        this.trendData = trend.data.trend;
        this.malSek    = trend.data.mal_sek;
        this._timers.push(setTimeout(() => {
          if (!this.destroy$.closed) this.renderChart();
        }, 100));
      }
    });
  }

  private renderChart(): void {
    try { this.chart?.destroy(); } catch (_e) { /* ignore */ }
    this.chart = null;

    const canvas = document.getElementById('cycleTrendChart') as HTMLCanvasElement | null;
    if (!canvas || this.trendData.length === 0) return;

    const labels    = this.trendData.map(p => p.label);
    const cykelData = this.trendData.map(p => p.cykel_sek);
    const malLine   = this.trendData.map(() => this.malSek);

    const config: ChartConfiguration = {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Cykeltid (sek)',
            data: cykelData,
            borderColor: 'rgba(66, 153, 225, 0.9)',
            backgroundColor: 'rgba(66, 153, 225, 0.15)',
            borderWidth: 2,
            pointRadius: 5,
            pointBackgroundColor: 'rgba(66, 153, 225, 1)',
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Mål (team-snitt)',
            data: malLine,
            borderColor: 'rgba(72, 187, 120, 0.8)',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 11 }, boxWidth: 14, padding: 16 },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.96)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => {
                const val = ctx.raw as number;
                if (ctx.datasetIndex === 0) {
                  return `Cykeltid: ${val.toFixed(0)} sek (${(val / 60).toFixed(2)} min)`;
                }
                return `Mål: ${val.toFixed(0)} sek`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Timme', color: '#a0aec0', font: { size: 12 } },
          },
          y: {
            beginAtZero: false,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => `${v} s`,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Cykeltid (sekunder)', color: '#a0aec0', font: { size: 12 } },
          },
        },
      },
    };

    this.chart = new Chart(canvas, config);
  }

  // ---- Hjälpmetoder för template ----

  get todayDateStr(): string {
    const d = new Date();
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  get motivationstext(): string {
    if (!this.summary?.har_data) return 'Inga cykler registrerade idag — dags att sätta igång!';

    const ibc       = this.summary.ibc_today;
    const snitt30d  = this.summary.snitt_ibc_30d;
    const vsTeam    = this.summary.vs_team_cykel;
    const kvalitet  = this.summary.kvalitet_pct;

    if (kvalitet >= 98 && vsTeam < 0) return 'Fantastisk dag! Du levererar hög kvalitet och snabb cykeltid!';
    if (ibc > 0 && snitt30d > 0 && ibc > snitt30d * 1.1) return 'Du ligger över ditt 30-dagarssnitt — bra jobbat!';
    if (vsTeam < -10) return 'Du är snabbare än teamets snitt — fortsätt så!';
    if (vsTeam > 20) return 'Cykeltiden kan förbättras — sikta på teamets snitt!';
    if (kvalitet >= 95) return 'Bra kvalitet! Håll tempot uppe.';
    if (ibc > 0) return 'Bra start på dagen — fortsätt i samma tempo!';
    return 'Dagens produktion har startat.';
  }

  get motivationsFarg(): string {
    if (!this.summary?.har_data) return '#a0aec0';
    const vsTeam   = this.summary.vs_team_cykel;
    const kvalitet = this.summary.kvalitet_pct;
    if (kvalitet >= 98 && vsTeam < 0) return '#48bb78';
    if (vsTeam < -10 || this.summary.ibc_today > this.summary.snitt_ibc_30d * 1.1) return '#48bb78';
    if (vsTeam > 20)  return '#fc8181';
    return '#ecc94b';
  }

  cykelTrendText(vsTeam: number): string {
    if (vsTeam < -5)  return `${Math.abs(vsTeam)} sek snabbare än teamets snitt`;
    if (vsTeam > 5)   return `${vsTeam} sek långsammare än teamets snitt`;
    return 'I linje med teamets snitt';
  }

  cykelTrendFarg(vsTeam: number): string {
    if (vsTeam < -5)  return '#48bb78';
    if (vsTeam > 5)   return '#fc8181';
    return '#ecc94b';
  }

  ibcVsSnittText(ibc: number, snitt: number): string {
    if (snitt <= 0) return '';
    const diff = ibc - snitt;
    if (Math.abs(diff) < 1) return 'I linje med snittet';
    if (diff > 0) return `+${diff.toFixed(0)} vs 30-dagarssnitt`;
    return `${diff.toFixed(0)} vs 30-dagarssnitt`;
  }

  ibcVsSnittFarg(ibc: number, snitt: number): string {
    if (snitt <= 0) return '#a0aec0';
    return ibc >= snitt ? '#48bb78' : '#fc8181';
  }

  progressFarg(pct: number): string {
    if (pct >= 100) return '#48bb78';
    if (pct >= 75)  return '#ecc94b';
    if (pct >= 50)  return '#ed8936';
    return '#fc8181';
  }

  formatSek(sek: number): string {
    if (sek <= 0) return '—';
    const m = Math.floor(sek / 60);
    const s = Math.round(sek % 60);
    if (m === 0) return `${s} sek`;
    return `${m}:${String(s).padStart(2, '0')} min`;
  }
}
