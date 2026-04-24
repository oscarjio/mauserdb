import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface DowData {
  avg_ibc_h: number;
  shifts: number;
  vs_team: number | null;
}

interface OperatorVeckodag {
  number: number;
  name: string;
  total_shifts: number;
  best_dow: number | null;
  best_dow_name: string;
  best_avg: number;
  by_dow: { [dow: number]: DowData };
}

interface WeekdayInfo {
  dow: number;
  name: string;
  team_avg: number;
  team_shifts: number;
}

interface Recommendation {
  dow: number;
  name: string;
  best_op_name: string;
  best_op_ibc_h: number;
}

interface VeckodagResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  weekdays: WeekdayInfo[];
  operators: OperatorVeckodag[];
  team_avg_overall: number;
  recommendations: Recommendation[];
}

@Component({
  standalone: true,
  selector: 'app-operator-veckodag',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-veckodag.html',
  styleUrl: './operator-veckodag.css'
})
export class OperatorVeckodagPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorVeckodag[] = [];
  weekdays: WeekdayInfo[] = [];
  recommendations: Recommendation[] = [];
  teamAvgOverall = 0;
  days = 90;
  from = '';
  to = '';

  Math = Math;

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    const url = `${environment.apiUrl}?action=rebotling&run=operator-veckodag&days=${this.days}`;
    this.http.get<VeckodagResponse>(url).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta veckodag-data.';
        return;
      }
      this.operators = res.operators;
      this.weekdays = res.weekdays;
      this.recommendations = res.recommendations;
      this.teamAvgOverall = res.team_avg_overall;
      this.from = res.from;
      this.to = res.to;
    });
  }

  setDays(d: number): void { this.days = d; this.load(); }

  getDow(op: OperatorVeckodag, dow: number): DowData | null {
    return op.by_dow[dow] ?? null;
  }

  cellBg(op: OperatorVeckodag, dow: number): string {
    const d = op.by_dow[dow];
    if (!d) return 'var(--cell-empty)';
    const vs = d.vs_team;
    if (vs === null) return 'var(--cell-empty)';
    if (vs >= 120) return 'var(--cell-great)';
    if (vs >= 108) return 'var(--cell-good)';
    if (vs >= 93)  return 'var(--cell-avg)';
    if (vs >= 80)  return 'var(--cell-weak)';
    return 'var(--cell-poor)';
  }

  cellTextCls(op: OperatorVeckodag, dow: number): string {
    const d = op.by_dow[dow];
    if (!d) return 'cell-empty-txt';
    const vs = d.vs_team;
    if (vs === null) return 'cell-empty-txt';
    if (vs >= 108) return 'cell-hi-txt';
    if (vs >= 80)  return 'cell-mid-txt';
    return 'cell-lo-txt';
  }

  isBestDay(op: OperatorVeckodag, dow: number): boolean {
    return op.best_dow === dow;
  }

  teamCellBg(wd: WeekdayInfo): string {
    if (!wd.team_avg || !this.teamAvgOverall) return 'var(--cell-empty)';
    const vs = wd.team_avg / this.teamAvgOverall * 100;
    if (vs >= 108) return 'var(--cell-good)';
    if (vs >= 93)  return 'var(--cell-avg)';
    return 'var(--cell-weak)';
  }

  constructor(private http: HttpClient) {}
}
