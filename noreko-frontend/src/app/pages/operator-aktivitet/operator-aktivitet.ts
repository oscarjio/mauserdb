import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorAktivitet {
  op_number: number;
  name: string;
  total_shifts: number;
  active_weeks: number;
  reliability: number;
  ibc_per_h: number | null;
  trend: 'okar' | 'minskar' | 'stabil';
  badge: 'flitig' | 'normal' | 'sallan';
  weekly: number[];
}

interface AktivitetResponse {
  success: boolean;
  operators: OperatorAktivitet[];
  weeks: string[];
  total_weeks: number;
  period_weeks: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-aktivitet',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-aktivitet.html',
  styleUrl: './operator-aktivitet.css'
})
export class OperatorAktivitetPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  periodWeeks = 12;
  sortBy: 'shifts' | 'reliability' | 'namn' = 'shifts';

  loading = false;
  error = '';

  operators: OperatorAktivitet[] = [];
  weeks: string[] = [];
  totalWeeks = 0;
  from = '';
  to = '';

  Math = Math;

  get sorted(): OperatorAktivitet[] {
    const ops = [...this.operators];
    if (this.sortBy === 'shifts') return ops.sort((a, b) => b.total_shifts - a.total_shifts);
    if (this.sortBy === 'reliability') return ops.sort((a, b) => b.reliability - a.reliability);
    return ops.sort((a, b) => a.name.localeCompare(b.name));
  }

  get activeCount(): number {
    return this.operators.filter(o => o.total_shifts > 0).length;
  }

  get avgReliability(): number {
    if (!this.operators.length) return 0;
    return Math.round(this.operators.reduce((s, o) => s + o.reliability, 0) / this.operators.length);
  }

  get mostActive(): OperatorAktivitet | null {
    if (!this.operators.length) return null;
    return this.operators.reduce((best, o) => o.total_shifts > best.total_shifts ? o : best);
  }

  get risingStars(): OperatorAktivitet[] {
    return this.operators.filter(o => o.trend === 'okar').slice(0, 3);
  }

  constructor(private http: HttpClient) {}

  ngOnInit() { this.load(); }
  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); }

  load() {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<AktivitetResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-aktivitet&weeks=${this.periodWeeks}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.operators   = res.operators;
          this.weeks       = res.weeks;
          this.totalWeeks  = res.total_weeks;
          this.from        = res.from;
          this.to          = res.to;
        } else {
          this.error = 'Kunde inte hämta aktivitetsdata.';
        }
      });
  }

  maxWeeklyShifts(op: OperatorAktivitet): number {
    return Math.max(...op.weekly, 1);
  }

  badgeLabel(badge: string): string {
    if (badge === 'flitig') return 'Flitig';
    if (badge === 'normal') return 'Normal';
    return 'Sällan';
  }

  badgeClass(badge: string): string {
    if (badge === 'flitig') return 'badge-flitig';
    if (badge === 'normal') return 'badge-normal';
    return 'badge-sallan';
  }

  trendIcon(trend: string): string {
    if (trend === 'okar')    return '↑';
    if (trend === 'minskar') return '↓';
    return '→';
  }

  trendClass(trend: string): string {
    if (trend === 'okar')    return 'trend-up';
    if (trend === 'minskar') return 'trend-down';
    return 'trend-stable';
  }

  reliabilityClass(r: number): string {
    if (r >= 75) return 'rel-high';
    if (r >= 40) return 'rel-mid';
    return 'rel-low';
  }

  weekLabel(wk: string): string {
    // wk = YYYYWW e.g. "202615"
    const y = wk.slice(0, 4);
    const w = wk.slice(4);
    return `v${parseInt(w, 10)}`;
  }

  trackByOp(_: number, op: OperatorAktivitet): number { return op.op_number; }
}
