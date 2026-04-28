import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosBreakdown {
  shifts: number;
  ibc_per_h: number;
}

interface OperatorRow {
  number: number;
  name: string;
  antal_skift: number;
  ibc_totalt: number;
  ibc_per_h: number;
  vs_team_pct: number | null;
  tier: string | null;
  primary_pos: string | null;
  pos_breakdown: Record<string, PosBreakdown>;
  best_shift_ibc_h: number | null;
  worst_shift_ibc_h: number | null;
  prev_ibc_per_h: number | null;
  delta_ibc_per_h: number | null;
}

interface Summary {
  total_ibc: number;
  team_avg_ibc_h: number | null;
  total_skift: number;
  antal_operatorer: number;
  top_performer: string | null;
}

@Component({
  standalone: true,
  selector: 'app-operator-monthly-report',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-monthly-report.html',
  styleUrl: './operator-monthly-report.css'
})
export class OperatorMonthlyReportPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  selectedMonth = this.currentMonthStr();
  loading = false;
  error = '';

  operators: OperatorRow[] = [];
  summary: Summary | null = null;
  teamAvgByPos: Record<string, number | null> = {};
  sortBy: 'ibc_per_h' | 'antal_skift' | 'name' = 'ibc_per_h';
  expandedOp: number | null = null;

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  readonly tierColors: Record<string, string> = {
    'Elite':        '#68d391',
    'Solid':        '#63b3ed',
    'Developing':   '#f6ad55',
    'Behöver stöd': '#fc8181',
  };

  readonly tierBg: Record<string, string> = {
    'Elite':        'rgba(104,211,145,0.15)',
    'Solid':        'rgba(99,179,237,0.15)',
    'Developing':   'rgba(246,173,85,0.15)',
    'Behöver stöd': 'rgba(252,129,129,0.15)',
  };

  get sortedOperators(): OperatorRow[] {
    return [...this.operators].sort((a, b) => {
      if (this.sortBy === 'ibc_per_h')    return b.ibc_per_h - a.ibc_per_h;
      if (this.sortBy === 'antal_skift')  return b.antal_skift - a.antal_skift;
      return a.name.localeCompare(b.name, 'sv');
    });
  }

  get tierCounts(): Record<string, number> {
    const counts: Record<string, number> = { Elite: 0, Solid: 0, Developing: 0, 'Behöver stöd': 0 };
    for (const op of this.operators) {
      if (op.tier && counts[op.tier] !== undefined) counts[op.tier]++;
    }
    return counts;
  }

  constructor(private http: HttpClient) {}

  ngOnInit() { this.load(); }
  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); }

  load() {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.operators = [];
    this.summary = null;

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=operator-monthly-report&month=${this.selectedMonth}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.operators    = res.data.operators;
          this.summary      = res.data.summary;
          this.teamAvgByPos = res.data.team_avg_by_pos;
        } else {
          this.error = 'Kunde inte hämta månadsrapport.';
        }
      });
  }

  toggleExpand(num: number) {
    this.expandedOp = this.expandedOp === num ? null : num;
  }

  posKeys(breakdown: Record<string, PosBreakdown>): string[] {
    return Object.keys(breakdown);
  }

  monthLabel(ym: string): string {
    const [y, m] = ym.split('-').map(Number);
    const months = [
      'Januari','Februari','Mars','April','Maj','Juni',
      'Juli','Augusti','September','Oktober','November','December'
    ];
    return `${months[m - 1]} ${y}`;
  }

  prevMonth() {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    this.selectedMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    this.load();
  }

  nextMonth() {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const d = new Date(y, m, 1);
    this.selectedMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    this.load();
  }

  private currentMonthStr(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  }

  trackByNum(_: number, op: OperatorRow) { return op.number; }
}
