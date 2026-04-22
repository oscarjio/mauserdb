import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface ShiftData {
  skiftraknare: number;
  datum: string;
  pos: string;
  ibc_ok: number;
  ibc_per_h: number;
  vs_team_avg: number;
  drifttid: number;
  driftstopptime: number;
}

interface PosBreakdown {
  antal_skift: number;
  avg_ibc_per_h: number;
  best: number;
  worst: number;
  team_avg: number;
}

interface EffectOnTeam {
  team_avg_with_op: number;
  team_avg_without_op: number;
  shift_count_with: number;
  shift_count_without: number;
}

interface Summary {
  antal_skift: number;
  attendance_days: number;
  avg_ibc_per_h: number;
  best_shift: number;
  worst_shift: number;
  most_common_pos: string;
}

interface ApiResponse {
  success: boolean;
  data: {
    operator: { number: number; name: string };
    period: { from: string; to: string };
    summary: Summary;
    shifts: ShiftData[];
    pos_breakdown: { op1: PosBreakdown | null; op2: PosBreakdown | null; op3: PosBreakdown | null };
    team_avg_per_pos: { op1: number; op2: number; op3: number };
    effect_on_team: EffectOnTeam;
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-profile',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-profile.html',
  styleUrl: './operator-profile.css'
})
export class OperatorProfilePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;

  loading = false;
  error = '';
  opNumber = 0;
  opName = '';
  period: { from: string; to: string } | null = null;
  summary: Summary | null = null;
  shifts: ShiftData[] = [];
  posBreakdown: { op1: PosBreakdown | null; op2: PosBreakdown | null; op3: PosBreakdown | null } | null = null;
  teamAvgPerPos: { op1: number; op2: number; op3: number } = { op1: 0, op2: 0, op3: 0 };
  effectOnTeam: EffectOnTeam | null = null;
  activeTab: 'op1' | 'op2' | 'op3' = 'op1';

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats', op2: 'Kontrollstation', op3: 'Truckförare'
  };
  readonly posColors: Record<string, string> = {
    op1: '#63b3ed', op2: '#68d391', op3: '#f6ad55'
  };

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.route.paramMap.pipe(takeUntil(this.destroy$)).subscribe(params => {
      const num = parseInt(params.get('number') ?? '0', 10);
      if (num > 0) {
        this.opNumber = num;
        this.fetchData();
      } else {
        this.error = 'Ogiltigt operatörsnummer';
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chart) { this.chart.destroy(); this.chart = null; }
  }

  goBack(): void {
    this.router.navigate(['/rebotling/operator-scores']);
  }

  fetchData(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = '';
    if (this.chart) { this.chart.destroy(); this.chart = null; }

    const url = `${environment.apiUrl}?action=rebotling&run=operator-profile&op=${this.opNumber}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        const d = res.data;
        this.opName        = d.operator.name;
        this.period        = d.period;
        this.summary       = d.summary;
        this.shifts        = d.shifts;
        this.posBreakdown  = d.pos_breakdown;
        this.teamAvgPerPos = d.team_avg_per_pos;
        this.effectOnTeam  = d.effect_on_team;
        const mp = d.summary.most_common_pos as 'op1' | 'op2' | 'op3';
        this.activeTab = (['op1', 'op2', 'op3'] as const).includes(mp) ? mp : 'op1';
        setTimeout(() => this.buildChart(), 80);
      });
  }

  private buildChart(): void {
    const canvas = document.getElementById('scatterChart') as HTMLCanvasElement | null;
    if (!canvas || this.shifts.length === 0) return;
    if (this.chart) { this.chart.destroy(); }

    const datasets: any[] = [];

    for (const pos of ['op1', 'op2', 'op3'] as const) {
      const pts = this.shifts
        .filter(s => s.pos === pos)
        .map(s => ({ x: new Date(s.datum).getTime(), y: s.ibc_per_h }));
      if (pts.length === 0) continue;
      datasets.push({
        label: this.posLabels[pos],
        type: 'scatter',
        data: pts,
        backgroundColor: this.posColors[pos] + 'cc',
        borderColor: this.posColors[pos],
        pointRadius: 6,
        pointHoverRadius: 9,
      });
    }

    // Dashed average line
    if (this.summary && this.shifts.length > 1) {
      const avg = this.summary.avg_ibc_per_h;
      const minX = new Date(this.shifts[0].datum).getTime();
      const maxX = new Date(this.shifts[this.shifts.length - 1].datum).getTime();
      datasets.push({
        label: `Snitt ${avg} IBC/h`,
        type: 'line',
        data: [{ x: minX, y: avg }, { x: maxX, y: avg }],
        borderColor: '#a0aec0',
        borderWidth: 1.5,
        borderDash: [6, 4],
        pointRadius: 0,
        fill: false,
      });
    }

    this.chart = new Chart(canvas, {
      type: 'scatter',
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', boxWidth: 14, font: { size: 12 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const raw = ctx.raw as { x: number; y: number };
                const date = new Date(raw.x).toLocaleDateString('sv-SE');
                return `${ctx.dataset.label}: ${raw.y} IBC/h (${date})`;
              }
            }
          }
        },
        scales: {
          x: {
            type: 'linear',
            grid: { color: '#2d374866' },
            ticks: {
              color: '#a0aec0',
              callback: (val) => {
                const d = new Date(val as number);
                return d.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
              },
              maxTicksLimit: 8,
            },
          },
          y: {
            grid: { color: '#2d374866' },
            ticks: { color: '#a0aec0' },
            title: { display: true, text: 'IBC/h', color: '#a0aec0' },
          },
        },
      },
    });
  }

  posHasData(pos: string): boolean {
    if (pos !== 'op1' && pos !== 'op2' && pos !== 'op3') return false;
    return !!this.posBreakdown?.[pos];
  }

  getPosBreakdown(pos: string): PosBreakdown | null {
    if (pos !== 'op1' && pos !== 'op2' && pos !== 'op3') return null;
    return this.posBreakdown?.[pos] ?? null;
  }

  setActiveTab(pos: string): void {
    if (pos === 'op1' || pos === 'op2' || pos === 'op3') this.activeTab = pos;
  }

  vsAvgClass(pct: number): string {
    if (pct >= 10) return 'text-success';
    if (pct <= -10) return 'text-danger';
    return 'text-warning';
  }

  effectDiff(): number {
    if (!this.effectOnTeam) return 0;
    return Math.round((this.effectOnTeam.team_avg_with_op - this.effectOnTeam.team_avg_without_op) * 10) / 10;
  }

  shiftsForTab(): ShiftData[] {
    return this.shifts.filter(s => s.pos === this.activeTab).reverse();
  }

  formatMinutes(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }
}
