import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OpPosData {
  ibc_per_h: number;
  antal_skift: number;
  team_avg: number;
  vs_avg_pct: number;
  rating: string;
}

interface Operator {
  number: number;
  name: string;
  positions: {
    op1: OpPosData | null;
    op2: OpPosData | null;
    op3: OpPosData | null;
  };
}

interface TeamAvg {
  op1: number;
  op2: number;
  op3: number;
}

interface ApiResponse {
  success: boolean;
  data: {
    operatorer: Operator[];
    team_avg: TeamAvg;
    from: string;
    to: string;
    days: number;
  };
}

interface TeamCombo {
  op1: Operator;
  op2: Operator;
  op3: Operator;
  ibc1: number;
  ibc2: number;
  ibc3: number;
  total: number;
}

interface SelectableOp {
  op: Operator;
  selected: boolean;
}

@Component({
  standalone: true,
  selector: 'app-team-optimizer',
  imports: [CommonModule, FormsModule],
  templateUrl: './team-optimizer.html',
  styleUrl: './team-optimizer.css',
})
export class TeamOptimizerPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  loading = false;
  error = '';
  days = 60;
  readonly dayOptions = [14, 30, 60, 90];

  selectableOps: SelectableOp[] = [];
  teamAvg: TeamAvg = { op1: 0, op2: 0, op3: 0 };
  topCombos: TeamCombo[] = [];

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

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
    this.loading = true;
    this.error = '';
    this.topCombos = [];

    const url = `${environment.apiUrl}?action=rebotling&run=operator-matcher&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.teamAvg = res.data.team_avg;
        this.selectableOps = res.data.operatorer.map(op => ({ op, selected: true }));
        this.computeOptimal();
      });
  }

  onDaysChange(d: number): void {
    this.days = d;
    this.fetchData();
  }

  toggleAll(select: boolean): void {
    this.selectableOps.forEach(s => s.selected = select);
    this.computeOptimal();
  }

  computeOptimal(): void {
    const selected = this.selectableOps.filter(s => s.selected).map(s => s.op);
    if (selected.length < 3) {
      this.topCombos = [];
      return;
    }

    const combos: TeamCombo[] = [];
    const n = selected.length;

    for (let i = 0; i < n; i++) {
      for (let j = 0; j < n; j++) {
        if (j === i) continue;
        for (let k = 0; k < n; k++) {
          if (k === i || k === j) continue;
          const a = selected[i];
          const b = selected[j];
          const c = selected[k];
          const ibc1 = a.positions.op1?.ibc_per_h ?? 0;
          const ibc2 = b.positions.op2?.ibc_per_h ?? 0;
          const ibc3 = c.positions.op3?.ibc_per_h ?? 0;
          combos.push({ op1: a, op2: b, op3: c, ibc1, ibc2, ibc3, total: ibc1 + ibc2 + ibc3 });
        }
      }
    }

    combos.sort((a, b) => b.total - a.total);
    this.topCombos = combos.slice(0, 5);
  }

  get selectedCount(): number {
    return this.selectableOps.filter(s => s.selected).length;
  }

  ratingClass(pos: string, ibc: number): string {
    const avg = this.teamAvg[pos as keyof TeamAvg];
    if (!avg || ibc === 0) return 'chip-none';
    const ratio = ibc / avg;
    if (ratio >= 1.10) return 'chip-top';
    if (ratio >= 0.90) return 'chip-avg';
    return 'chip-below';
  }

  ibcLabel(ibc: number): string {
    return ibc > 0 ? `${ibc.toFixed(1)} IBC/h` : '–';
  }

  comboRankClass(idx: number): string {
    if (idx === 0) return 'combo-best';
    if (idx === 1) return 'combo-second';
    return 'combo-rest';
  }
}
