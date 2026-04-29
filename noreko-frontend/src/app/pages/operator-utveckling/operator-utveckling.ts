import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface MonthPoint {
  ym: string;
  ibch: number;
  shifts: number;
  team_avg: number;
}

interface OperatorUtveckling {
  number: number;
  name: string;
  overall_ibch: number;
  total_shifts: number;
  first3_avg: number;
  last3_avg: number;
  delta_pct: number;
  trend: 'forbattras' | 'forsamras' | 'stabil';
  monthly: MonthPoint[];
}

interface UtvecklingResponse {
  success: boolean;
  months: number;
  from: string;
  to: string;
  month_labels: string[];
  team_avg: Record<string, number>;
  operators: OperatorUtveckling[];
}

@Component({
  standalone: true,
  selector: 'app-operator-utveckling',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-utveckling.html',
  styleUrl: './operator-utveckling.css',
})
export class OperatorUtvecklingPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  Math = Math;

  months = 12;
  loading = false;
  error = '';

  operators: OperatorUtveckling[] = [];
  monthLabels: string[] = [];
  teamAvg: Record<string, number> = {};

  filterTrend: 'alla' | 'forbattras' | 'stabil' | 'forsamras' = 'alla';
  sortBy: 'overall' | 'delta' | 'name' = 'overall';

  get filteredOperators(): OperatorUtveckling[] {
    let ops = this.operators;
    if (this.filterTrend !== 'alla') {
      ops = ops.filter(o => o.trend === this.filterTrend);
    }
    if (this.sortBy === 'delta') return [...ops].sort((a, b) => b.delta_pct - a.delta_pct);
    if (this.sortBy === 'name') return [...ops].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    return [...ops].sort((a, b) => b.overall_ibch - a.overall_ibch);
  }

  get improvingCount(): number { return this.operators.filter(o => o.trend === 'forbattras').length; }
  get stableCount(): number    { return this.operators.filter(o => o.trend === 'stabil').length; }
  get decliningCount(): number { return this.operators.filter(o => o.trend === 'forsamras').length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<UtvecklingResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-utveckling&months=${this.months}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.operators   = res.operators;
        this.monthLabels = res.month_labels;
        this.teamAvg     = res.team_avg;
      });
  }

  formatMonth(ym: string): string {
    const months = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    const parts = ym.split('-');
    const m = parseInt(parts[1], 10) - 1;
    return months[m] + ' ' + parts[0].slice(2);
  }

  formatMonthShort(ym: string): string {
    const months = ['J','F','M','A','M','J','J','A','S','O','N','D'];
    return months[parseInt(ym.split('-')[1], 10) - 1];
  }

  sparkBarHeight(ibch: number, op: OperatorUtveckling): number {
    const max = Math.max(...op.monthly.map(m => m.ibch));
    if (!max) return 0;
    return Math.round((ibch / max) * 100);
  }

  sparkBarColor(point: MonthPoint): string {
    if (point.ibch === 0) return '#2d3748';
    if (point.ibch >= point.team_avg * 1.10) return '#68d391';
    if (point.ibch >= point.team_avg * 0.90) return '#63b3ed';
    return '#fc8181';
  }

  trendColor(trend: string): string {
    if (trend === 'forbattras') return '#68d391';
    if (trend === 'forsamras') return '#fc8181';
    return '#a0aec0';
  }

  trendIcon(trend: string): string {
    if (trend === 'forbattras') return 'fas fa-arrow-up';
    if (trend === 'forsamras') return 'fas fa-arrow-down';
    return 'fas fa-minus';
  }

  trendLabel(trend: string): string {
    if (trend === 'forbattras') return 'Förbättras';
    if (trend === 'forsamras') return 'Försämras';
    return 'Stabil';
  }
}
