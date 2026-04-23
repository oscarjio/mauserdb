import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface MonthCell {
  ibc_per_h: number | null;
  skift: number;
}

interface OperatorRow {
  number: number;
  name: string;
  antal_skift: number;
  ibc_per_h: number | null;
  vs_team_pct: number | null;
  tier: string | null;
  trend: 'improving' | 'stable' | 'declining' | null;
  bonus: string | null;
  month_breakdown: Record<number, MonthCell>;
}

interface Summary {
  antal_operatorer: number;
  team_avg_ibc_h: number | null;
  top_performer: string | null;
  total_skift: number;
}

@Component({
  standalone: true,
  selector: 'app-operator-kvartal',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-kvartal.html',
  styleUrl: './operator-kvartal.css'
})
export class OperatorKvartalPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  selectedQuarter = this.currentQuarterStr();
  loading = false;
  error = '';

  operators: OperatorRow[] = [];
  summary: Summary | null = null;
  months: number[] = [];
  teamAvgIbcH: number | null = null;
  quarterLabel = '';
  sortBy: 'ibc_per_h' | 'antal_skift' | 'name' = 'ibc_per_h';

  readonly monthNames = [
    '', 'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun',
    'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'
  ];

  readonly tierColors: Record<string, string> = {
    'Elite':        '#68d391',
    'Solid':        '#63b3ed',
    'Developing':   '#f6ad55',
    'Behöver stöd': '#fc8181',
  };

  readonly bonusColors: Record<string, string> = {
    'Bonusnivå A': '#68d391',
    'Bonusnivå B': '#63b3ed',
    'Bonusnivå C': '#f6ad55',
    'Ingen bonus': '#718096',
  };

  get sortedOperators(): OperatorRow[] {
    return [...this.operators].sort((a, b) => {
      if (this.sortBy === 'ibc_per_h')   return (b.ibc_per_h ?? 0) - (a.ibc_per_h ?? 0);
      if (this.sortBy === 'antal_skift') return b.antal_skift - a.antal_skift;
      return a.name.localeCompare(b.name, 'sv');
    });
  }

  get tierCounts(): Record<string, number> {
    const c: Record<string, number> = { Elite: 0, Solid: 0, Developing: 0, 'Behöver stöd': 0 };
    for (const op of this.operators) {
      if (op.tier && c[op.tier] !== undefined) c[op.tier]++;
    }
    return c;
  }

  get availableQuarters(): string[] {
    const result: string[] = [];
    const now = new Date();
    const currY = now.getFullYear();
    const currQ = Math.ceil((now.getMonth() + 1) / 3);
    for (let y = currY; y >= currY - 1; y--) {
      const maxQ = y === currY ? currQ : 4;
      for (let q = maxQ; q >= 1; q--) {
        result.push(`${y}Q${q}`);
      }
    }
    return result;
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
      `${environment.apiUrl}?action=rebotling&run=operator-kvartal&quarter=${this.selectedQuarter}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.operators     = res.data.operators;
          this.summary       = res.data.summary;
          this.months        = res.data.months;
          this.teamAvgIbcH   = res.data.team_avg_ibc_h;
          this.quarterLabel  = res.data.quarter;
        } else {
          this.error = 'Kunde inte hämta kvartalsutvärdering.';
        }
      });
  }

  monthCell(op: OperatorRow, mo: number): MonthCell {
    return op.month_breakdown[mo] ?? { ibc_per_h: null, skift: 0 };
  }

  trendIcon(trend: string | null): string {
    if (trend === 'improving') return '↑';
    if (trend === 'declining') return '↓';
    return '→';
  }

  trendClass(trend: string | null): string {
    if (trend === 'improving') return 'trend-up';
    if (trend === 'declining') return 'trend-down';
    return 'trend-stable';
  }

  printPage() {
    window.print();
  }

  trackByNum(_: number, op: OperatorRow) { return op.number; }

  private currentQuarterStr(): string {
    const now = new Date();
    const q = Math.ceil((now.getMonth() + 1) / 3);
    return `${now.getFullYear()}Q${q}`;
  }
}
