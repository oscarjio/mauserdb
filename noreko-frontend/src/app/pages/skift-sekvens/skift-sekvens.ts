import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorSekvens {
  number: number;
  name: string;
  total_skift: number;
  fresh_ibch: number;
  fresh_n: number;
  consec_ibch: number;
  consec_n: number;
  delta_pct: number;
  badge: 'eldig' | 'behover_vila' | 'konsistent';
}

interface SekvensResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  avg_fresh_ibch: number;
  avg_consec_ibch: number;
  avg_delta_pct: number;
  operators: OperatorSekvens[];
}

@Component({
  standalone: true,
  selector: 'app-skift-sekvens',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-sekvens.html',
  styleUrl: './skift-sekvens.css',
})
export class SkiftSekvensPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  days = 365;
  from = '';
  to = '';

  avgFreshIbch = 0;
  avgConsecIbch = 0;
  avgDeltaPct = 0;
  operators: OperatorSekvens[] = [];

  filterBadge: 'alla' | 'eldig' | 'behover_vila' | 'konsistent' = 'alla';
  Math = Math;

  readonly daysOptions = [
    { value: 90,  label: '90 dagar' },
    { value: 180, label: '180 dagar' },
    { value: 365, label: '1 år' },
    { value: 730, label: '2 år' },
  ];

  get filtered(): OperatorSekvens[] {
    if (this.filterBadge === 'alla') return this.operators;
    return this.operators.filter(o => o.badge === this.filterBadge);
  }

  get eldiga(): number   { return this.operators.filter(o => o.badge === 'eldig').length; }
  get vilorBehov(): number { return this.operators.filter(o => o.badge === 'behover_vila').length; }
  get konsekventa(): number { return this.operators.filter(o => o.badge === 'konsistent').length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=skift-sekvens&days=${this.days}`;
    this.http.get<SekvensResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta sekvensdata.';
          return;
        }
        this.from          = res.from;
        this.to            = res.to;
        this.avgFreshIbch  = res.avg_fresh_ibch;
        this.avgConsecIbch = res.avg_consec_ibch;
        this.avgDeltaPct   = res.avg_delta_pct;
        this.operators     = res.operators;
      });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  deltaLabel(op: OperatorSekvens): string {
    const sign = op.delta_pct >= 0 ? '+' : '';
    return `${sign}${op.delta_pct.toFixed(1)}%`;
  }

  deltaClass(pct: number): string {
    if (pct >= 10)  return 'delta-pos';
    if (pct <= -10) return 'delta-neg';
    return 'delta-neu';
  }

  badgeLabel(b: string): string {
    if (b === 'eldig')       return 'Eldig';
    if (b === 'behover_vila') return 'Behöver vila';
    return 'Konsistent';
  }

  badgeClass(b: string): string {
    if (b === 'eldig')       return 'badge-eldig';
    if (b === 'behover_vila') return 'badge-vila';
    return 'badge-konsistent';
  }

  barWidth(ibch: number, max: number): number {
    if (!max) return 0;
    return Math.min(100, Math.round((ibch / max) * 100));
  }

  get maxIbch(): number {
    if (!this.operators.length) return 1;
    return Math.max(...this.operators.map(o => Math.max(o.fresh_ibch, o.consec_ibch)), 1);
  }
}
