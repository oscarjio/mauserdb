import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, distinctUntilChanged, timeout, catchError } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { BonusService, WeeklyHistoryEntry } from '../../services/bonus.service';
import { localToday, localDateStr, parseLocalDate } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);


// ================================================================
// INTERFACE: Feedback
// ================================================================
export interface FeedbackItem {
  id: number;
  datum: string;
  stamning: number;
  kommentar: string | null;
}
// ================================================================
// INTERFACE: WorkDay (närvaro-kalender)
// ================================================================
export interface WorkDay {
  date: string;
  worked: boolean;
  ibc: number;
}

@Component({
  selector: 'app-my-bonus',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './my-bonus.html',
  styleUrls: ['./my-bonus.css']
})
export class MyBonusPage implements OnInit, OnDestroy {
  loggedIn = false;
  operatorId = '';
  savedOperatorId = '';
  loading = false;
  error = '';
  operatorIdFromAccount = false;

  stats: any = null;
  history: any[] = [];
  selectedPeriod = 'week';
  showFormula = false;

  Math = Math;
  today = new Date();
  private kpiChart: Chart | null = null;
  private historyChart: Chart | null = null;
  private ibcTrendChart: Chart | null = null;
  private weeklyChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  // Veckohistorik
  weeklyData: WeeklyHistoryEntry[] = [];
  weeklyAvg = 0;
  weeklyLoading = false;

  // Bonusprognos i kr
  bonusAmounts: { brons: number; silver: number; guld: number; platina: number } | null = null;
  bonusAmountsLoading = false;
  bonusAmountsConfigured = false;

  // Personliga rekord
  pbData: any = null;
  pbLoading = false;

  // Streak
  streakData: any = null;
  streakLoading = false;

  // Min placering (anonymiserad kollegajämförelse)
  myRanking: {
    rank: number;
    total_ops: number;
    ibc_per_h: number;
    quality_pct: number;
    diff_from_leader_pct: number;
    period_label: string;
    no_data?: boolean;
  } | null = null;
  rankingPeriod: 'day' | 'week' | 'month' = 'week';
  rankingLoading = false;

  // Anonym kollegjämförelse (ny sektion, veckobaserad via ranking-position endpoint)
  rankingPosition: {
    my_rank: number | null;
    total_operators: number;
    my_ibc_per_h: number | null;
    top_ibc_per_h: number | null;
    avg_ibc_per_h: number | null;
    percentile: number | null;
    trend: 'up' | 'down' | 'same';
    week_label: string;
  } | null = null;
  rankingPositionLoading = false;

  // Operatörsfeedback
  feedbackMood: number = 3;
  feedbackKommentar: string = '';
  feedbackLoading = false;
  feedbackSaved = false;
  feedbackError = '';
  feedbackHistory: FeedbackItem[] = [];
  feedbackHistoryLoading = false;

  // Achievement badges (backend-driven)
  achievementBadges: {
    badge_id: string;
    name: string;
    description: string;
    icon: string;
    earned: boolean;
    earned_date: string | null;
    progress: number;
  }[] = [];
  achievementsLoading = false;
  totalIbcLifetime = 0;
  achievementCurrentStreak = 0;
  showConfetti = false;
  private confettiTimerId: any = null;
  private feedbackSavedTimerId: any = null;
  private weeklyChartTimerId: any = null;

  // Cachade achievements (undviker funktionsanrop vid varje change detection)
  cachedAchievements: { icon: string; label: string; earned: boolean; desc: string }[] = [];
  cachedEarnedAchievementsCount = 0;

  // Cachade tunga beräkningar (undviker filter/reduce per change-detection-cykel)
  cachedTrendDirection: 'up' | 'down' | 'flat' = 'flat';
  cachedStatusBadge: { text: string; cssClass: string } = { text: 'Fortsätt kämpa!', cssClass: 'badge-below' };
  cachedShiftPrognosis: { bonusPoang: number; ibcPerHour: number; weeklyIbc: number } | null = null;
  cachedProjectedBonus: { weekly: number; monthly: number } | null = null;
  cachedMyAvgIbcPerHour = 0;
  cachedWeeklyTeamComparison: { ibcDiff: number; kvalitetDiff: number; bonusDiff: number } | null = null;
  cachedMyWeeklyAvgIbc = 0;
  cachedTeamWeeklyAvgIbc = 0;
  cachedMyWeeklyAvgKvalitet = 0;
  cachedTeamWeeklyAvgKvalitet = 0;
  cachedTeamWeeklyAvgBonus = 0;
  cachedAchievementBadgesEarned = 0;

  // Peer ranking (anonymiserad kollegajamforelse)
  peerRanking: {
    your_rank: number | null;
    total_operators: number;
    your_ibc_h: number | null;
    your_quality: number | null;
    peers: { rank: number; ibc_h: number; quality: number; is_you: boolean }[];
    week_label: string;
  } | null = null;
  peerRankingLoading = false;

  // Närvaro-kalender
  workCalendar: WorkDay[] = [];
  calendarLoading = false;
  readonly moodEmojis: Record<number, string> = { 1: '😟', 2: '😐', 3: '😊', 4: '🌟' };
  readonly moodLabels: Record<number, string> = { 1: 'Dålig', 2: 'Ok', 3: 'Bra', 4: 'Utmärkt' };

  constructor(private auth: AuthService, private bonusService: BonusService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
  }

  ngOnInit(): void {
    this.auth.user$.pipe(
      takeUntil(this.destroy$),
      distinctUntilChanged((a: any, b: any) => a?.operator_id === b?.operator_id)
    ).subscribe((user: any) => {
      if (user?.operator_id) {
        this.operatorId = String(user.operator_id);
        this.savedOperatorId = String(user.operator_id);
        this.operatorIdFromAccount = true;
        this.loadStats();
      } else {
        this.operatorIdFromAccount = false;
        const saved = localStorage.getItem('myOperatorId');
        if (saved) {
          this.operatorId = saved;
          this.savedOperatorId = saved;
          this.loadStats();
        }
      }
    });
    this.loadBonusAmounts();
    this.loadRankingPosition();
    this.loadFeedbackHistory();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.confettiTimerId);
    clearTimeout(this.feedbackSavedTimerId);
    clearTimeout(this.weeklyChartTimerId);
    try { if (this.kpiChart) this.kpiChart.destroy(); } catch (e) {}
    this.kpiChart = null;
    try { if (this.historyChart) this.historyChart.destroy(); } catch (e) {}
    this.historyChart = null;
    try { if (this.ibcTrendChart) this.ibcTrendChart.destroy(); } catch (e) {}
    this.ibcTrendChart = null;
    try { if (this.weeklyChart) this.weeklyChart.destroy(); } catch (e) {}
    this.weeklyChart = null;
  }

  saveAndLoad(): void {
    if (!this.operatorId.trim()) return;
    localStorage.setItem('myOperatorId', this.operatorId.trim());
    this.savedOperatorId = this.operatorId.trim();
    this.loadStats();
  }

  clearOperator(): void {
    localStorage.removeItem('myOperatorId');
    this.operatorId = '';
    this.savedOperatorId = '';
    this.stats = null;
    this.history = [];
    this.weeklyData = [];
    this.weeklyAvg = 0;
    this.pbData = null;
    this.streakData = null;
    this.myRanking = null;
    this.rankingPosition = null;
    this.peerRanking = null;
    this.achievementBadges = [];
    this.totalIbcLifetime = 0;
    this.achievementCurrentStreak = 0;
    try { if (this.kpiChart) this.kpiChart.destroy(); } catch (e) {}
    this.kpiChart = null;
    try { if (this.historyChart) this.historyChart.destroy(); } catch (e) {}
    this.historyChart = null;
    try { if (this.ibcTrendChart) this.ibcTrendChart.destroy(); } catch (e) {}
    this.ibcTrendChart = null;
    try { if (this.weeklyChart) this.weeklyChart.destroy(); } catch (e) {}
    this.weeklyChart = null;
  }

  changePeriod(period: string): void {
    this.selectedPeriod = period;
    this.loadStats();
  }

  loadStats(): void {
    if (!this.savedOperatorId) return;
    this.loading = true;
    this.error = '';

    this.bonusService.getOperatorStats(this.savedOperatorId, this.selectedPeriod).pipe(
      timeout(8000),
      catchError(() => of({ _networkError: true } as any)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?._networkError) {
          this.error = 'Kunde inte hämta data. Kontrollera anslutningen och försök igen.';
          this.stats = null;
        } else if (res?.success && res.data) {
          this.stats = res.data;
          this.buildKPIChart(res.data);
          this.refreshAchievementsCache();
          this.rebuildStatsCache();
        } else {
          this.error = (res as any)?.error || 'Ingen data hittades för detta operatörs-ID.';
          this.stats = null;
        }
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta data. Försök igen.';
        this.loading = false;
      }
    });

    // Historik: hämta 20 senaste skift för historikgraf + ibcTrend
    this.bonusService.getOperatorHistory(this.savedOperatorId, 20).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data) {
          this.history = res.data.history || [];
          this.buildHistoryChart(this.history);
          this.buildIbcTrendChart(this.history);
          this.buildWorkCalendar();
          this.rebuildStatsCache();
        }
      },
      error: () => {}
    });

    // Veckohistorik: senaste 8 ISO-veckor
    this.weeklyLoading = true;
    this.bonusService.getWeeklyHistory(this.savedOperatorId).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data) {
          this.weeklyData = res.data.weeks || [];
          this.weeklyAvg = res.data.my_avg ?? 0;
          this.rebuildWeeklyCache();
          // Bygg grafen när DOM är redo
          clearTimeout(this.weeklyChartTimerId);
          this.weeklyChartTimerId = setTimeout(() => {
            if (!this.destroy$.closed) this.buildWeeklyChart();
          }, 150);
        }
        this.weeklyLoading = false;
      },
      error: () => { this.weeklyLoading = false; }
    });

    // Personliga rekord
    this.loadPersonalBest();

    // Streak
    this.loadStreak();

    // Achievement badges
    this.loadAchievements();

    // Min placering (anonymiserad kollegajämförelse)
    this.loadMyRanking();

    // Peer ranking (anonymiserad veckoranking)
    this.loadPeerRanking();
  }

  loadPersonalBest(): void {
    if (!this.savedOperatorId) return;
    this.pbLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonus&run=personal-best&operator_id=${this.savedOperatorId}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.pbData = res;
          this.refreshAchievementsCache();
        } else {
          this.pbData = null;
        }
        this.pbLoading = false;
      },
      error: () => { this.pbLoading = false; }
    });
  }

  loadStreak(): void {
    if (!this.savedOperatorId) return;
    this.streakLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonus&run=streak&operator_id=${this.savedOperatorId}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.streakData = res;
          this.refreshAchievementsCache();
        } else {
          this.streakData = null;
        }
        this.streakLoading = false;
      },
      error: () => { this.streakLoading = false; }
    });
  }

  loadAchievements(): void {
    if (!this.savedOperatorId) return;
    this.achievementsLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonus&run=achievements&operator_id=${this.savedOperatorId}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.badges) {
          this.achievementBadges = res.badges;
          this.totalIbcLifetime = res.total_ibc_lifetime ?? 0;
          this.achievementCurrentStreak = res.current_streak ?? 0;
          // Konfetti om någon badge precis uppnåddes (inom session)
          const earnedCount = res.badges.filter((b: any) => b.earned).length;
          this.cachedAchievementBadgesEarned = earnedCount;
          if (earnedCount > 0) {
            this.showConfetti = true;
            clearTimeout(this.confettiTimerId);
            this.confettiTimerId = setTimeout(() => { if (!this.destroy$.closed) this.showConfetti = false; }, 4000);
          }
        }
        this.achievementsLoading = false;
      },
      error: () => { this.achievementsLoading = false; }
    });
  }

  getAchievementBadgesEarned(): number {
    return this.cachedAchievementBadgesEarned;
  }

  loadRankingPosition(): void {
    this.rankingPositionLoading = true;
    this.bonusService.getRankingPosition().pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        this.rankingPosition = res?.success ? res : null;
        this.rankingPositionLoading = false;
      },
      error: () => { this.rankingPositionLoading = false; }
    });
  }

  getRankingPositionProgressPct(): number {
    if (!this.rankingPosition?.my_ibc_per_h || !this.rankingPosition?.top_ibc_per_h) return 0;
    if (this.rankingPosition.top_ibc_per_h <= 0) return 0;
    return Math.min(Math.round((this.rankingPosition.my_ibc_per_h / this.rankingPosition.top_ibc_per_h) * 100), 100);
  }

  getRankingPositionMotivation(): string {
    if (!this.rankingPosition?.my_rank) return '';
    const rank = this.rankingPosition.my_rank;
    if (rank === 1) return 'Du leder! Fortsätt så!';
    if (rank <= 3) return 'Nära toppen!';
    return 'Känn motivationen växa!';
  }

  getRankingPositionMotivationClass(): string {
    if (!this.rankingPosition?.my_rank) return 'text-muted';
    const rank = this.rankingPosition.my_rank;
    if (rank === 1) return 'text-warning';
    if (rank <= 3) return 'text-info';
    return 'text-secondary';
  }

  loadMyRanking(): void {
    if (!this.savedOperatorId) return;
    this.rankingLoading = true;
    const period = this.rankingPeriod === 'day' ? 'day' : this.rankingPeriod;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonus&run=my-ranking&op_id=${this.savedOperatorId}&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data) {
          this.myRanking = res.data;
        } else {
          this.myRanking = null;
        }
        this.rankingLoading = false;
      },
      error: () => { this.rankingLoading = false; }
    });
  }

  changeRankingPeriod(period: 'day' | 'week' | 'month'): void {
    this.rankingPeriod = period;
    this.myRanking = null;
    this.loadMyRanking();
  }

  getRankingMotivationText(): string {
    if (!this.myRanking || this.myRanking.no_data) return '';
    const rank = this.myRanking.rank;
    const diff = this.myRanking.diff_from_leader_pct;
    if (rank === 1) return 'Du leder! Hall trycket!';
    if (Math.abs(diff) < 5) return 'Extremt tight! Allt avgors idag.';
    if (rank === 2) return 'Nastan! Du ar tatt bakom ledaren.';
    return 'Kampa pa! Du kan na toppen.';
  }

  getRankingMotivationClass(): string {
    if (!this.myRanking) return 'text-muted';
    const rank = this.myRanking.rank;
    const diff = this.myRanking.diff_from_leader_pct;
    if (rank === 1) return 'text-warning';
    if (Math.abs(diff) < 5) return 'text-danger';
    if (rank === 2) return 'text-info';
    return 'text-secondary';
  }

  getRankingProgressPct(): number {
    if (!this.myRanking || !this.myRanking.rank || !this.myRanking.total_ops) return 0;
    if (this.myRanking.rank === 1) return 100;
    // Progress: rank 1 = 100%, last rank = 10%
    const rank = this.myRanking.rank;
    const total = this.myRanking.total_ops;
    if (total <= 1) return 100;
    const pct = ((total - rank) / (total - 1)) * 90 + 10;
    return Math.round(pct);
  }

  // ===== Peer ranking (anonymiserad veckoranking) =====
  loadPeerRanking(): void {
    if (!this.savedOperatorId) return;
    this.peerRankingLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonus&run=peer-ranking&operator_id=${this.savedOperatorId}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.peerRanking = res;
        } else {
          this.peerRanking = null;
        }
        this.peerRankingLoading = false;
      },
      error: () => { this.peerRankingLoading = false; }
    });
  }

  getPeerRankBadgeClass(rank: number): string {
    if (rank === 1) return 'peer-badge-gold';
    if (rank === 2) return 'peer-badge-silver';
    if (rank === 3) return 'peer-badge-bronze';
    return '';
  }

  getPeerDiffFromAbove(): number | null {
    if (!this.peerRanking || !this.peerRanking.your_rank || this.peerRanking.your_rank <= 1) return null;
    const peers = this.peerRanking.peers;
    const myIdx = this.peerRanking.your_rank - 1;
    if (myIdx > 0 && myIdx < peers.length) {
      return Math.round((peers[myIdx - 1].ibc_h - peers[myIdx].ibc_h) * 10) / 10;
    }
    return null;
  }

  // ===== Achievement-medaljer =====
  refreshAchievementsCache(): void {
    this.cachedAchievements = this.getAchievements();
    this.cachedEarnedAchievementsCount = this.cachedAchievements.filter(a => a.earned).length;
  }

  /** Bygger om cachade värden som beror på stats/history */
  private rebuildStatsCache(): void {
    this.cachedTrendDirection = this._computeTrendDirection();
    this.cachedStatusBadge = this._computeStatusBadge();
    this.cachedShiftPrognosis = this._computeShiftPrognosis();
    this.cachedProjectedBonus = this._computeProjectedBonus();
    this.cachedMyAvgIbcPerHour = this._computeMyAvgIbcPerHour();
  }

  /** Bygger om cachade värden som beror på weeklyData */
  private rebuildWeeklyCache(): void {
    this.cachedWeeklyTeamComparison = this._computeWeeklyTeamComparison();
    this.cachedMyWeeklyAvgIbc = this._computeMyWeeklyAvgIbc();
    this.cachedTeamWeeklyAvgIbc = this._computeTeamWeeklyAvgIbc();
    this.cachedMyWeeklyAvgKvalitet = this._computeMyWeeklyAvgKvalitet();
    this.cachedTeamWeeklyAvgKvalitet = this._computeTeamWeeklyAvgKvalitet();
    this.cachedTeamWeeklyAvgBonus = this._computeTeamWeeklyAvgBonus();
  }

  getEarnedAchievementsCount(): number {
    return this.getAchievements().filter(a => a.earned).length;
  }

  getAchievements(): { icon: string; label: string; earned: boolean; desc: string }[] {
    return [
      {
        icon: 'trophy',
        label: 'Guldnivå',
        earned: (this.stats?.kpis?.bonus_avg ?? 0) >= 90,
        desc: 'Nått minst Guld-nivå i bonus (90+ poäng)'
      },
      {
        icon: 'bolt',
        label: 'Snabbaste',
        earned: (this.stats?.kpis?.produktivitet ?? 0) >= 45,
        desc: 'Snitt IBC/h på minst 45'
      },
      {
        icon: 'gem',
        label: 'Perfekt kvalitet',
        earned: (this.stats?.kpis?.kvalitet ?? 0) >= 99,
        desc: 'Kvalitet på minst 99%'
      },
      {
        icon: 'fire',
        label: 'Veckostreak',
        earned: (this.streakData?.current_streak ?? 0) >= 5,
        desc: 'Jobbat 5 dagar i rad'
      },
      {
        icon: 'star',
        label: 'Rekordstjärna',
        earned: (this.pbData?.best_ibc_per_h ?? 0) >= 50,
        desc: 'Personligt rekord IBC/h >= 50'
      },
      {
        icon: 'box-open',
        label: '100 IBC/skift',
        earned: (this.pbData?.best_skift_ibc ?? 0) >= 100,
        desc: 'Minst 100 IBC OK i ett skift'
      },
    ];
  }

  // ===== Motivational status badge =====
  getStatusBadge(): { text: string; cssClass: string } {
    return this.cachedStatusBadge;
  }

  private _computeStatusBadge(): { text: string; cssClass: string } {
    const bonus = this.stats?.kpis?.bonus_avg ?? 0;
    const trend = this.cachedTrendDirection;
    if (bonus >= 95) return { text: 'Rekordniva!', cssClass: 'badge-outstanding' };
    if (bonus >= 90 && trend === 'up') return { text: 'Uppat mot toppen!', cssClass: 'badge-excellent-up' };
    if (bonus >= 90) return { text: 'Utmarkt prestanda!', cssClass: 'badge-excellent' };
    if (bonus >= 80 && trend === 'up') return { text: 'Over genomsnitt!', cssClass: 'badge-good-up' };
    if (bonus >= 80) return { text: 'Over genomsnitt', cssClass: 'badge-good' };
    if (bonus >= 70) return { text: 'Pa ratt spår', cssClass: 'badge-base' };
    return { text: 'Fortsätt kämpa!', cssClass: 'badge-below' };
  }

  // Beräkna mitt IBC/h-snitt senaste 7 skiften
  getMyAvgIbcPerHour(): number {
    return this.cachedMyAvgIbcPerHour;
  }

  private _computeMyAvgIbcPerHour(): number {
    if (!this.history || this.history.length === 0) return 0;
    const recent = this.history.slice(0, 7);
    const withProd = recent.filter((h: any) => (h.kpis?.produktivitet ?? 0) > 0);
    if (withProd.length === 0) return 0;
    const sum = withProd.reduce((s: number, h: any) => s + (h.kpis?.produktivitet ?? 0), 0);
    return Math.round((sum / withProd.length) * 10) / 10;
  }

  // Prognos: antal poäng + IBC/h om fortsätter i detta tempo
  getShiftPrognosis(): { bonusPoang: number; ibcPerHour: number; weeklyIbc: number } | null {
    return this.cachedShiftPrognosis;
  }

  private _computeShiftPrognosis(): { bonusPoang: number; ibcPerHour: number; weeklyIbc: number } | null {
    if (!this.history || this.history.length < 3) return null;
    const recent = this.history.slice(0, 7);
    const avgBonus = recent.reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / recent.length;
    const avgProd = recent.reduce((s: number, h: any) => s + (h.kpis?.produktivitet ?? 0), 0) / recent.length;
    const avgIbcPerShift = recent.reduce((s: number, h: any) => s + (h.ibc_ok ?? 0), 0) / recent.length;

    return {
      bonusPoang: Math.round(avgBonus * 10) / 10,
      ibcPerHour: Math.round(avgProd * 10) / 10,
      weeklyIbc: Math.round(avgIbcPerShift * 5)  // 5 skift/vecka
    };
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 90) return 'text-success';
    if (bonus >= 70) return 'text-info';
    if (bonus >= 50) return 'text-warning';
    return 'text-danger';
  }

  getBonusTier(bonus: number): string {
    if (bonus >= 95) return 'Outstanding (x2.0)';
    if (bonus >= 90) return 'Excellent (x1.5)';
    if (bonus >= 80) return 'God prestanda (x1.25)';
    if (bonus >= 70) return 'Basbonus (x1.0)';
    return 'Under förväntan (x0.75)';
  }

  getProductName(id: number): string {
    switch (id) {
      case 1: return 'FoodGrade';
      case 4: return 'NonUN';
      case 5: return 'Tvättade';
      default: return 'Produkt ' + id;
    }
  }

  getNextTierInfo(bonus: number): { name: string; pointsNeeded: number } | null {
    const tiers = [
      { name: 'Basbonus (x1.0)', threshold: 70 },
      { name: 'God prestanda (x1.25)', threshold: 80 },
      { name: 'Excellent (x1.5)', threshold: 90 },
      { name: 'Outstanding (x2.0)', threshold: 95 }
    ];
    for (const tier of tiers) {
      if (bonus < tier.threshold) {
        return { name: tier.name, pointsNeeded: Math.round((tier.threshold - bonus) * 10) / 10 };
      }
    }
    return null;
  }

  getProjectedBonus(): { weekly: number; monthly: number } | null {
    return this.cachedProjectedBonus;
  }

  private _computeProjectedBonus(): { weekly: number; monthly: number } | null {
    if (!this.history || this.history.length < 3) return null;
    const recent = this.history.slice(0, 7);
    const avg = recent.reduce((sum: number, h: any) => sum + (h.kpis?.bonus ?? 0), 0) / recent.length;
    return {
      weekly: Math.round(avg * 10) / 10,
      monthly: Math.round(avg * 4 * 10) / 10
    };
  }

  getTrendDirection(): 'up' | 'down' | 'flat' {
    return this.cachedTrendDirection;
  }

  private _computeTrendDirection(): 'up' | 'down' | 'flat' {
    if (!this.history || this.history.length < 6) return 'flat';
    const recent3 = this.history.slice(0, 3).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const prev3 = this.history.slice(3, 6).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const diff = recent3 - prev3;
    if (diff > 2) return 'up';
    if (diff < -2) return 'down';
    return 'flat';
  }

  loadBonusAmounts(): void {
    this.bonusAmountsLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=bonusadmin&run=getAmounts`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data?.amounts) {
          const a = res.data.amounts;
          if ((a.brons || 0) > 0 || (a.silver || 0) > 0 || (a.guld || 0) > 0 || (a.platina || 0) > 0) {
            this.bonusAmounts = {
              brons:   a.brons   ?? 0,
              silver:  a.silver  ?? 0,
              guld:    a.guld    ?? 0,
              platina: a.platina ?? 0
            };
            this.bonusAmountsConfigured = true;
          } else {
            this.bonusAmountsConfigured = false;
          }
        } else {
          this.bonusAmountsConfigured = false;
        }
        this.bonusAmountsLoading = false;
      },
      error: () => {
        this.bonusAmountsConfigured = false;
        this.bonusAmountsLoading = false;
      }
    });
  }

  getBonusLevelKey(bonus: number): 'brons' | 'silver' | 'guld' | 'platina' | null {
    if (bonus >= 95) return 'platina';
    if (bonus >= 90) return 'guld';
    if (bonus >= 80) return 'silver';
    if (bonus >= 70) return 'brons';
    return null;
  }

  getNextLevelKey(current: 'brons' | 'silver' | 'guld' | 'platina' | null): 'silver' | 'guld' | 'platina' | null {
    switch (current) {
      case 'brons':   return 'silver';
      case 'silver':  return 'guld';
      case 'guld':    return 'platina';
      default:        return null;
    }
  }

  getLevelDisplayName(key: string | null): string {
    switch (key) {
      case 'brons':   return 'Brons';
      case 'silver':  return 'Silver';
      case 'guld':    return 'Guld';
      case 'platina': return 'Platina';
      default:        return 'Ingen nivå';
    }
  }

  getLevelIconStyle(key: string | null): string {
    switch (key) {
      case 'brons':   return 'color:#cd7f32';
      case 'silver':  return 'color:#a8a9ad';
      case 'guld':    return 'color:#ffd700';
      case 'platina': return 'color:#e5e4e2';
      default:        return 'color:#718096';
    }
  }

  getLevelProgress(bonus: number): number {
    const tiers = [0, 70, 80, 90, 95, 120];
    for (let i = 0; i < tiers.length - 1; i++) {
      if (bonus >= tiers[i] && bonus < tiers[i + 1]) {
        const pct = ((bonus - tiers[i]) / (tiers[i + 1] - tiers[i])) * 100;
        return Math.min(Math.round(pct), 100);
      }
    }
    return 100;
  }

  exportBonusCSV(): void {
    if (!this.stats?.daily_breakdown?.length) return;
    const header = ['Datum', 'Cykler', 'IBC OK', 'IBC Ej OK', 'Effektivitet', 'Produktivitet', 'Kvalitet', 'Bonus'];
    const rows = this.stats.daily_breakdown.map((d: any) => [
      d.date, d.cycles, d.ibc_ok, d.ibc_ej_ok,
      (d.effektivitet ?? 0).toFixed(1) + '%',
      (d.produktivitet ?? 0).toFixed(1),
      (d.kvalitet ?? 0).toFixed(1) + '%',
      (d.bonus_poang ?? 0).toFixed(1)
    ]);
    const csv = [header, ...rows].map(r => r.map((c: any) => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `min-bonus-${this.savedOperatorId}-${this.selectedPeriod}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportBonusPDF(): void {
    if (!this.stats) return;
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const s = this.stats;
        const opName = s.operator_name || ('Operatör ' + this.savedOperatorId);
        const trend = this.getTrendDirection();
        const trendText = trend === 'up' ? '↑ Uppåtgående' : trend === 'down' ? '↓ Nedåtgående' : '→ Stabil';
        const projected = this.getProjectedBonus();
        const prognosis = this.getShiftPrognosis();
        const breakdownRows = (s.daily_breakdown || []).slice(0, 20).map((d: any) => [
          d.date || '',
          String(d.cycles || 0),
          (d.effektivitet ?? 0).toFixed(1) + '%',
          (d.produktivitet ?? 0).toFixed(1),
          (d.kvalitet ?? 0).toFixed(1) + '%',
          { text: (d.bonus_poang ?? 0).toFixed(1), bold: true }
        ]);
        pdfMake.createPdf({
          content: [
            { text: 'Bonusrapport', style: 'header' },
            { text: opName + '  |  ' + this.getPositionName(s.position) + '  |  Period: ' + s.period, style: 'subheader' },
            { text: ' ' },
            { text: 'Sammanfattning', style: 'sectionHeader' },
            {
              table: { widths: ['*', '*', '*', '*'],
                body: [
                  [{ text: 'Snittbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Maxbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Minbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Trend', bold: true, fillColor: '#eeeeee' }],
                  [
                    { text: (s.kpis?.bonus_avg ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.bonus_max ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.bonus_min ?? 0).toFixed(1), alignment: 'center' },
                    { text: trendText, alignment: 'center' }
                  ]
                ]
              }, layout: 'lightHorizontalLines'
            },
            ...(prognosis ? [
              { text: ' ' },
              { text: 'Skiftprognos (om du fortsätter i detta tempo)', style: 'sectionHeader' },
              {
                table: { widths: ['*', '*', '*'],
                  body: [
                    [{ text: 'Förv. bonus', bold: true, fillColor: '#eeeeee' }, { text: 'IBC/h', bold: true, fillColor: '#eeeeee' }, { text: 'IBC/vecka (5 skift)', bold: true, fillColor: '#eeeeee' }],
                    [
                      { text: prognosis.bonusPoang.toFixed(1) + ' p', alignment: 'center' },
                      { text: prognosis.ibcPerHour.toFixed(1), alignment: 'center' },
                      { text: String(prognosis.weeklyIbc), alignment: 'center' }
                    ]
                  ]
                }, layout: 'lightHorizontalLines'
              }
            ] : []),
            { text: ' ' },
            { text: 'KPI:er', style: 'sectionHeader' },
            {
              table: { widths: ['*', '*', '*'],
                body: [
                  [{ text: 'Effektivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Produktivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }],
                  [
                    { text: (s.kpis?.effektivitet ?? 0).toFixed(1) + '%', alignment: 'center' },
                    { text: (s.kpis?.produktivitet ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.kvalitet ?? 0).toFixed(1) + '%', alignment: 'center' }
                  ]
                ]
              }, layout: 'lightHorizontalLines'
            },
            ...(breakdownRows.length > 0 ? [
              { text: ' ' },
              { text: 'Daglig uppdelning (senaste ' + breakdownRows.length + ' skift)', style: 'sectionHeader' },
              {
                table: {
                  widths: ['*', 'auto', 'auto', 'auto', 'auto', 'auto'],
                  body: [
                    [{ text: 'Datum', bold: true, fillColor: '#eeeeee' }, { text: 'Cykler', bold: true, fillColor: '#eeeeee' }, { text: 'Effektivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Produktivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }, { text: 'Bonus', bold: true, fillColor: '#eeeeee' }],
                    ...breakdownRows
                  ]
                }, layout: 'lightHorizontalLines'
              }
            ] : []),
            { text: ' ' },
            { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
          ],
          styles: {
            header: { fontSize: 20, bold: true, margin: [0, 0, 0, 4] },
            subheader: { fontSize: 12, color: '#555', margin: [0, 0, 0, 10] },
            sectionHeader: { fontSize: 13, bold: true, margin: [0, 8, 0, 4] },
            meta: { fontSize: 10, color: '#777', margin: [0, 2, 0, 0] }
          },
          defaultStyle: { fontSize: 11 }
        }).download(`bonusrapport-${this.savedOperatorId}-${this.selectedPeriod}.pdf`);
      });
    });
  }

  exportShiftHistoryCSV(): void {
    if (!this.history?.length) return;
    const headers = ["Datum", "IBC OK", "IBC Ej OK", "Effektivitet (%)", "IBC/h", "Kvalitet (%)", "Bonus (p)"];
    const rows = this.history.map((h: any) => [
      h.datum ?? "",
      String(h.ibc_ok ?? 0),
      String(h.ibc_ej_ok ?? 0),
      (h.kpis?.effektivitet ?? 0).toFixed(1),
      (h.kpis?.produktivitet ?? 0).toFixed(1),
      (h.kpis?.kvalitet ?? 0).toFixed(1),
      (h.kpis?.bonus ?? 0).toFixed(1)
    ]);
    const csv = [headers, ...rows].map(r => r.map((c: string) => `"${c}"`).join(";")).join("\n");
    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `skifthistorik-${this.savedOperatorId}-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportShiftHistoryPDF(): void {
    window.print();
  }

  getPositionName(pos: string): string {
    switch (pos) {
      case 'position_1': return 'Tvättplats';
      case 'position_2': return 'Kontrollstation';
      case 'position_3': return 'Truckförare';
      default: return pos;
    }
  }

  // ===== Veckohistorik hjälpfunktioner =====

  /** Jämförelserad: diff i % och riktning mot lagsnitt */
  getWeeklyTeamComparison(): { ibcDiff: number; kvalitetDiff: number; bonusDiff: number } | null {
    return this.cachedWeeklyTeamComparison;
  }

  private _computeWeeklyTeamComparison(): { ibcDiff: number; kvalitetDiff: number; bonusDiff: number } | null {
    if (!this.weeklyData || this.weeklyData.length === 0) return null;
    const n = this.weeklyData.length;
    const myIbc     = this.weeklyData.reduce((s, w) => s + w.my_ibc_per_hour, 0) / n;
    const teamIbc   = this.weeklyData.reduce((s, w) => s + w.team_ibc_per_hour, 0) / n;
    const myKval    = this.weeklyData.reduce((s, w) => s + w.my_kvalitet, 0) / n;
    const teamKval  = this.weeklyData.reduce((s, w) => s + w.team_kvalitet, 0) / n;
    const myBonus   = this.weeklyData.reduce((s, w) => s + w.my_bonus, 0) / n;
    const teamBonus = this.weeklyData.reduce((s, w) => s + w.team_bonus, 0) / n;
    return {
      ibcDiff:     Math.round((myIbc   - teamIbc)   * 10) / 10,
      kvalitetDiff: Math.round((myKval  - teamKval)  * 10) / 10,
      bonusDiff:   Math.round((myBonus - teamBonus) * 10) / 10,
    };
  }

  getMyWeeklyAvgIbc(): number {
    return this.cachedMyWeeklyAvgIbc;
  }

  private _computeMyWeeklyAvgIbc(): number {
    if (!this.weeklyData.length) return 0;
    return Math.round(this.weeklyData.reduce((s, w) => s + w.my_ibc_per_hour, 0) / this.weeklyData.length * 10) / 10;
  }

  getTeamWeeklyAvgIbc(): number {
    return this.cachedTeamWeeklyAvgIbc;
  }

  private _computeTeamWeeklyAvgIbc(): number {
    if (!this.weeklyData.length) return 0;
    return Math.round(this.weeklyData.reduce((s, w) => s + w.team_ibc_per_hour, 0) / this.weeklyData.length * 10) / 10;
  }

  getMyWeeklyAvgKvalitet(): number {
    return this.cachedMyWeeklyAvgKvalitet;
  }

  private _computeMyWeeklyAvgKvalitet(): number {
    if (!this.weeklyData.length) return 0;
    return Math.round(this.weeklyData.reduce((s, w) => s + w.my_kvalitet, 0) / this.weeklyData.length * 10) / 10;
  }

  getTeamWeeklyAvgKvalitet(): number {
    return this.cachedTeamWeeklyAvgKvalitet;
  }

  private _computeTeamWeeklyAvgKvalitet(): number {
    if (!this.weeklyData.length) return 0;
    return Math.round(this.weeklyData.reduce((s, w) => s + w.team_kvalitet, 0) / this.weeklyData.length * 10) / 10;
  }

  getTeamWeeklyAvgBonus(): number {
    return this.cachedTeamWeeklyAvgBonus;
  }

  private _computeTeamWeeklyAvgBonus(): number {
    if (!this.weeklyData.length) return 0;
    return Math.round(this.weeklyData.reduce((s, w) => s + w.team_bonus, 0) / this.weeklyData.length * 10) / 10;
  }

  /** Stapeldiagram: bonuspoäng per vecka med referenslinje och färgkodning */
  private buildWeeklyChart(): void {
    try { this.weeklyChart?.destroy(); } catch (e) {}

    const canvas = document.getElementById('myWeeklyChart') as HTMLCanvasElement;
    if (!canvas || this.weeklyData.length === 0) return;

    const labels = this.weeklyData.map(w => w.label);
    const bonusValues = this.weeklyData.map(w => w.my_bonus);
    const avg = this.weeklyAvg;

    // Färgkodning: över snitt = grön, under = röd/orange
    const barColors = bonusValues.map(v =>
      v >= avg ? 'rgba(72, 187, 120, 0.75)' : 'rgba(229, 100, 62, 0.75)'
    );
    const barBorders = bonusValues.map(v =>
      v >= avg ? 'rgb(72, 187, 120)' : 'rgb(229, 100, 62)'
    );

    if (this.weeklyChart) { (this.weeklyChart as any).destroy(); }
    this.weeklyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Bonuspoäng',
            data: bonusValues,
            backgroundColor: barColors,
            borderColor: barBorders,
            borderWidth: 1,
            borderRadius: 5,
            order: 2
          },
          {
            // Referenslinje: streckad horisontell linje på genomsnittet
            label: 'Mitt snitt',
            data: bonusValues.map(() => avg),
            type: 'line',
            borderColor: '#f6e05e',
            backgroundColor: 'transparent',
            borderDash: [6, 4],
            borderWidth: 2,
            pointRadius: 0,
            tension: 0,
            order: 1
          } as any
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: '#a0aec0',
              font: { size: 11 },
              boxWidth: 14
            }
          },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            callbacks: {
              afterBody: (ctx: any[]) => {
                const idx = ctx[0]?.dataIndex;
                if (idx == null) return [];
                const w = this.weeklyData[idx];
                if (!w) return [];
                const diff = Math.round((w.my_bonus - avg) * 10) / 10;
                const sign = diff >= 0 ? '+' : '';
                return [`Vs snitt: ${sign}${diff} p`, `Skift: ${w.shifts}`];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: '#2d3748' }
          },
          y: {
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            beginAtZero: false,
            title: { display: true, text: 'Bonuspoäng', color: '#718096', font: { size: 11 } }
          }
        }
      }
    });
  }

  private buildKPIChart(data: any): void {
    try { this.kpiChart?.destroy(); } catch (e) {}

    const canvas = document.getElementById('myKpiChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.kpiChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
        datasets: [{
          label: 'Dina KPI:er',
          data: [
            data.kpis?.effektivitet ?? 0,
            data.kpis?.produktivitet ?? 0,
            data.kpis?.kvalitet ?? 0
          ],
          borderColor: '#38b2ac',
          backgroundColor: 'rgba(56, 178, 172, 0.2)',
          pointBackgroundColor: '#38b2ac'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            beginAtZero: true,
            max: 120,
            ticks: { color: '#a0aec0', backdropColor: 'transparent' },
            grid: { color: '#4a5568' },
            angleLines: { color: '#4a5568' },
            pointLabels: { color: '#e2e8f0', font: { size: 12 } }
          }
        },
        plugins: {
          legend: { labels: { color: '#a0aec0' } }
        }
      }
    });
  }

  private buildHistoryChart(history: any[]): void {
    try { this.historyChart?.destroy(); } catch (e) {}

    const canvas = document.getElementById('myHistoryChart') as HTMLCanvasElement;
    if (!canvas || history.length === 0) return;

    const recent = history.slice(0, 15).reverse();
    const labels = recent.map((h: any) => h.datum?.substring(5) || '');
    const bonusData = recent.map((h: any) => h.kpis?.bonus ?? 0);

    if (this.historyChart) { (this.historyChart as any).destroy(); }
    this.historyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Bonus',
          data: bonusData,
          backgroundColor: bonusData.map((b: number) =>
            b >= 90 ? 'rgba(72, 187, 120, 0.7)' :
            b >= 70 ? 'rgba(56, 178, 172, 0.7)' :
            b >= 50 ? 'rgba(236, 201, 75, 0.7)' :
            'rgba(229, 62, 62, 0.7)'
          ),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, min: 0, max: 200 }
        }
      }
    });
  }

  /** IBC/h de senaste 7 skiften vs ett glidande snitt */
  private buildIbcTrendChart(history: any[]): void {
    try { this.ibcTrendChart?.destroy(); } catch (e) {}

    const canvas = document.getElementById('myIbcTrendChart') as HTMLCanvasElement;
    if (!canvas || history.length < 2) return;

    const recent = history.slice(0, 7).reverse();
    const labels = recent.map((h: any) => h.datum?.substring(5, 10) || '');
    const ibcData = recent.map((h: any) => +(h.kpis?.produktivitet ?? 0).toFixed(1));

    // Beräkna rullande medelvärde (3 punkter)
    const avgData = ibcData.map((_: number, i: number) => {
      const window = ibcData.slice(Math.max(0, i - 2), i + 1);
      return +(window.reduce((a: number, b: number) => a + b, 0) / window.length).toFixed(1);
    });

    if (this.ibcTrendChart) { (this.ibcTrendChart as any).destroy(); }
    this.ibcTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h per skift',
            data: ibcData,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 5,
            pointBackgroundColor: '#4299e1'
          },
          {
            label: 'Glidande snitt (3)',
            data: avgData,
            borderColor: '#f6e05e',
            backgroundColor: 'transparent',
            borderDash: [5, 3],
            tension: 0.3,
            pointRadius: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'IBC/h', color: '#718096' },
            beginAtZero: false
          }
        }
      }
    });
  }

  // ================================================================
  // NÄRVARO-KALENDER
  // ================================================================
  buildWorkCalendar(): void {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const workedDates = new Set<string>(
      (this.history || []).map((s: any) => s.datum || '').filter(Boolean)
    );
    this.workCalendar = Array.from({ length: daysInMonth }, (_, i) => {
      const d = new Date(year, month, i + 1);
      const dateStr = localDateStr(d);
      const shift = (this.history || []).find((s: any) => s.datum === dateStr);
      return { date: dateStr, worked: workedDates.has(dateStr), ibc: shift?.ibc_ok || 0 };
    });
  }

  get calendarLeadingDays(): number[] {
    if (this.workCalendar.length === 0) return [];
    const firstDay = parseLocalDate(this.workCalendar[0].date).getDay();
    // Måndag=1, Söndag=0 → konvertera till Måndag-start
    const offset = firstDay === 0 ? 6 : firstDay - 1;
    return Array(offset).fill(0);
  }

  isToday(dateStr: string): boolean {
    return dateStr === localToday();
  }

  getWorkedDaysThisMonth(): number {
    return (this.workCalendar || []).filter(d => d.worked).length;
  }

  getCalendarMonthLabel(): string {
    if (this.workCalendar.length === 0) return '';
    const d = parseLocalDate(this.workCalendar[0].date);
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'long' });
  }

  get currentStreak(): number {
    if (!this.workCalendar || this.workCalendar.length === 0) return 0;
    let streak = 0;
    const todayStr = localToday();
    // Ta bara dagar upp till och med idag
    const past = [...this.workCalendar].filter(d => d.date <= todayStr).reverse();
    for (const day of past) {
      if (day.worked) streak++;
      else if (streak > 0) break;
    }
    return streak;
  }

  // ================================================================
  // FEEDBACK — ladda historik
  // ================================================================
  loadFeedbackHistory(): void {
    this.feedbackHistoryLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=feedback&run=my-history`, { withCredentials: true }).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        const raw = res?.success ? (res.items || []) : [];
        this.feedbackHistory = raw.map((i: any) => ({ ...i, stamning: i.stämning ?? i.stamning }));
        this.feedbackHistoryLoading = false;
      },
      error: () => { this.feedbackHistoryLoading = false; }
    });
  }

  // ================================================================
  // FEEDBACK — skicka feedback
  // ================================================================
  submitFeedback(): void {
    if (this.feedbackLoading) return;
    this.feedbackLoading = true;
    this.feedbackError = '';
    this.feedbackSaved = false;

    const body = { stamning: this.feedbackMood, kommentar: this.feedbackKommentar.trim() };

    this.http.post<any>(`${environment.apiUrl}?action=feedback&run=submit`, body, { withCredentials: true }).pipe(
      timeout(8000),
      catchError((err) => {
        const msg = err?.error?.error || 'Fel vid sparning av feedback';
        return of({ success: false, error: msg });
      }),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        this.feedbackLoading = false;
        if (res?.success) {
          this.feedbackSaved = true;
          this.feedbackKommentar = '';
          this.loadFeedbackHistory();
          clearTimeout(this.feedbackSavedTimerId);
          this.feedbackSavedTimerId = setTimeout(() => { if (!this.destroy$.closed) this.feedbackSaved = false; }, 3000);
        } else {
          this.feedbackError = res?.error || 'Kunde inte spara feedback.';
        }
      },
      error: () => {
        this.feedbackLoading = false;
        this.feedbackError = 'Nätverksfel — försök igen.';
      }
    });
  }

  // ======== trackBy-funktioner ========

  trackByIndex(index: number, item: any): any {
    return item?.id ?? index;
  }

  trackByBadgeId(index: number, badge: { badge_id: string }): string {
    return badge.badge_id;
  }

  trackByAchLabel(index: number, ach: { label: string }): string {
    return ach.label;
  }

  trackByDate(index: number, day: { date: string }): string {
    return day.date;
  }
}
