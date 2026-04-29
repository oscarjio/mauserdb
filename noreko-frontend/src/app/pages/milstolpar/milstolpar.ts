import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface BestMonth {
  ym: string;
  ibc: number;
  shifts: number;
}

interface ThisMonth {
  ibc: number;
  shifts: number;
}

interface OperatorMilestone {
  number: number;
  name: string;
  active: boolean;
  career_ibc: number;
  career_shifts: number;
  career_hours: number;
  career_ibc_h: number;
  best_shift_ibch: number;
  first_shift: string;
  last_shift: string;
  years_since: number;
  months_since: number;
  best_month: BestMonth | null;
  this_month: ThisMonth | null;
  current_ms: number;
  next_ms: number | null;
  ms_label: string;
  progress_pct: number;
  ibc_to_next: number;
}

interface KPI {
  total_operators: number;
  active_operators: number;
  total_career_ibc: number;
  avg_career_ibc: number;
}

interface MilstolparResponse {
  success: boolean;
  today: string;
  this_month: string;
  operators: OperatorMilestone[];
  kpi: KPI;
}

@Component({
  standalone: true,
  selector: 'app-milstolpar',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './milstolpar.html',
  styleUrl: './milstolpar.css',
})
export class MilstolparPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorMilestone[] = [];
  kpi: KPI = { total_operators: 0, active_operators: 0, total_career_ibc: 0, avg_career_ibc: 0 };

  filter: 'alla' | 'aktiva' | 'nara' = 'aktiva';
  sortBy: 'career_ibc' | 'best_shift_ibch' | 'anniversary' | 'namn' = 'career_ibc';

  Math = Math;

  readonly milestones = [100, 500, 1000, 2500, 5000, 10000, 25000, 50000];

  readonly msColors: Record<number, string> = {
    0:     '#718096',
    100:   '#68d391',
    500:   '#63b3ed',
    1000:  '#b794f4',
    2500:  '#f6ad55',
    5000:  '#fc8181',
    10000: '#76e4f7',
    25000: '#f6c90e',
    50000: '#ffd700',
  };

  readonly msIcons: Record<number, string> = {
    0:     '🔰',
    100:   '⭐',
    500:   '🎯',
    1000:  '🏅',
    2500:  '🏆',
    5000:  '💎',
    10000: '🥇',
    25000: '👑',
    50000: '🌟',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=milstolpar`;
    this.http.get<MilstolparResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta milstolpsdata.';
          return;
        }
        this.operators = res.operators;
        this.kpi = res.kpi;
      });
  }

  get filtered(): OperatorMilestone[] {
    let ops = this.operators;
    if (this.filter === 'aktiva') ops = ops.filter(o => o.active);
    if (this.filter === 'nara') ops = ops.filter(o => o.next_ms !== null && o.progress_pct >= 80);

    switch (this.sortBy) {
      case 'best_shift_ibch':
        return [...ops].sort((a, b) => b.best_shift_ibch - a.best_shift_ibch);
      case 'anniversary':
        return [...ops].sort((a, b) => a.first_shift.localeCompare(b.first_shift));
      case 'namn':
        return [...ops].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
      default:
        return [...ops].sort((a, b) => b.career_ibc - a.career_ibc);
    }
  }

  get closeToNextCount(): number {
    return this.operators.filter(o => o.active && o.next_ms !== null && o.progress_pct >= 80).length;
  }

  msColor(ms: number): string {
    return this.msColors[ms] ?? '#718096';
  }

  msIcon(ms: number): string {
    return this.msIcons[ms] ?? '🔰';
  }

  progressBarColor(pct: number): string {
    if (pct >= 90) return '#68d391';
    if (pct >= 70) return '#f6ad55';
    return '#63b3ed';
  }

  tenureLabel(op: OperatorMilestone): string {
    if (op.years_since >= 2) return `${op.years_since} år`;
    if (op.years_since === 1) return '1 år';
    if (op.months_since >= 1) return `${op.months_since} mån`;
    return 'Ny';
  }

  formatMonth(ym: string): string {
    const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun',
                    'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    const [y, m] = ym.split('-');
    return `${months[parseInt(m, 10) - 1]} ${y}`;
  }
}
