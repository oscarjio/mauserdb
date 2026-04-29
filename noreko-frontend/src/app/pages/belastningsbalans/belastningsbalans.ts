import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface OpRow {
  number: number;
  name: string;
  antal_skift: number;
  total_h: number;
  avg_h: number;
  vs_snitt: number;
  cnt_op1: number;
  cnt_op2: number;
  cnt_op3: number;
  pct_op1: number;
  pct_op2: number;
  pct_op3: number;
}

interface Kpi {
  antal_op: number;
  total_skift: number;
  snitt_skift: number;
  snitt_h: number;
  gini: number;
}

interface ApiResponse {
  success: boolean;
  operators: OpRow[];
  kpi: Kpi;
  snitt_skift: number;
  from: string;
  to: string;
  days: number;
}

@Component({
  standalone: true,
  selector: 'app-belastningsbalans',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './belastningsbalans.html',
  styleUrl: './belastningsbalans.css'
})
export class BelastningsbalansPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: OpRow[] = [];
  kpi: Kpi = { antal_op: 0, total_skift: 0, snitt_skift: 0, snitt_h: 0, gini: 0 };
  snittSkift = 0;
  sortBy: 'skift' | 'timmar' | 'namn' = 'skift';
  from = '';
  to = '';

  readonly POS_COLOR = { op1: '#63b3ed', op2: '#68d391', op3: '#f6ad55' };

  get sortedOperators(): OpRow[] {
    const ops = [...this.operators];
    if (this.sortBy === 'timmar') return ops.sort((a, b) => b.total_h - a.total_h);
    if (this.sortBy === 'namn') return ops.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    return ops.sort((a, b) => b.antal_skift - a.antal_skift);
  }

  get giniLabel(): string {
    const g = this.kpi.gini;
    if (g < 0.1) return 'Mycket jämn';
    if (g < 0.2) return 'Jämn';
    if (g < 0.3) return 'Måttlig';
    if (g < 0.4) return 'Ojämn';
    return 'Mycket ojämn';
  }

  get giniColor(): string {
    const g = this.kpi.gini;
    if (g < 0.15) return '#68d391';
    if (g < 0.25) return '#9ae6b4';
    if (g < 0.35) return '#fbd38d';
    return '#fc8181';
  }

  vsColor(vs: number): string {
    if (vs >= 25) return '#fc8181';
    if (vs >= 10) return '#fbd38d';
    if (vs > -10) return '#a0aec0';
    if (vs > -25) return '#9ae6b4';
    return '#68d391';
  }

  vsSign(vs: number): string {
    return vs >= 0 ? `+${vs}%` : `${vs}%`;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.chart?.destroy(); } catch (_) {}
    this.chart = null;
  }

  load(): void {
    this.loading = true;
    this.error = '';
    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=belastningsbalans&days=${this.days}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte hämta data.'; return; }
        this.operators = res.operators;
        this.kpi = res.kpi;
        this.snittSkift = res.snitt_skift;
        this.from = res.from;
        this.to = res.to;
        setTimeout(() => this.buildChart(), 80);
      });
  }

  private buildChart(): void {
    try { this.chart?.destroy(); } catch (_) {}
    this.chart = null;

    const canvas = document.getElementById('belastningChart') as HTMLCanvasElement;
    if (!canvas || this.operators.length === 0) return;

    const sorted = [...this.operators].sort((a, b) => b.antal_skift - a.antal_skift);
    const labels = sorted.map(o => o.name);
    const data   = sorted.map(o => o.antal_skift);
    const colors = sorted.map(o =>
      o.vs_snitt >= 25 ? '#fc8181' :
      o.vs_snitt >= 10 ? '#fbd38d' :
      o.vs_snitt > -10 ? '#63b3ed' :
      o.vs_snitt > -25 ? '#9ae6b4' :
      '#68d391'
    );

    this.chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal skift',
            data,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              afterLabel: (ctx) => {
                const op = sorted[ctx.dataIndex];
                return `${op.total_h} h vid linjen · snitt ${op.avg_h} h/skift`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: { display: true, text: 'Antal skift', color: '#8fa3b8', font: { size: 11 } }
          }
        }
      },
      plugins: [{
        id: 'avgLine',
        afterDraw: (chart) => {
          const { ctx, chartArea: { left, right }, scales: { y } } = chart as any;
          const yPos = y.getPixelForValue(this.snittSkift);
          ctx.save();
          ctx.beginPath();
          ctx.setLineDash([6, 4]);
          ctx.strokeStyle = '#ecc94b';
          ctx.lineWidth = 2;
          ctx.moveTo(left, yPos);
          ctx.lineTo(right, yPos);
          ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = '#ecc94b';
          ctx.font = '11px sans-serif';
          ctx.fillText(`Snitt ${this.snittSkift}`, right - 70, yPos - 5);
          ctx.restore();
        }
      }]
    });
  }
}
