import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface CauseData {
  orsak_id: number;
  namn: string;
  antal: number;
  pct: number;
}

interface OperatorCauses {
  number: number;
  name: string;
  total_kassation: number;
  top_cause: string;
  causes: CauseData[];
}

interface CausesMeta {
  id: number;
  namn: string;
}

interface KassationsorsakResponse {
  success: boolean;
  operators: OperatorCauses[];
  all_causes: CausesMeta[];
  total_events: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-kassationsorsak-per-operator',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './kassationsorsak-per-operator.html',
  styleUrl: './kassationsorsak-per-operator.css'
})
export class KassationsorsakPerOperatorPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorCauses[] = [];
  allCauses: CausesMeta[] = [];
  totalEvents = 0;
  from = '';
  to = '';

  days = 90;
  sortBy: 'total' | 'name' | 'top_cause' = 'total';
  expandedOp: number | null = null;

  Math = Math;

  readonly CAUSE_COLORS = [
    '#fc8181', '#f6ad55', '#faf089', '#68d391',
    '#63b3ed', '#a78bfa', '#f687b3', '#4fd1c5',
    '#ed8936', '#9ae6b4', '#76e4f7', '#b794f4',
  ];

  get sorted(): OperatorCauses[] {
    const list = [...this.operators];
    if (this.sortBy === 'name') return list.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'top_cause') return list.sort((a, b) => a.top_cause.localeCompare(b.top_cause, 'sv'));
    return list.sort((a, b) => b.total_kassation - a.total_kassation);
  }

  get topCause(): string {
    if (!this.allCauses.length || !this.operators.length) return '–';
    const counts: Record<string, number> = {};
    for (const op of this.operators) {
      for (const c of op.causes) {
        counts[c.namn] = (counts[c.namn] ?? 0) + c.antal;
      }
    }
    return Object.entries(counts).sort((a, b) => b[1] - a[1])[0]?.[0] ?? '–';
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.expandedOp = null;

    const url = `${environment.apiUrl}?action=rebotling&run=kassationsorsak-per-operator&days=${this.days}`;
    this.http.get<KassationsorsakResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta kassationsorsaksdata.';
        return;
      }
      this.operators    = res.operators;
      this.allCauses    = res.all_causes;
      this.totalEvents  = res.total_events;
      this.from         = res.from;
      this.to           = res.to;
    });
  }

  toggleExpand(num: number): void {
    this.expandedOp = this.expandedOp === num ? null : num;
  }

  causeColor(idx: number): string {
    return this.CAUSE_COLORS[idx % this.CAUSE_COLORS.length];
  }

  causeColorByName(namn: string): string {
    const idx = this.allCauses.findIndex(c => c.namn === namn);
    return this.causeColor(idx >= 0 ? idx : 0);
  }

  barWidth(antal: number, total: number): number {
    return total > 0 ? Math.round((antal / total) * 100) : 0;
  }
}
