import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PositionDetail {
  ibc_h: number;
  antal_skift: number;
}

interface TypeDetail {
  ibc_h: number;
  antal_skift: number;
}

interface OperatorRec {
  number: number;
  name: string;
  ibc_h: number;
  ibc_h_30d: number;
  vs_team: number;
  tier: 'elite' | 'solid' | 'developing' | 'needs_support';
  trend: 'rising' | 'stable' | 'falling';
  best_position: string | null;
  best_position_name: string | null;
  best_position_ibc_h: number | null;
  best_shift_type: string | null;
  best_shift_type_name: string | null;
  best_shift_type_ibc_h: number | null;
  total_skift: number;
  recommendation: string;
  positions: Record<string, PositionDetail | null>;
  shift_types: Record<string, TypeDetail | null>;
}

interface SchemaResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  team_avg_ibc_h: number;
  operators: OperatorRec[];
}

@Component({
  standalone: true,
  selector: 'app-schema-rekommendationer',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './schema-rekommendationer.html',
  styleUrl: './schema-rekommendationer.css',
})
export class SchemaRekommendationerPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';
  data: SchemaResponse | null = null;

  days = 90;
  filterTier = 'all';
  sortBy = 'tier';
  expandedOp: number | null = null;

  readonly posKeys = ['op1', 'op2', 'op3'];
  readonly typeKeys = ['dag', 'kvall', 'natt'];

  readonly tierColors: Record<string, string> = {
    elite: '#f6c90e',
    solid: '#68d391',
    developing: '#f6ad55',
    needs_support: '#fc8181',
  };

  readonly tierLabels: Record<string, string> = {
    elite: 'Elite',
    solid: 'Solid',
    developing: 'Developing',
    needs_support: 'Behöver stöd',
  };

  readonly trendLabels: Record<string, string> = {
    rising: '↑ Stiger',
    stable: '→ Stabil',
    falling: '↓ Sjunker',
  };

  readonly trendColors: Record<string, string> = {
    rising: '#68d391',
    stable: '#a0aec0',
    falling: '#fc8181',
  };

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontroll',
    op3: 'Truck',
  };

  readonly typeLabels: Record<string, string> = {
    dag: 'Dagskift',
    kvall: 'Kvällsskift',
    natt: 'Nattskift',
  };

  readonly tierOptions = [
    { value: 'all',          label: 'Alla' },
    { value: 'elite',        label: 'Elite' },
    { value: 'solid',        label: 'Solid' },
    { value: 'developing',   label: 'Developing' },
    { value: 'needs_support',label: 'Behöver stöd' },
  ];

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
    this.data = null;
    this.expandedOp = null;

    const url = `${environment.apiUrl}?action=rebotling&run=schema-rekommendationer&days=${this.days}`;
    this.http.get<SchemaResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res || !res.success) { this.error = 'Kunde inte hämta data.'; return; }
        this.data = res;
      });
  }

  get filtered(): OperatorRec[] {
    if (!this.data) return [];
    let ops = [...this.data.operators];
    if (this.filterTier !== 'all') ops = ops.filter(o => o.tier === this.filterTier);
    if (this.sortBy === 'vs_team') ops.sort((a, b) => b.vs_team - a.vs_team);
    else if (this.sortBy === 'ibc_h') ops.sort((a, b) => b.ibc_h - a.ibc_h);
    else if (this.sortBy === 'name') ops.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    return ops;
  }

  get eliteCount(): number        { return this.data?.operators.filter(o => o.tier === 'elite').length ?? 0; }
  get solidCount(): number        { return this.data?.operators.filter(o => o.tier === 'solid').length ?? 0; }
  get developingCount(): number   { return this.data?.operators.filter(o => o.tier === 'developing').length ?? 0; }
  get needsSupportCount(): number { return this.data?.operators.filter(o => o.tier === 'needs_support').length ?? 0; }
  get risingCount(): number       { return this.data?.operators.filter(o => o.trend === 'rising').length ?? 0; }
  get fallingCount(): number      { return this.data?.operators.filter(o => o.trend === 'falling').length ?? 0; }

  toggleExpand(num: number): void {
    this.expandedOp = this.expandedOp === num ? null : num;
  }

  vsColor(vs: number): string {
    if (vs >= 15)  return '#f6c90e';
    if (vs >= 0)   return '#68d391';
    if (vs >= -15) return '#f6ad55';
    return '#fc8181';
  }

  getPos(op: OperatorRec, key: string): PositionDetail | null {
    return op.positions[key] ?? null;
  }

  getType(op: OperatorRec, key: string): TypeDetail | null {
    return op.shift_types[key] ?? null;
  }

  posBarWidth(op: OperatorRec, key: string): string {
    const max = Math.max(
      op.positions['op1']?.ibc_h ?? 0,
      op.positions['op2']?.ibc_h ?? 0,
      op.positions['op3']?.ibc_h ?? 0,
    );
    const val = op.positions[key]?.ibc_h ?? 0;
    return max > 0 ? `${Math.round(val / max * 100)}%` : '0%';
  }

  typeBarWidth(op: OperatorRec, key: string): string {
    const max = Math.max(
      op.shift_types['dag']?.ibc_h ?? 0,
      op.shift_types['kvall']?.ibc_h ?? 0,
      op.shift_types['natt']?.ibc_h ?? 0,
    );
    const val = op.shift_types[key]?.ibc_h ?? 0;
    return max > 0 ? `${Math.round(val / max * 100)}%` : '0%';
  }
}
