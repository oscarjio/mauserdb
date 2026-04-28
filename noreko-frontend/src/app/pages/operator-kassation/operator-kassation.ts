import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorKass {
  number: number;
  name: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  total_shifts: number;
  kassationsgrad: number;
  vs_team: number;
  trend: 'better' | 'stable' | 'worse';
  status: 'bra' | 'normal' | 'hog';
}

interface KassationResponse {
  success: boolean;
  operators: OperatorKass[];
  team_kassgrad: number;
  total_ibc_ok: number;
  total_ibc_ej: number;
  total_bur_ej: number;
  best_kassgrad: number;
  worst_kassgrad: number;
  days: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-kassation',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-kassation.html',
  styleUrl: './operator-kassation.css'
})
export class OperatorKassationPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorKass[] = [];
  teamKassgrad = 0;
  totalIbcOk = 0;
  totalIbcEj = 0;
  totalBurEj = 0;
  bestKassgrad = 0;
  worstKassgrad = 0;
  days = 90;
  from = '';
  to = '';

  sortBy: 'kassationsgrad' | 'ibc_ej_ok' | 'bur_ej_ok' | 'name' = 'kassationsgrad';
  filterStatus: 'alla' | 'bra' | 'normal' | 'hog' = 'alla';

  Math = Math;

  get filtered(): OperatorKass[] {
    let list = this.filterStatus === 'alla'
      ? [...this.operators]
      : this.operators.filter(o => o.status === this.filterStatus);

    list.sort((a, b) => {
      if (this.sortBy === 'name')       return a.name.localeCompare(b.name);
      if (this.sortBy === 'ibc_ej_ok') return a.ibc_ej_ok - b.ibc_ej_ok;
      if (this.sortBy === 'bur_ej_ok') return a.bur_ej_ok - b.bur_ej_ok;
      return a.kassationsgrad - b.kassationsgrad;
    });

    return list;
  }

  get counts() {
    return {
      bra:    this.operators.filter(o => o.status === 'bra').length,
      normal: this.operators.filter(o => o.status === 'normal').length,
      hog:    this.operators.filter(o => o.status === 'hog').length,
    };
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=operator-kassation&days=${this.days}`;
    this.http.get<KassationResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta kassationsdata.';
        return;
      }
      this.operators     = res.operators;
      this.teamKassgrad  = res.team_kassgrad;
      this.totalIbcOk    = res.total_ibc_ok;
      this.totalIbcEj    = res.total_ibc_ej;
      this.totalBurEj    = res.total_bur_ej;
      this.bestKassgrad  = res.best_kassgrad;
      this.worstKassgrad = res.worst_kassgrad;
      this.from          = res.from;
      this.to            = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setSort(s: typeof this.sortBy): void {
    this.sortBy = s;
  }

  setFilter(f: typeof this.filterStatus): void {
    this.filterStatus = f;
  }

  kassBar(op: OperatorKass): number {
    const max = Math.max(this.worstKassgrad * 1.1, 1);
    return Math.min(100, (op.kassationsgrad / max) * 100);
  }

  teamBar(): number {
    const max = Math.max(this.worstKassgrad * 1.1, 1);
    return Math.min(100, (this.teamKassgrad / max) * 100);
  }

  kassColor(grad: number): string {
    if (grad <= Math.max(1.0, this.teamKassgrad * 0.6)) return '#68d391';
    if (grad <= this.teamKassgrad * 1.3)                return '#f6ad55';
    return '#fc8181';
  }

  trendLabel(t: string): string {
    if (t === 'better') return '↓ Förbättring';
    if (t === 'worse')  return '↑ Försämring';
    return '→ Stabil';
  }

  trendClass(t: string): string {
    if (t === 'better') return 'trend-better';
    if (t === 'worse')  return 'trend-worse';
    return 'trend-stable';
  }

  vsTeamLabel(vs: number): string {
    if (vs < -0.5) return `${vs.toFixed(1)}pp bättre än lag`;
    if (vs > 0.5)  return `+${vs.toFixed(1)}pp sämre än lag`;
    return 'I linje med lag';
  }

  vsTeamClass(vs: number): string {
    if (vs < -0.5) return 'vs-better';
    if (vs > 0.5)  return 'vs-worse';
    return 'vs-neutral';
  }

  statusLabel(s: string): string {
    if (s === 'bra')    return 'Låg';
    if (s === 'hog')    return 'Hög';
    return 'Normal';
  }
}
