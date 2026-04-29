import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosData {
  antal_skift: number;
  ibch: number;
  spec_index: number;
}

interface Operator {
  number: number;
  name: string;
  total_skift: number;
  overall_ibch: number;
  positions: { op1: PosData | null; op2: PosData | null; op3: PosData | null };
  best_pos: 'op1' | 'op2' | 'op3' | null;
  spread: number;
  badge: 'specialist' | 'generalist' | 'flexibel';
}

interface Kpi {
  antal_op: number;
  specialists: number;
  generalister: number;
  avg_spread: number;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  operators: Operator[];
  kpi: Kpi;
}

type SortKey = 'spread' | 'ibch' | 'name';
type BadgeFilter = 'alla' | 'specialist' | 'generalist' | 'flexibel';

@Component({
  standalone: true,
  selector: 'app-positions-specialisering',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './positions-specialisering.html',
  styleUrl: './positions-specialisering.css',
})
export class PositionsSpecialiseringPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  days = 90;
  minSkift = 3;
  readonly dayOptions = [30, 60, 90, 180, 365];
  readonly minSkiftOptions = [2, 3, 5, 10];

  loading = false;
  error = '';

  operators: Operator[] = [];
  kpi: Kpi | null = null;
  from = '';
  to = '';

  sortKey: SortKey = 'spread';
  filterBadge: BadgeFilter = 'alla';

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  readonly posIcons: Record<string, string> = {
    op1: 'fas fa-tint',
    op2: 'fas fa-search',
    op3: 'fas fa-truck',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=positions-specialisering&days=${this.days}&min_skift=${this.minSkift}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta specialiseringsdata.';
          return;
        }
        this.operators = res.operators;
        this.kpi = res.kpi;
        this.from = res.from;
        this.to = res.to;
      });
  }

  setDays(d: number): void { this.days = d; this.load(); }
  setMinSkift(n: number): void { this.minSkift = n; this.load(); }

  get filtered(): Operator[] {
    let list = this.filterBadge === 'alla'
      ? [...this.operators]
      : this.operators.filter(o => o.badge === this.filterBadge);

    return list.sort((a, b) => {
      switch (this.sortKey) {
        case 'spread': return b.spread - a.spread;
        case 'ibch':   return b.overall_ibch - a.overall_ibch;
        case 'name':   return a.name.localeCompare(b.name, 'sv');
        default:       return 0;
      }
    });
  }

  badgeLabel(b: string): string {
    if (b === 'specialist') return 'Specialist';
    if (b === 'generalist') return 'Generalist';
    return 'Flexibel';
  }

  badgeClass(b: string): string {
    if (b === 'specialist') return 'badge-specialist';
    if (b === 'generalist') return 'badge-generalist';
    return 'badge-flexibel';
  }

  bestPosLabel(op: Operator): string {
    if (!op.best_pos) return '—';
    return this.posLabels[op.best_pos] ?? op.best_pos;
  }

  /** Bar width: scale spec_index so that 100% = half bar, 150% = full bar */
  barWidth(index: number): number {
    return Math.min(100, Math.max(2, Math.round((index / 150) * 100)));
  }

  /** Color class for a position bar based on its spec_index */
  barClass(index: number): string {
    if (index >= 110) return 'bar-strong';
    if (index >= 95)  return 'bar-neutral';
    return 'bar-weak';
  }

  /** Sign prefix for delta vs own average */
  deltaSign(index: number): string {
    const d = index - 100;
    if (d > 0) return '+';
    return '';
  }

  /** Delta vs own average in percentage points */
  delta(index: number): string {
    return `${this.deltaSign(index)}${(index - 100).toFixed(1)}%`;
  }

  getPosData(op: Operator, posKey: string): PosData | null {
    return op.positions[posKey as 'op1' | 'op2' | 'op3'] ?? null;
  }

  get specialistCount(): number { return this.operators.filter(o => o.badge === 'specialist').length; }
  get generalistCount(): number { return this.operators.filter(o => o.badge === 'generalist').length; }
}
