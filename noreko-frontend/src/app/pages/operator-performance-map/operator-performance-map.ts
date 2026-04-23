import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);
Chart.defaults.color = '#e2e8f0';

interface OperatorPoint {
  op_number: number;
  name: string;
  ibc_per_h: number | null;
  vs_team: number | null;
  reject_rate: number;
  antal_skift: number;
  quadrant: 'stjarna' | 'snabb' | 'noggrann' | 'utmanad';
}

interface ApiResponse {
  success: boolean;
  operators: OperatorPoint[];
  team_ibc_per_h: number;
  team_reject_rate: number;
  period_days: number;
  from: string;
  to: string;
}

const QUADRANT_COLORS: Record<string, string> = {
  stjarna:  '#68d391',
  snabb:    '#f6ad55',
  noggrann: '#63b3ed',
  utmanad:  '#fc8181',
};

const QUADRANT_LABELS: Record<string, string> = {
  stjarna:  'Stjärna',
  snabb:    'Snabb',
  noggrann: 'Noggrann',
  utmanad:  'Utmanad',
};

const QUADRANT_DESC: Record<string, string> = {
  stjarna:  'Hög fart + låg kassation',
  snabb:    'Hög fart + hög kassation',
  noggrann: 'Låg fart + låg kassation',
  utmanad:  'Låg fart + hög kassation',
};

@Component({
  standalone: true,
  selector: 'app-operator-performance-map',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-performance-map.html',
  styleUrl: './operator-performance-map.css',
})
export class OperatorPerformanceMapPage implements OnInit, OnDestroy {
  @ViewChild('chartCanvas', { static: false }) chartCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;

  loading = false;
  error = '';
  days = 90;
  operators: OperatorPoint[] = [];
  teamIbcPerH = 0;
  teamRejectRate = 0;
  from = '';
  to = '';

  readonly dayOptions = [30, 60, 90, 180];

  readonly quadrantColors = QUADRANT_COLORS;
  readonly quadrantLabels = QUADRANT_LABELS;
  readonly quadrantDesc   = QUADRANT_DESC;
  readonly quadrantOrder: Array<'stjarna' | 'snabb' | 'noggrann' | 'utmanad'> = ['stjarna', 'snabb', 'noggrann', 'utmanad'];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
  }

  get stjarna()  { return this.operators.filter(o => o.quadrant === 'stjarna'); }
  get snabb()    { return this.operators.filter(o => o.quadrant === 'snabb'); }
  get noggrann() { return this.operators.filter(o => o.quadrant === 'noggrann'); }
  get utmanad()  { return this.operators.filter(o => o.quadrant === 'utmanad'); }

  fetchData(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=operator-performance-map&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$),
      )
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta data.';
          return;
        }
        this.operators      = res.operators;
        this.teamIbcPerH    = res.team_ibc_per_h;
        this.teamRejectRate = res.team_reject_rate;
        this.from           = res.from;
        this.to             = res.to;
        setTimeout(() => this.buildChart(), 0);
      });
  }

  buildChart(): void {
    this.chart?.destroy();
    if (!this.chartCanvas) return;

    const datasets = this.quadrantOrder.map(q => {
      const pts = this.operators.filter(o => o.quadrant === q);
      return {
        label: QUADRANT_LABELS[q],
        data: pts.map(o => ({ x: o.vs_team ?? 0, y: o.reject_rate, op: o })) as any,
        backgroundColor: QUADRANT_COLORS[q] + 'cc',
        borderColor:     QUADRANT_COLORS[q],
        borderWidth: 2,
        pointRadius: 9,
        pointHoverRadius: 12,
      };
    });

    const teamX = 100;
    const teamY = this.teamRejectRate;

    this.chart = new Chart(this.chartCanvas.nativeElement, {
      type: 'scatter',
      data: { datasets: datasets as any },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#90cdf4',
            bodyColor: '#e2e8f0',
            callbacks: {
              label: (ctx: any) => {
                const op: OperatorPoint = ctx.raw.op;
                return [
                  ` ${op.name}`,
                  ` IBC/h: ${op.ibc_per_h?.toFixed(1)} (${op.vs_team?.toFixed(0)}% av snitt)`,
                  ` Kassation: ${op.reject_rate.toFixed(1)}%`,
                  ` Skift: ${op.antal_skift}`,
                ];
              },
            },
          },
        },
        scales: {
          x: {
            title: {
              display: true,
              text: 'IBC/h vs teamsnitt (%)',
              color: '#a0aec0',
            },
            grid: { color: '#2d3748' },
            ticks: { color: '#e2e8f0' },
          },
          y: {
            title: {
              display: true,
              text: 'Kassationsgrad (%)',
              color: '#a0aec0',
            },
            grid: { color: '#2d3748' },
            ticks: { color: '#e2e8f0' },
          },
        },
      },
      plugins: [
        {
          id: 'crosshair',
          afterDraw: (chart: Chart) => {
            const ctx2 = chart.ctx;
            const xScale = chart.scales['x'];
            const yScale = chart.scales['y'];
            if (!xScale || !yScale) return;

            const xPx = xScale.getPixelForValue(teamX);
            const yPx = yScale.getPixelForValue(teamY);

            ctx2.save();
            ctx2.setLineDash([6, 4]);
            ctx2.strokeStyle = '#718096';
            ctx2.lineWidth = 1;

            // vertical line at x=100 (team avg IBC/h)
            ctx2.beginPath();
            ctx2.moveTo(xPx, yScale.top);
            ctx2.lineTo(xPx, yScale.bottom);
            ctx2.stroke();

            // horizontal line at y=teamRejectRate
            ctx2.beginPath();
            ctx2.moveTo(xScale.left, yPx);
            ctx2.lineTo(xScale.right, yPx);
            ctx2.stroke();

            ctx2.restore();
          },
        },
      ],
    });
  }

  quadrantFor(q: string): OperatorPoint[] {
    return this.operators.filter(o => o.quadrant === q);
  }
}
