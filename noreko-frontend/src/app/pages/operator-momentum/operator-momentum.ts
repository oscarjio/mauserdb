import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface MomentumOp {
  number: number;
  name: string;
  antal_skift: number;
  current_streak: number;
  current_above: boolean;
  max_streak: number;
  hit_rate: number;
  recent: number[];
  last_datum: string;
  status: 'het' | 'varm' | 'neutral' | 'sval' | 'kall';
}

@Component({
  standalone: true,
  selector: 'app-operator-momentum',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-momentum.html',
  styleUrl: './operator-momentum.css'
})
export class OperatorMomentumPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  days = 90;
  loading = false;
  error = '';
  isFetching = false;

  operators: MomentumOp[] = [];
  teamAvg = 0;
  sortBy: 'status' | 'name' | 'hitrate' | 'maxstreak' = 'status';

  get sorted(): MomentumOp[] {
    const ops = [...this.operators];
    if (this.sortBy === 'name') return ops.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'hitrate') return ops.sort((a, b) => b.hit_rate - a.hit_rate);
    if (this.sortBy === 'maxstreak') return ops.sort((a, b) => b.max_streak - a.max_streak);
    // default: status order (het first, kall second, then by streak)
    return ops;
  }

  get hetCount(): number { return this.operators.filter(o => o.status === 'het').length; }
  get varmCount(): number { return this.operators.filter(o => o.status === 'varm').length; }
  get kallCount(): number { return this.operators.filter(o => o.status === 'kall').length; }
  get svalCount(): number { return this.operators.filter(o => o.status === 'sval').length; }
  get neutralCount(): number { return this.operators.filter(o => o.status === 'neutral').length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.error = '';

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=operator-momentum&days=${this.days}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        if (!res?.success) {
          this.error = 'Kunde inte ladda momentumdata.';
          return;
        }
        this.operators = res.operators ?? [];
        this.teamAvg = res.team_avg ?? 0;
      });
  }

  statusLabel(status: string): string {
    const map: Record<string, string> = {
      het: 'Hetast', varm: 'Varm', neutral: 'Neutral', sval: 'Sval', kall: 'Kall'
    };
    return map[status] ?? status;
  }

  statusColor(status: string): string {
    const map: Record<string, string> = {
      het: '#fc8181', varm: '#f6ad55', neutral: '#a0aec0', sval: '#63b3ed', kall: '#76e4f7'
    };
    return map[status] ?? '#a0aec0';
  }

  statusBg(status: string): string {
    const map: Record<string, string> = {
      het: 'rgba(252,129,129,0.15)', varm: 'rgba(246,173,85,0.12)',
      neutral: 'rgba(160,174,192,0.08)', sval: 'rgba(99,179,237,0.12)',
      kall: 'rgba(118,228,247,0.12)'
    };
    return map[status] ?? 'transparent';
  }

  statusIcon(status: string): string {
    const map: Record<string, string> = {
      het: 'fas fa-fire', varm: 'fas fa-arrow-up', neutral: 'fas fa-minus',
      sval: 'fas fa-arrow-down', kall: 'fas fa-snowflake'
    };
    return map[status] ?? 'fas fa-minus';
  }

  dotColor(val: number): string {
    return val === 1 ? '#68d391' : '#fc8181';
  }
}
