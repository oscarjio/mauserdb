import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OpAbsence {
  number: number;
  name: string;
  with_shifts: number;
  with_ibc_h: number;
  without_shifts: number;
  without_ibc_h: number | null;
  impact: number;
  attendance: number;
}

interface AbsenceResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  total_shifts: number;
  team_avg: number;
  operators: OpAbsence[];
}

@Component({
  standalone: true,
  selector: 'app-operator-avsaknad',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-avsaknad.html',
  styleUrl: './operator-avsaknad.css',
})
export class OperatorAvsaknadPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: OpAbsence[] = [];
  teamAvg = 0;
  totalShifts = 0;
  from = '';
  to = '';

  sortBy: 'impact' | 'attendance' | 'with_ibc_h' | 'name' = 'impact';

  get sorted(): OpAbsence[] {
    return [...this.operators].sort((a, b) => {
      if (this.sortBy === 'name')       return a.name.localeCompare(b.name, 'sv');
      if (this.sortBy === 'attendance') return b.attendance - a.attendance;
      if (this.sortBy === 'with_ibc_h') return b.with_ibc_h - a.with_ibc_h;
      return b.impact - a.impact;
    });
  }

  get topOperator(): OpAbsence | null {
    return this.operators.length ? this.operators[0] : null;
  }

  get avgAttendance(): number {
    if (!this.operators.length) return 0;
    return Math.round(this.operators.reduce((s, o) => s + o.attendance, 0) / this.operators.length * 10) / 10;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<AbsenceResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-avsaknad&days=${this.days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta frånvarodata.';
        return;
      }
      this.operators   = res.operators;
      this.teamAvg     = res.team_avg;
      this.totalShifts = res.total_shifts;
      this.from        = res.from;
      this.to          = res.to;
    });
  }

  impactColor(impact: number): string {
    if (impact >= 3)   return '#68d391';
    if (impact >= 1)   return '#9ae6b4';
    if (impact >= -1)  return '#a0aec0';
    if (impact >= -3)  return '#fbd38d';
    return '#fc8181';
  }

  impactLabel(impact: number): string {
    if (impact >= 3)   return 'Nyckelspelare';
    if (impact >= 1)   return 'Viktig';
    if (impact >= -1)  return 'Neutral';
    if (impact >= -3)  return 'Viss negativ';
    return 'Negativ';
  }

  attendanceBadge(pct: number): string {
    if (pct >= 70) return 'badge-high';
    if (pct >= 40) return 'badge-med';
    return 'badge-low';
  }

  withoutDisplay(op: OpAbsence): string {
    if (op.without_ibc_h === null || op.without_shifts === 0) return '–';
    return op.without_ibc_h.toFixed(1);
  }

  impactSign(impact: number): string {
    if (impact > 0) return `+${impact.toFixed(1)}`;
    return impact.toFixed(1);
  }

  barWidth(impact: number): number {
    const maxAbs = Math.max(...this.operators.map(o => Math.abs(o.impact)), 1);
    return Math.min(100, (Math.abs(impact) / maxAbs) * 100);
  }
}
