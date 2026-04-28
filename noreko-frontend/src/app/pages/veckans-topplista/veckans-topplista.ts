import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface WeekWinner {
  number: number;
  name: string;
  ibc_ok: number;
  ibc_per_h: number;
  min: number;
  vs_team: number;
}

interface WeekResult {
  yw: number;
  week_label: string;
  week_start: string;
  winner: WeekWinner;
  runners_up: WeekWinner[];
  team_ibc_h: number;
  team_ibc: number;
}

interface WinCount {
  number: number;
  name: string;
  wins: number;
}

interface OpOverall {
  number: number;
  name: string;
  ibc_per_h: number;
}

interface VeckansResponse {
  success: boolean;
  weeks: number;
  from: string;
  to: string;
  week_results: WeekResult[];
  win_counts: WinCount[];
  op_overall: OpOverall[];
  overall_team_ibch: number;
}

@Component({
  standalone: true,
  selector: 'app-veckans-topplista',
  imports: [CommonModule, RouterModule],
  templateUrl: './veckans-topplista.html',
  styleUrl: './veckans-topplista.css'
})
export class VeckansTopplista implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;

  weeks = 12;
  loading = false;
  error = '';

  weekResults: WeekResult[] = [];
  winCounts: WinCount[] = [];
  opOverall: OpOverall[] = [];
  overallTeamIbch = 0;
  from = '';
  to = '';

  readonly WEEKS_OPTIONS = [4, 8, 12, 16, 20, 26];

  get totalWeeks(): number { return this.weekResults.length; }
  get uniqueWinners(): number { return this.winCounts.length; }
  get topWinner(): WinCount | null { return this.winCounts[0] ?? null; }

  get maxWins(): number {
    return this.winCounts.reduce((m, w) => Math.max(m, w.wins), 0);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  setWeeks(w: number): void {
    if (this.weeks === w) return;
    this.weeks = w;
    this.load();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=veckans-topplista&weeks=${this.weeks}`;
    this.http.get<VeckansResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta veckovinnare.';
        return;
      }
      this.weekResults     = res.week_results;
      this.winCounts       = res.win_counts;
      this.opOverall       = res.op_overall;
      this.overallTeamIbch = res.overall_team_ibch;
      this.from            = res.from;
      this.to              = res.to;
    });
  }

  vsColor(vs: number): string {
    if (vs >= 15) return '#68d391';
    if (vs >= 5)  return '#9ae6b4';
    if (vs >= -5) return '#a0aec0';
    if (vs >= -15) return '#fbd38d';
    return '#fc8181';
  }

  medalColor(rank: number): string {
    if (rank === 0) return '#f6c90e';
    if (rank === 1) return '#a0aec0';
    if (rank === 2) return '#c05621';
    return '#718096';
  }

  winsBarWidth(wins: number): number {
    return this.maxWins > 0 ? Math.round((wins / this.maxWins) * 100) : 0;
  }
}
