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
    { value: 'manad', label: 'Manad' },
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

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

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
    return '#718096';
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
}
