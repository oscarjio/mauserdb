import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface PosResult {
  pos: string;
  label: string;
  antal_skift: number;
  avg_ibc_h: number;
  team_avg_ibc_h: number;
  vs_team_pct: number;
  hours_worked: number;
  ibc_impact: number;
}

interface OperatorResult {
  op_num: number;
  name: string;
  positions: PosResult[];
  total_gain: number;
  total_loss: number;
  net_impact: number;
  total_skift: number;
}

@Component({
  standalone: true,
  selector: 'app-ibc-forlust',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './ibc-forlust.html',
  styleUrl: './ibc-forlust.css'
})
export class IbcForlustPage implements OnInit, OnDestroy, AfterViewInit {
  private destroy$ = new Subject<void>();
  @ViewChild('barCanvas') barCanvas!: ElementRef<HTMLCanvasElement>;
  private chart: any = null;

  days = 90;
  loading = false;
  error = '';

  operators: OperatorResult[] = [];
  teamAvgByPos: Record<string, number | undefined> = {};
  totalLoss = 0;
  totalGain = 0;
  projectedGain = 0;
  from = '';
  to = '';

  sortBy: 'net' | 'loss' | 'gain' | 'name' = 'net';
  expandedOp: number | null = null;

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit() { this.load(); }
  ngAfterViewInit() {}

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
  }

  load() {
    this.loading = true;
    this.error = '';
    this.chart?.destroy();
    this.chart = null;

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=ibc-forlust&days=${this.days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.operators       = res.operators;
          this.teamAvgByPos    = res.team_avg_by_pos;
          this.totalLoss       = res.total_loss;
          this.totalGain       = res.total_gain;
          this.projectedGain   = res.projected_gain;
          this.from            = res.from;
          this.to              = res.to;
          setTimeout(() => this.buildChart(), 50);
        } else {
          this.error = 'Kunde inte hämta IBC-förlustdata.';
        }
      });
  }

  get sorted(): OperatorResult[] {
    const list = [...this.operators];
    switch (this.sortBy) {
      case 'loss': return list.sort((a, b) => a.net_impact - b.net_impact);
      case 'gain': return list.sort((a, b) => b.net_impact - a.net_impact);
      case 'name': return list.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
      default:     return list.sort((a, b) => a.net_impact - b.net_impact);
    }
  }

  get belowAvgCount(): number { return this.operators.filter(o => o.net_impact < 0).length; }
  get aboveAvgCount(): number { return this.operators.filter(o => o.net_impact > 0).length; }

  toggleExpand(opNum: number) {
    this.expandedOp = this.expandedOp === opNum ? null : opNum;
  }

  impactClass(val: number): string {
    if (val >= 5)  return 'impact-pos-strong';
    if (val > 0)   return 'impact-pos';
    if (val >= -5) return 'impact-neg';
    return 'impact-neg-strong';
  }

  pctClass(pct: number): string {
    if (pct >= 10)  return 'text-success';
    if (pct >= 0)   return 'text-info';
    if (pct >= -10) return 'text-warning';
    return 'text-danger';
  }

  private buildChart() {
    if (!this.barCanvas?.nativeElement || typeof Chart === 'undefined') return;
    const ops = [...this.operators].sort((a, b) => a.net_impact - b.net_impact);
    const labels = ops.map(o => o.name);
    const data   = ops.map(o => o.net_impact);
    const colors = data.map(v => v >= 0 ? 'rgba(104,211,145,0.85)' : 'rgba(252,129,129,0.85)');
    const borders = data.map(v => v >= 0 ? '#68d391' : '#fc8181');

    this.chart = new Chart(this.barCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'IBC-påverkan',
          data,
          backgroundColor: colors,
          borderColor: borders,
          borderWidth: 1,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.raw as number;
                return v >= 0 ? `+${v.toFixed(1)} IBC` : `${v.toFixed(1)} IBC`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#e2e8f0', font: { size: 11 } }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#e2e8f0', callback: (v: number | string) => { const n = +v; return `${n >= 0 ? '+' : ''}${n}`; } },
            grid: { color: '#2d3748' },
          }
        }
      }
    });
  }

  trackByOp(_: number, op: OperatorResult) { return op.op_num; }
}
