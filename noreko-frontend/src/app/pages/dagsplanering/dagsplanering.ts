import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosStats {
  ibc_h: number;
  vs_team_pct: number;
  antal_skift: number;
}

interface Operator {
  number: number;
  name: string;
  shifts_90d: number;
  shifts_30d: number;
  shifts_7d: number;
  shifts_today: number;
  last_shift_date: string | null;
  days_since_last: number;
  pos: { op1: PosStats | null; op2: PosStats | null; op3: PosStats | null };
  recent_avg: number | null;
  avg_30d: number | null;
  form_ratio: number | null;
  form_label: 'het' | 'varm' | 'neutral' | 'sval' | 'kall' | 'okänd';
}

interface TeamAvg { op1: number; op2: number; op3: number; }

interface Suggestion {
  op1: number | null;
  op2: number | null;
  op3: number | null;
  expected_ibc_h: number | null;
}

interface ApiResponse {
  success: boolean;
  data: {
    operators: Operator[];
    team_avg: TeamAvg;
    suggestion: Suggestion | null;
    today: string;
  };
}

@Component({
  standalone: true,
  selector: 'app-dagsplanering',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './dagsplanering.html',
  styleUrl: './dagsplanering.css',
})
export class DagsplaneringPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  Math = Math;

  loading = false;
  error = '';

  operators: Operator[] = [];
  teamAvg: TeamAvg = { op1: 0, op2: 0, op3: 0 };
  suggestion: Suggestion | null = null;
  today = '';

  // Manual selection
  selectedOp1: number | null = null;
  selectedOp2: number | null = null;
  selectedOp3: number | null = null;

  // Sort
  sortField: 'name' | 'shifts_7d' | 'days_since_last' | 'form_ratio' | 'avg_30d' = 'days_since_last';
  sortAsc = true;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = '';

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=dagsplanering`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loading = false;
      if (res?.success) {
        this.operators = res.data.operators;
        this.teamAvg   = res.data.team_avg;
        this.suggestion = res.data.suggestion;
        this.today      = res.data.today;
        // Pre-populate manual selection from suggestion
        if (this.suggestion) {
          this.selectedOp1 = this.suggestion.op1;
          this.selectedOp2 = this.suggestion.op2;
          this.selectedOp3 = this.suggestion.op3;
        }
      } else {
        this.error = 'Kunde inte hämta planeringsdata.';
      }
    });
  }

  get sortedOperators(): Operator[] {
    const ops = [...this.operators];
    ops.sort((a, b) => {
      let va: any, vb: any;
      switch (this.sortField) {
        case 'name':           va = a.name; vb = b.name; break;
        case 'shifts_7d':      va = a.shifts_7d; vb = b.shifts_7d; break;
        case 'days_since_last':va = a.days_since_last; vb = b.days_since_last; break;
        case 'form_ratio':     va = a.form_ratio ?? 0; vb = b.form_ratio ?? 0; break;
        case 'avg_30d':        va = a.avg_30d ?? 0; vb = b.avg_30d ?? 0; break;
        default:               va = 0; vb = 0;
      }
      if (va < vb) return this.sortAsc ? -1 : 1;
      if (va > vb) return this.sortAsc ? 1 : -1;
      return 0;
    });
    return ops;
  }

  setSort(field: typeof this.sortField): void {
    if (this.sortField === field) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortField = field;
      this.sortAsc = field === 'name';
    }
  }

  applySelection(): void {
    this.selectedOp1 = this.suggestion?.op1 ?? null;
    this.selectedOp2 = this.suggestion?.op2 ?? null;
    this.selectedOp3 = this.suggestion?.op3 ?? null;
  }

  opName(num: number | null): string {
    if (num === null) return '—';
    return this.operators.find(o => o.number === num)?.name ?? `#${num}`;
  }

  opByNum(num: number | null): Operator | null {
    if (num === null) return null;
    return this.operators.find(o => o.number === num) ?? null;
  }

  posIbcH(op: Operator | null, pos: 'op1' | 'op2' | 'op3'): string {
    if (!op) return '—';
    const p = op.pos[pos];
    return p ? `${p.ibc_h.toFixed(1)}` : '—';
  }

  posVsTeam(op: Operator | null, pos: 'op1' | 'op2' | 'op3'): number | null {
    if (!op) return null;
    return op.pos[pos]?.vs_team_pct ?? null;
  }

  formClass(label: string): string {
    switch (label) {
      case 'het':     return 'form-hot';
      case 'varm':    return 'form-warm';
      case 'neutral': return 'form-neutral';
      case 'sval':    return 'form-cool';
      case 'kall':    return 'form-cold';
      default:        return 'form-unknown';
    }
  }

  formEmoji(label: string): string {
    switch (label) {
      case 'het':     return '🔥';
      case 'varm':    return '↑';
      case 'neutral': return '→';
      case 'sval':    return '↓';
      case 'kall':    return '❄️';
      default:        return '?';
    }
  }

  workloadClass(shifts7d: number): string {
    if (shifts7d >= 5) return 'workload-high';
    if (shifts7d >= 3) return 'workload-mid';
    return 'workload-low';
  }

  daysSinceClass(days: number): string {
    if (days === 0) return 'since-today';
    if (days === 1) return 'since-yesterday';
    if (days <= 3)  return 'since-recent';
    return 'since-old';
  }

  vsTeamClass(pct: number | null): string {
    if (pct === null) return '';
    if (pct >= 8)  return 'vs-good';
    if (pct >= -8) return 'vs-mid';
    return 'vs-bad';
  }

  get selectedTeamPreview(): { name: string; pos: 'op1' | 'op2' | 'op3'; ibcH: string; vsPct: number | null }[] {
    const slots: { posKey: 'op1' | 'op2' | 'op3'; num: number | null; label: string }[] = [
      { posKey: 'op1', num: this.selectedOp1, label: 'Tvättplats' },
      { posKey: 'op2', num: this.selectedOp2, label: 'Kontroll' },
      { posKey: 'op3', num: this.selectedOp3, label: 'Truck' },
    ];
    return slots.map(s => {
      const op = this.opByNum(s.num);
      return {
        name:   op ? op.name : '(Välj operatör)',
        pos:    s.posKey,
        ibcH:   this.posIbcH(op, s.posKey),
        vsPct:  this.posVsTeam(op, s.posKey),
      };
    });
  }

  get availableForOp1(): Operator[] {
    return this.operators.filter(o => o.number !== this.selectedOp2 && o.number !== this.selectedOp3);
  }
  get availableForOp2(): Operator[] {
    return this.operators.filter(o => o.number !== this.selectedOp1 && o.number !== this.selectedOp3);
  }
  get availableForOp3(): Operator[] {
    return this.operators.filter(o => o.number !== this.selectedOp1 && o.number !== this.selectedOp2);
  }
}
