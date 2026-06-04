import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import {
  GamificationService,
  LeaderboardData,
  MinProfilData,
  OverviewData,
} from '../gamification.service';

@Component({
  standalone: true,
  selector: 'app-gamification',
  templateUrl: './gamification.component.html',
  styleUrls: ['./gamification.component.css'],
  imports: [CommonModule],
})
export class GamificationPage implements OnInit, OnDestroy {

  // Aktiv flik
  activeTab: 'leaderboard' | 'min-profil' | 'vd-vy' = 'leaderboard';

  // Period
  period = 'vecka';
  readonly periodOptions = [
    { value: 'dag',   label: 'Idag' },
    { value: 'vecka', label: 'Vecka' },
    { value: 'manad', label: 'Månad' },
  ];

  // Loading
  loadingLeaderboard = false;
  loadingProfil = false;
  loadingOverview = false;

  // Error
  errorLeaderboard = false;
  errorProfil = false;
  errorOverview = false;

  // Data
  leaderboardData: LeaderboardData | null = null;
  profilData: MinProfilData | null = null;
  overviewData: OverviewData | null = null;

  // Sortering av leaderboard-tabell
  leaderboardSortCol: 'rank' | 'total_ibc' | 'kassations_rate' | 'antal_stopp' | 'total_poang' = 'total_poang';
  leaderboardSortDir: 'asc' | 'desc' = 'desc';

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: GamificationService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  setTab(tab: 'leaderboard' | 'min-profil' | 'vd-vy'): void {
    this.activeTab = tab;
    this.loadAll();
  }

  setPeriod(value: string): void {
    this.period = value;
    this.loadLeaderboard();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadLeaderboard();
    if (this.activeTab === 'min-profil') this.loadProfil();
    if (this.activeTab === 'vd-vy') this.loadOverview();
  }

  // ---- Helpers ----

  getInitials(namn: string): string {
    if (!namn) return '?';
    const parts = namn.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return namn.substring(0, 2).toUpperCase();
  }

  getMedalColor(rank: number): string {
    if (rank === 1) return '#FFD700';
    if (rank === 2) return '#C0C0C0';
    if (rank === 3) return '#CD7F32';
    return '#4a5568';
  }

  getMedalBg(rank: number): string {
    if (rank === 1) return 'rgba(255,215,0,0.15)';
    if (rank === 2) return 'rgba(192,192,192,0.15)';
    if (rank === 3) return 'rgba(205,127,50,0.15)';
    return 'rgba(74,85,104,0.15)';
  }

  getStreakIcon(streak: number): string {
    if (streak >= 7) return 'fas fa-fire';
    if (streak >= 3) return 'fas fa-bolt';
    if (streak >= 1) return 'fas fa-check';
    return '';
  }

  getStreakColor(streak: number): string {
    if (streak >= 7) return '#fc8181';
    if (streak >= 3) return '#ecc94b';
    if (streak >= 1) return '#68d391';
    return '#8fa3b8';
  }

  getRankSuffix(rank: number | null): string {
    if (!rank) return '-';
    return '#' + rank;
  }

  // ---- Data loading ----

  private loadLeaderboard(): void {
    this.loadingLeaderboard = true;
    this.errorLeaderboard = false;
    this.svc.getLeaderboard(this.period).pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loadingLeaderboard = false;
      this.isFetching = false;
      if (res?.success) {
        this.leaderboardData = res.data;
      } else {
        this.errorLeaderboard = true;
      }
    });
  }

  loadProfil(): void {
    this.loadingProfil = true;
    this.errorProfil = false;
    this.svc.getMinProfil().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loadingProfil = false;
      if (res?.success) {
        this.profilData = res.data;
      } else {
        this.errorProfil = true;
      }
    });
  }

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview().pipe(
      timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overviewData = res.data;
      } else {
        this.errorOverview = true;
      }
    });
  }
  formatKvalitet(kassationsRate: number | null | undefined): string {
    return (100 - (kassationsRate ?? 0)).toFixed(1);
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }

  // ---- Leaderboard sortering ----

  get sortedLeaderboard(): any[] {
    const rows = this.leaderboardData?.leaderboard ?? [];
    if (!rows.length) return rows;
    const col = this.leaderboardSortCol;
    const dir = this.leaderboardSortDir === 'desc' ? -1 : 1;
    return [...rows].sort((a, b) => {
      const aVal = col === 'kassations_rate'
        ? (100 - (a.kassations_rate ?? 0))
        : (a[col] ?? 0);
      const bVal = col === 'kassations_rate'
        ? (100 - (b.kassations_rate ?? 0))
        : (b[col] ?? 0);
      return dir * ((bVal as number) - (aVal as number));
    });
  }

  toggleLeaderboardSort(col: typeof this.leaderboardSortCol): void {
    if (this.leaderboardSortCol === col) {
      this.leaderboardSortDir = this.leaderboardSortDir === 'desc' ? 'asc' : 'desc';
    } else {
      this.leaderboardSortCol = col;
      // Kassation sorteras stigande (lägre = bättre), övriga fallande
      this.leaderboardSortDir = col === 'antal_stopp' ? 'asc' : 'desc';
    }
  }

  leaderboardSortIcon(col: typeof this.leaderboardSortCol): string {
    if (this.leaderboardSortCol !== col) return 'fas fa-sort text-muted ms-1';
    return this.leaderboardSortDir === 'desc' ? 'fas fa-sort-down ms-1' : 'fas fa-sort-up ms-1';
  }
}
