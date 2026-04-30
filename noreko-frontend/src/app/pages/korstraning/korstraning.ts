import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosData {
  shifts: number;
  qualified: boolean;
  ibch: number;
  vs_avg: number | null;
}

interface OperatorSummary {
  number: number;
  name: string;
  total_skift: number;
  overall_ibch: number;
  overall_vs_team: number;
  covered_positions: number;
  positions: Record<string, PosData>;
}

interface Recommendation {
  op_num: number;
  op_name: string;
  position: string;
  pos_label: string;
  type: 'untrained' | 'weak';
  pos_shifts: number;
  pos_ibch: number;
  team_pos_avg: number;
  overall_ibch: number;
  overall_vs_team: number;
  projected_ibch: number;
  ibc_gap: number;
  priority_score: number;
  priority: 'Hög' | 'Medel' | 'Låg';
  other_qualified: number;
}

interface KorstraningResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  min_skift: number;
  team_avg: number;
  team_avg_per_pos: Record<string, number>;
  qualified_per_pos: Record<string, number>;
  recommendations: Recommendation[];
  operator_summaries: OperatorSummary[];
}

@Component({
  standalone: true,
  selector: 'app-korstraning',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './korstraning.html',
  styleUrl: './korstraning.css',
})
export class KorstraningPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  days = 180;
  minSkift = 3;

  teamAvg = 0;
  teamAvgPerPos: Partial<Record<string, number>> = {};
  qualifiedPerPos: Partial<Record<string, number>> = {};
  recommendations: Recommendation[] = [];
  operatorSummaries: OperatorSummary[] = [];

  from = '';
  to = '';

  filterPrio: 'alla' | 'Hög' | 'Medel' | 'Låg' = 'alla';
  filterType: 'alla' | 'untrained' | 'weak' = 'alla';
  filterPos: 'alla' | 'op1' | 'op2' | 'op3' = 'alla';

  readonly POSITIONS = ['op1', 'op2', 'op3'] as const;
  readonly POS_LABELS: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };
  readonly POS_ICONS: Record<string, string> = {
    op1: 'fas fa-tint',
    op2: 'fas fa-eye',
    op3: 'fas fa-truck',
  };
  readonly POS_COLORS: Record<string, string> = {
    op1: '#63b3ed',
    op2: '#68d391',
    op3: '#f6ad55',
  };

  readonly daysOptions = [
    { value: 90,  label: '90 dagar' },
    { value: 180, label: '6 månader' },
    { value: 365, label: '1 år' },
    { value: 730, label: '2 år' },
  ];
  readonly minSkiftOptions = [
    { value: 2,  label: 'min 2 skift' },
    { value: 3,  label: 'min 3 skift' },
    { value: 5,  label: 'min 5 skift' },
    { value: 10, label: 'min 10 skift' },
  ];

  get filtered(): Recommendation[] {
    return this.recommendations.filter(r => {
      if (this.filterPrio !== 'alla' && r.priority !== this.filterPrio) return false;
      if (this.filterType !== 'alla' && r.type !== this.filterType) return false;
      if (this.filterPos  !== 'alla' && r.position !== this.filterPos) return false;
      return true;
    });
  }

  get highCount(): number { return this.recommendations.filter(r => r.priority === 'Hög').length; }
  get medCount(): number  { return this.recommendations.filter(r => r.priority === 'Medel').length; }
  get lowCount(): number  { return this.recommendations.filter(r => r.priority === 'Låg').length; }

  get fullCoverageCount(): number {
    return this.operatorSummaries.filter(o => o.covered_positions === 3).length;
  }
  get weakestPos(): string {
    let min = Infinity;
    let weak = '';
    for (const p of this.POSITIONS) {
      const q = this.qualifiedPerPos[p] ?? 0;
      if (q < min) { min = q; weak = p; }
    }
    return weak ? this.POS_LABELS[weak] : '—';
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=korstraning&days=${this.days}&min_skift=${this.minSkift}`;
    this.http.get<KorstraningResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta korsträningsdata.';
          return;
        }
        this.from              = res.from;
        this.to                = res.to;
        this.teamAvg           = res.team_avg;
        this.teamAvgPerPos     = res.team_avg_per_pos;
        this.qualifiedPerPos   = res.qualified_per_pos;
        this.recommendations   = res.recommendations;
        this.operatorSummaries = res.operator_summaries;
      });
  }

  setDays(d: number): void { this.days = d; this.load(); }
  setMinSkift(n: number): void { this.minSkift = n; this.load(); }

  prioClass(p: string): string {
    if (p === 'Hög')   return 'prio-hog';
    if (p === 'Medel') return 'prio-medel';
    return 'prio-lag';
  }

  typeLabel(t: string): string {
    return t === 'untrained' ? 'Ej tränad' : 'Behöver förbättras';
  }
  typeClass(t: string): string {
    return t === 'untrained' ? 'type-untrained' : 'type-weak';
  }

  vsSign(val: number): string {
    const s = val.toFixed(1);
    return val >= 0 ? `+${s}` : s;
  }

  gapSign(g: number): string {
    return g >= 0 ? `+${g.toFixed(1)}` : g.toFixed(1);
  }

  coverageBar(op: OperatorSummary): string {
    return `${Math.round((op.covered_positions / 3) * 100)}%`;
  }

  coverageColor(op: OperatorSummary): string {
    if (op.covered_positions === 3) return '#68d391';
    if (op.covered_positions === 2) return '#f6ad55';
    return '#fc8181';
  }

  posQualColor(pos: PosData | undefined): string {
    if (!pos) return '#4a5568';
    if (!pos.qualified) return '#2d3748';
    if ((pos.vs_avg ?? 0) >= 10) return '#68d391';
    if ((pos.vs_avg ?? 0) >= -10) return '#63b3ed';
    return '#fc8181';
  }

  posQualText(pos: PosData | undefined, posKey: string): string {
    if (!pos || pos.shifts === 0) return '—';
    const ibch = pos.ibch.toFixed(1);
    return pos.qualified ? `${ibch}` : `${pos.shifts}sk`;
  }

  profileLink(num: number): string {
    return `/rebotling/operator/${num}`;
  }
}
