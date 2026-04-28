import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface SynergyBestPartner {
  partner_num: number;
  partner_name: string;
  synergy_pct: number;
  together_ibc_h: number;
  together_shifts: number;
}

interface SynergyOperator {
  number: number;
  name: string;
  total_shifts: number;
  avg_ibc_h: number;
  best_partner: SynergyBestPartner | null;
}

interface SynergyPair {
  op_a: number;
  name_a: string;
  op_b: number;
  name_b: string;
  together_shifts: number;
  together_ibc_h: number;
  a_avg_ibc_h: number;
  b_avg_ibc_h: number;
  avg_baseline: number;
  synergy: number;
  synergy_pct: number;
}

interface SynergyResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  operators: SynergyOperator[];
  pairs: SynergyPair[];
}

@Component({
  standalone: true,
  selector: 'app-operator-synergy',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-synergy.html',
  styleUrl: './operator-synergy.css'
})
export class OperatorSynergyPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  days = 90;
  loading = false;
  error = '';
  view: 'pairs' | 'operators' = 'pairs';
  showAll = false;

  operators: SynergyOperator[] = [];
  pairs: SynergyPair[] = [];
  from = '';
  to = '';

  Math = Math;

  get visiblePairs(): SynergyPair[] {
    return this.showAll ? this.pairs : this.pairs.slice(0, 15);
  }

  get totalPairs(): number {
    return this.pairs.length;
  }

  get topPair(): SynergyPair | null {
    return this.pairs[0] ?? null;
  }

  get positivePairs(): number {
    return this.pairs.filter(p => p.synergy_pct > 0).length;
  }

  get avgSynergy(): number {
    if (!this.pairs.length) return 0;
    return Math.round(
      this.pairs.reduce((s, p) => s + p.synergy_pct, 0) / this.pairs.length * 10
    ) / 10;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.showAll = false;

    this.http.get<SynergyResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-synergy&days=${this.days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta teamkemidata.';
        return;
      }
      this.operators = res.operators;
      this.pairs     = res.pairs;
      this.from      = res.from;
      this.to        = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  synergyColor(pct: number): string {
    if (pct >= 15)  return '#68d391';
    if (pct >= 5)   return '#9ae6b4';
    if (pct >= 0)   return '#a0aec0';
    if (pct >= -5)  return '#f6ad55';
    return '#fc8181';
  }

  synergyClass(pct: number): string {
    if (pct >= 10)  return 'syn-great';
    if (pct >= 3)   return 'syn-good';
    if (pct >= -3)  return 'syn-neutral';
    return 'syn-poor';
  }

  synergyLabel(pct: number): string {
    if (pct >= 15)  return 'Stark kemi';
    if (pct >= 5)   return 'Bra kemi';
    if (pct >= 0)   return 'Normal';
    if (pct >= -5)  return 'Lite sämre';
    return 'Dålig kemi';
  }

  barWidth(val: number, max: number): number {
    if (max <= 0) return 0;
    return Math.min(100, (val / max) * 100);
  }

  trackByPair(_: number, p: SynergyPair): string {
    return `${p.op_a}-${p.op_b}`;
  }

  trackByOp(_: number, o: SynergyOperator): number {
    return o.number;
  }
}
