import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface CoachOperator {
  op_number: number;
  name: string;
  action_type: 'behöver_stöd' | 'bevaka' | 'stabil' | 'erkänn_framgång';
  rekommendation: string;
  ibc_h_90d: number;
  ibc_h_recent: number;
  ibc_h_baseline: number;
  delta_pct: number;
  vs_team_pct: number;
  kass_pct_recent: number;
  kass_pct_baseline: number;
  kass_delta_pp: number;
  streak: number;
  streak_dir: 'over' | 'under' | 'ingen';
  recent_skift: number;
  baseline_skift: number;
  priority_score: number;
}

interface CoachResponse {
  success: boolean;
  operators: CoachOperator[];
  counts: { behöver_stöd: number; bevaka: number; stabil: number; erkänn_framgång: number };
  team_ibc_h: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-tranar-vy',
  imports: [CommonModule, RouterModule],
  templateUrl: './tranar-vy.html',
  styleUrl: './tranar-vy.css'
})
export class TranarVyPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: CoachOperator[] = [];
  counts = { behöver_stöd: 0, bevaka: 0, stabil: 0, erkänn_framgång: 0 };
  teamIbcH = 0;
  from = '';
  to = '';

  filter: 'alla' | 'behöver_stöd' | 'bevaka' | 'erkänn_framgång' | 'stabil' = 'alla';
  Math = Math;

  get filtered(): CoachOperator[] {
    if (this.filter === 'alla') return this.operators;
    return this.operators.filter(o => o.action_type === this.filter);
  }

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

    const url = `${environment.apiUrl}?action=rebotling&run=coach-view`;
    this.http.get<CoachResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta tränarvy.';
        return;
      }
      this.operators = res.operators;
      this.counts    = res.counts;
      this.teamIbcH  = res.team_ibc_h;
      this.from      = res.from;
      this.to        = res.to;
    });
  }

  setFilter(f: typeof this.filter): void { this.filter = f; }

  actionLabel(t: string): string {
    if (t === 'behöver_stöd')  return 'Behöver stöd';
    if (t === 'bevaka')        return 'Bevaka';
    if (t === 'erkänn_framgång') return 'Erkänn framgång';
    return 'Stabil';
  }

  actionIcon(t: string): string {
    if (t === 'behöver_stöd')    return 'fas fa-exclamation-circle';
    if (t === 'bevaka')          return 'fas fa-eye';
    if (t === 'erkänn_framgång') return 'fas fa-star';
    return 'fas fa-check-circle';
  }

  streakLabel(o: CoachOperator): string {
    if (o.streak < 2 || o.streak_dir === 'ingen') return '';
    const dir = o.streak_dir === 'over' ? 'över' : 'under';
    return `${o.streak} skift i rad ${dir} snitt`;
  }

  sign(n: number): string { return n > 0 ? '+' : ''; }

  constructor(private http: HttpClient) {}
}
