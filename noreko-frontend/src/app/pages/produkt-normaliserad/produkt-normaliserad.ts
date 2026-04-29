import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ProductBreakdown {
  product_id:   number;
  product_name: string;
  op_ibc_h:     number;
  team_ibc_h:   number;
  relative_eff: number;
  skift_count:  number;
  total_ibc:    number;
  hours:        number;
}

interface OperatorRow {
  op_num:            number;
  name:              string;
  raw_ibc_h:         number;
  normalized_index:  number;
  vs_team_raw:       number;
  skift_count:       number;
  raw_rank:          number;
  norm_rank:         number;
  rank_delta:        number;
  products:          ProductBreakdown[];
}

interface NormResponse {
  success:     boolean;
  from:        string;
  to:          string;
  days:        number;
  min_shifts:  number;
  team_ibc_h:  number;
  operators:   OperatorRow[];
}

@Component({
  standalone: true,
  selector: 'app-produkt-normaliserad',
  imports: [CommonModule, RouterModule],
  templateUrl: './produkt-normaliserad.html',
  styleUrl: './produkt-normaliserad.css'
})
export class ProduktNormaliseradPage implements OnInit, OnDestroy {
  private destroy$   = new Subject<void>();
  private isFetching = false;

  loading = false;
  error   = '';

  operators:  OperatorRow[] = [];
  from        = '';
  to          = '';
  teamIbcH    = 0;
  days        = 90;
  minShifts   = 3;

  sortBy: 'norm_rank' | 'raw_rank' | 'rank_delta' | 'name' = 'norm_rank';
  filterMode: 'alla' | 'rose' | 'dropped' | 'same' = 'alla';
  expandedOp: number | null = null;

  Math = Math;

  get filtered(): OperatorRow[] {
    let list = [...this.operators];
    if (this.filterMode === 'rose')    list = list.filter(o => o.rank_delta > 0);
    if (this.filterMode === 'dropped') list = list.filter(o => o.rank_delta < 0);
    if (this.filterMode === 'same')    list = list.filter(o => o.rank_delta === 0);

    list.sort((a, b) => {
      if (this.sortBy === 'raw_rank')    return a.raw_rank  - b.raw_rank;
      if (this.sortBy === 'rank_delta')  return b.rank_delta - a.rank_delta;
      if (this.sortBy === 'name')        return a.name.localeCompare(b.name);
      return a.norm_rank - b.norm_rank;
    });
    return list;
  }

  get counts() {
    return {
      rose:    this.operators.filter(o => o.rank_delta > 0).length,
      dropped: this.operators.filter(o => o.rank_delta < 0).length,
      same:    this.operators.filter(o => o.rank_delta === 0).length,
    };
  }

  get mostImproved(): OperatorRow | null {
    if (!this.operators.length) return null;
    return this.operators.reduce((best, o) => o.rank_delta > best.rank_delta ? o : best, this.operators[0]);
  }

  get mostDropped(): OperatorRow | null {
    if (!this.operators.length) return null;
    return this.operators.reduce((worst, o) => o.rank_delta < worst.rank_delta ? o : worst, this.operators[0]);
  }

  constructor(private http: HttpClient) {}

  ngOnInit():    void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading    = true;
    this.error      = '';

    const url = `${environment.apiUrl}?action=rebotling&run=produkt-normaliserad&days=${this.days}&min_shifts=${this.minShifts}`;
    this.http.get<NormResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading    = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta normaliseringsdata.';
        return;
      }
      this.operators  = res.operators;
      this.from       = res.from;
      this.to         = res.to;
      this.teamIbcH   = res.team_ibc_h;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setMinShifts(n: number): void {
    this.minShifts = n;
    this.load();
  }

  setSort(s: typeof this.sortBy): void { this.sortBy = s; }
  setFilter(f: typeof this.filterMode): void { this.filterMode = f; }

  toggleExpand(opNum: number): void {
    this.expandedOp = this.expandedOp === opNum ? null : opNum;
  }

  indexColor(idx: number): string {
    if (idx >= 1.10) return '#276749';
    if (idx >= 1.02) return '#2f855a';
    if (idx >= 0.98) return '#4a5568';
    if (idx >= 0.90) return '#744210';
    return '#742a2a';
  }

  indexTextColor(idx: number): string {
    if (idx >= 1.02) return '#9ae6b4';
    if (idx >= 0.98) return '#a0aec0';
    return '#fc8181';
  }

  deltaLabel(delta: number): string {
    if (delta > 0) return `↑${delta}`;
    if (delta < 0) return `↓${Math.abs(delta)}`;
    return '–';
  }

  deltaClass(delta: number): string {
    if (delta > 0) return 'delta-rose';
    if (delta < 0) return 'delta-drop';
    return 'delta-same';
  }

  relEffColor(eff: number): string {
    if (eff >= 1.10) return '#68d391';
    if (eff >= 1.02) return '#9ae6b4';
    if (eff >= 0.98) return '#a0aec0';
    if (eff >= 0.90) return '#f6ad55';
    return '#fc8181';
  }
}
