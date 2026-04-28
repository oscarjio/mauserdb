import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface TeamRecord {
  min_op: number;
  mid_op: number;
  max_op: number;
  min_name: string;
  mid_name: string;
  max_name: string;
  avg_ibc_h: number;
  total_ibc: number;
  total_hours: number;
  total_shifts: number;
  first_shift: string;
  last_shift: string;
  vs_team: number;
}

interface SkiftlagResponse {
  success: boolean;
  teams: TeamRecord[];
  global_avg: number;
  days: number;
  from: string;
  to: string;
  total_teams: number;
}

@Component({
  standalone: true,
  selector: 'app-skiftlag-historik',
  imports: [CommonModule, RouterModule],
  templateUrl: './skiftlag-historik.html',
  styleUrl: './skiftlag-historik.css'
})
export class SkiftlagHistorikPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  teams: TeamRecord[] = [];
  globalAvg = 0;
  totalTeams = 0;
  from = '';
  to = '';

  days = 365;
  sortBy: 'avg_ibc_h' | 'total_shifts' | 'last_shift' | 'vs_team' = 'avg_ibc_h';

  Math = Math;

  get sorted(): TeamRecord[] {
    return [...this.teams].sort((a, b) => {
      if (this.sortBy === 'total_shifts') return b.total_shifts - a.total_shifts;
      if (this.sortBy === 'last_shift')   return b.last_shift.localeCompare(a.last_shift);
      if (this.sortBy === 'vs_team')      return b.vs_team - a.vs_team;
      return b.avg_ibc_h - a.avg_ibc_h;
    });
  }

  get top3(): TeamRecord[] {
    return this.sorted.slice(0, 3);
  }

  get totalShifts(): number {
    return this.teams.reduce((s, t) => s + t.total_shifts, 0);
  }

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

    const url = `${environment.apiUrl}?action=rebotling&run=skiftlag-historik&days=${this.days}`;
    this.http.get<SkiftlagResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta skiftlag-historik.';
        return;
      }
      this.teams      = res.teams;
      this.globalAvg  = res.global_avg;
      this.totalTeams = res.total_teams;
      this.from       = res.from;
      this.to         = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setSort(s: typeof this.sortBy): void {
    this.sortBy = s;
  }

  tier(t: TeamRecord): string {
    if (t.vs_team >= 15)  return 'elite';
    if (t.vs_team >= 0)   return 'solid';
    if (t.vs_team >= -15) return 'developing';
    return 'low';
  }

  tierLabel(t: TeamRecord): string {
    if (t.vs_team >= 15)  return 'Topp';
    if (t.vs_team >= 0)   return 'Bra';
    if (t.vs_team >= -15) return 'OK';
    return 'Svag';
  }

  vsColor(pct: number): string {
    if (pct >= 10)  return '#68d391';
    if (pct >= 0)   return '#63b3ed';
    if (pct >= -10) return '#f6ad55';
    return '#fc8181';
  }

  rankMedal(i: number): string {
    if (i === 0) return '🥇';
    if (i === 1) return '🥈';
    if (i === 2) return '🥉';
    return `${i + 1}.`;
  }

  formatDate(d: string): string {
    if (!d) return '—';
    return d.slice(0, 10);
  }

  teamNames(t: TeamRecord): string {
    return [t.min_name, t.mid_name, t.max_name].join(' + ');
  }
}
