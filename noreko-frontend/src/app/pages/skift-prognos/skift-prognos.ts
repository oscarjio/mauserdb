import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChildren, QueryList } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface OperatorOption {
  number: number;
  name: string;
}

interface OpForecast {
  number: number;
  name: string;
  avg_ibc_h: number;
  antal_skift: number;
  consistency: number;
  vs_team: number;
  rating: 'topp' | 'snitt' | 'under' | 'ingen';
  shifts: number[];
}

interface PositionForecast {
  position: string;
  col: string;
  team_avg: number;
  team_shifts: number;
  operator: OpForecast | null;
}

interface Forecast {
  positions: PositionForecast[];
  predicted_team_ibc_h: number;
  team_avg_overall: number;
  vs_team_overall: number;
}

interface PrognoseResponse {
  success: boolean;
  operators: OperatorOption[];
  days: number;
  from: string;
  to: string;
  forecast: Forecast | null;
}

@Component({
  standalone: true,
  selector: 'app-skift-prognos',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-prognos.html',
  styleUrl: './skift-prognos.css'
})
export class SkiftPrognosPage implements OnInit, OnDestroy, AfterViewInit {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private charts: Chart[] = [];

  Math = Math;

  operators: OperatorOption[] = [];
  loading = false;
  error = '';

  op1: number | null = null;
  op2: number | null = null;
  op3: number | null = null;

  forecast: Forecast | null = null;
  days = 90;
  from = '';
  to = '';

  @ViewChildren('chartCanvas') chartCanvases!: QueryList<ElementRef<HTMLCanvasElement>>;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadOperators();
  }

  ngAfterViewInit(): void {
    this.chartCanvases.changes.pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.renderCharts();
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    this.charts.forEach(c => c.destroy());
    this.charts = [];
  }

  private loadOperators(): void {
    const url = `${environment.apiUrl}?action=rebotling&run=skift-prognos`;
    this.http.get<PrognoseResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.operators = res.operators;
      }
    });
  }

  berakna(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.forecast = null;
    this.destroyCharts();

    const params: string[] = [];
    if (this.op1 !== null) params.push(`op1=${this.op1}`);
    if (this.op2 !== null) params.push(`op2=${this.op2}`);
    if (this.op3 !== null) params.push(`op3=${this.op3}`);

    const url = `${environment.apiUrl}?action=rebotling&run=skift-prognos&${params.join('&')}`;
    this.http.get<PrognoseResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loading = false;
      this.isFetching = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta prognos. Försök igen.';
        return;
      }
      this.forecast = res.forecast;
      this.days = res.days;
      this.from = res.from;
      this.to = res.to;
    });
  }

  private renderCharts(): void {
    if (!this.forecast) return;
    this.destroyCharts();

    const canvases = this.chartCanvases.toArray();
    this.forecast.positions.forEach((pos, i) => {
      const op = pos.operator;
      if (!op || op.shifts.length === 0 || !canvases[i]) return;

      const ctx = canvases[i].nativeElement.getContext('2d');
      if (!ctx) return;

      const color = this.ratingColor(op.rating);
      const chart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: op.shifts.map((_, idx) => `Skift ${idx + 1}`),
          datasets: [{
            label: 'IBC/h',
            data: op.shifts,
            backgroundColor: color + '99',
            borderColor: color,
            borderWidth: 1,
          }, {
            label: 'Teamsnitt',
            data: op.shifts.map(() => pos.team_avg),
            type: 'line' as any,
            borderColor: '#718096',
            borderDash: [5, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
            tooltip: { backgroundColor: '#2d3748', titleColor: '#90cdf4', bodyColor: '#e2e8f0' }
          },
          scales: {
            x: { ticks: { color: '#a0aec0', maxTicksLimit: 8 }, grid: { color: '#2d374866' } },
            y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d374866' }, beginAtZero: true }
          }
        }
      });
      this.charts.push(chart);
    });
  }

  ratingColor(rating: string): string {
    switch (rating) {
      case 'topp':  return '#68d391';
      case 'snitt': return '#63b3ed';
      case 'under': return '#fc8181';
      default:      return '#718096';
    }
  }

  ratingLabel(rating: string): string {
    switch (rating) {
      case 'topp':  return 'Topp';
      case 'snitt': return 'Snitt';
      case 'under': return 'Under snitt';
      default:      return 'Ingen data';
    }
  }

  vsTeamArrow(vs: number): string {
    if (vs > 5)  return '↑';
    if (vs < -5) return '↓';
    return '→';
  }

  vsTeamClass(vs: number): string {
    if (vs > 5)  return 'text-success';
    if (vs < -5) return 'text-danger';
    return 'text-muted';
  }

  consistencyLabel(c: number): string {
    if (c >= 80) return 'Stabil';
    if (c >= 60) return 'Variabel';
    return 'Opålitlig';
  }

  overallClass(vs: number): string {
    if (vs > 5)  return 'card-success';
    if (vs < -5) return 'card-danger';
    return 'card-neutral';
  }

  hasAnyOp(): boolean {
    return this.op1 !== null || this.op2 !== null || this.op3 !== null;
  }

  operatorName(num: number | null): string {
    if (num === null) return '—';
    return this.operators.find(o => o.number === num)?.name ?? '—';
  }
}
