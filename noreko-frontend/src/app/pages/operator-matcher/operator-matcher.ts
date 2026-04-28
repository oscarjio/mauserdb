import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PositionData {
  ibc_per_h: number;
  antal_skift: number;
  team_avg: number;
  vs_avg_pct: number;
  rating: 'green' | 'yellow' | 'red';
}

interface OperatorMatch {
  number: number;
  name: string;
  positions: {
    op1: PositionData | null;
    op2: PositionData | null;
    op3: PositionData | null;
  };
}

interface ApiResponse {
  success: boolean;
  data: {
    from: string;
    to: string;
    days: number;
    operatorer: OperatorMatch[];
    team_avg: { op1: number; op2: number; op3: number };
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-matcher',
  imports: [CommonModule, FormsModule],
  templateUrl: './operator-matcher.html',
  styleUrl: './operator-matcher.css'
})
export class OperatorMatcherPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  loading = false;
  error = '';
  operatorer: OperatorMatch[] = [];
  teamAvg: { op1: number; op2: number; op3: number } = { op1: 0, op2: 0, op3: 0 };
  selectedDays = 30;
  fromDate = '';
  toDate = '';

  // Filter: only show operators active in at least one position
  filterActive = false;
  // Highlight: which position to sort by
  sortPos: 'op1' | 'op2' | 'op3' | 'all' = 'all';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchData(): void {
    if (this.loading) return;
    this.error = '';
    this.loading = true;
    const url = `${environment.apiUrl}?action=rebotling&run=operator-matcher&days=${this.selectedDays}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.operatorer = res.data.operatorer;
        this.teamAvg = res.data.team_avg;
        this.fromDate = res.data.from;
        this.toDate = res.data.to;
      });
  }

  get sorted(): OperatorMatch[] {
    let list = this.filterActive
      ? this.operatorer.filter(op => op.positions.op1 || op.positions.op2 || op.positions.op3)
      : [...this.operatorer];

    return list.sort((a, b) => {
      if (this.sortPos === 'all') {
        const ga = this.greenCount(a);
        const gb = this.greenCount(b);
        return gb - ga || this.bestRate(b) - this.bestRate(a);
      }
      const ra = a.positions[this.sortPos]?.ibc_per_h ?? -1;
      const rb = b.positions[this.sortPos]?.ibc_per_h ?? -1;
      return rb - ra;
    });
  }

  private greenCount(op: OperatorMatch): number {
    return (['op1', 'op2', 'op3'] as const)
      .filter(p => op.positions[p]?.rating === 'green').length;
  }

  private bestRate(op: OperatorMatch): number {
    return Math.max(
      op.positions.op1?.ibc_per_h ?? 0,
      op.positions.op2?.ibc_per_h ?? 0,
      op.positions.op3?.ibc_per_h ?? 0
    );
  }

  ratingClass(p: PositionData | null): string {
    if (!p) return 'cell-empty';
    return `cell-${p.rating}`;
  }

  ratingIcon(p: PositionData | null): string {
    if (!p) return '';
    return p.rating === 'green' ? '●' : p.rating === 'yellow' ? '●' : '●';
  }

  tooltipText(p: PositionData | null, pos: string): string {
    if (!p) return 'Ingen data';
    const posLabel: Record<string, string> = { op1: 'Tvätt', op2: 'Kontroll', op3: 'Truck' };
    return `${posLabel[pos] ?? pos}: ${p.ibc_per_h} IBC/h (snitt ${p.team_avg}, ${p.vs_avg_pct > 0 ? '+' : ''}${p.vs_avg_pct}%) · ${p.antal_skift} skift`;
  }

  greenCountForPos(pos: 'op1' | 'op2' | 'op3'): number {
    return this.operatorer.filter(op => op.positions[pos]?.rating === 'green').length;
  }

  formatDate(s: string): string {
    if (!s) return '';
    const [y, m, d] = s.split('-');
    return `${d}/${m}/${y}`;
  }
}
