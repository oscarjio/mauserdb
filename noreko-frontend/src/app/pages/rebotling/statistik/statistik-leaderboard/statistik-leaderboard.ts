import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { RebotlingService, LeaderboardOperator, LeaderboardResponse } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-leaderboard',
  templateUrl: './statistik-leaderboard.html',
  styleUrls: ['./statistik-leaderboard.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikLeaderboardComponent implements OnInit, OnDestroy {
  loading: boolean = false;
  error: string | null = null;

  days: number = 30;
  leaderboard: LeaderboardOperator[] = [];
  fromDate: string = '';
  toDate: string = '';

  // Håll reda på om etta bytte sedan senaste hämtning
  previousFirstId: number | null = null;
  pulseFirst: boolean = false;

  private isFetching = false;
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.load();
    // Auto-refresh var 30:e sekund
    this.refreshInterval = setInterval(() => {
      if (!this.destroy$.closed) this.load(true);
    }, 30000);
  }

  ngOnDestroy(): void {
    if (this.refreshInterval !== null) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  onDaysChange(): void {
    this.previousFirstId = null;
    this.load();
  }

  load(silent: boolean = false): void {
    if (this.isFetching) return;
    this.isFetching = true;
    if (!silent) {
      this.loading = true;
      this.error = null;
    }

    this.rebotlingService.getTopOperatorsLeaderboard(this.days).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: LeaderboardResponse | null) => {
      this.isFetching = false;
      if (!silent) this.loading = false;

      if (res?.success) {
        const newFirstId = res.leaderboard[0]?.operator_id ?? null;

        // Pulsanimation om ny etta
        if (this.previousFirstId !== null && newFirstId !== null && newFirstId !== this.previousFirstId) {
          this.pulseFirst = false;
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.pulseFirst = true; }, 50));
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.pulseFirst = false; }, 2500));
        }

        this.previousFirstId = newFirstId;
        this.leaderboard = res.leaderboard;
        this.fromDate = res.from ?? '';
        this.toDate   = res.to   ?? '';
      } else if (!silent) {
        this.error = 'Kunde inte ladda leaderboard';
        this.leaderboard = [];
      }
    });
  }

  getRankClass(rank: number): string {
    if (rank === 1) return 'rank-gold';
    if (rank === 2) return 'rank-silver';
    if (rank === 3) return 'rank-bronze';
    return 'rank-default';
  }

  getRankLabel(rank: number): string {
    if (rank === 1) return '#1';
    if (rank === 2) return '#2';
    if (rank === 3) return '#3';
    return `#${rank}`;
  }

  getRankIcon(rank: number): string {
    if (rank === 1) return 'fas fa-trophy';
    if (rank === 2) return 'fas fa-medal';
    if (rank === 3) return 'fas fa-award';
    return 'fas fa-star';
  }

  getTrendIcon(trend: string): string {
    if (trend === 'up')   return 'fas fa-arrow-up trend-up';
    if (trend === 'down') return 'fas fa-arrow-down trend-down';
    if (trend === 'new')  return 'fas fa-star trend-new';
    return 'fas fa-minus trend-same';
  }

  getTrendTitle(op: LeaderboardOperator): string {
    if (op.trend === 'new') return 'Ny i toppen!';
    if (op.trend === 'up' && op.previous_rank !== null)
      return `Klättrade från plats ${op.previous_rank}`;
    if (op.trend === 'down' && op.previous_rank !== null)
      return `Föll från plats ${op.previous_rank}`;
    return 'Oförändrad placering';
  }

  isFirstAndPulsing(rank: number): boolean {
    return rank === 1 && this.pulseFirst;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
