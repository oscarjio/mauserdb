import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { environment } from '../../../environments/environment';

interface RankingEntry {
  op_number: number;
  name: string;
  ibc_ok: number;
  ibc_per_hour: number | null;
  quality_pct: number | null;
  shifts_today: number;
}

interface LiveRankingResponse {
  success: boolean;
  ranking: RankingEntry[];
  date: string;
  period: string;
  goal: number;
  ibc_idag_total?: number;
  rekord_ibc?: number;
  rekord_datum?: string | null;
  error?: string;
}

// Roterande meddelanden per prestation-nivå
const MOTTOS_OVER100: string[] = [
  'Fantastisk prestation idag!',
  'Rekordnivå — ni är oslagbara!',
  'Hela laget levererar maximalt!',
  'Suveränt — fortsätt rulla!',
  'Ni sätter standarden — bra jobbat!',
];

const MOTTOS_OVER80: string[] = [
  'Fortsätt så! Ni är på rätt spår.',
  'Bra tempo — målet är inom räckhåll!',
  'Teamet levererar — ge allt nu!',
  'Starkt skift — håll farten!',
  'Ni klarar det — kör på!',
];

const MOTTOS_UNDER80: string[] = [
  'Ni klarar det — justera takten lite!',
  'Varje IBC räknas — kör hårt!',
  'Fokus ger resultat — ge allt!',
  'Tillsammans når vi målet!',
  'Ännu ett IBC — bra jobbat!',
];

@Component({
  selector: 'app-live-ranking',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './live-ranking.html',
  styleUrls: ['./live-ranking.css']
})
export class LiveRankingPage implements OnInit, OnDestroy {
  Math = Math;

  ranking: RankingEntry[] = [];
  date: string = '';
  period: string = '';
  goal: number = 0;
  loading = true;
  lastRefresh: Date = new Date();
  isFetching = false;
  error: string | null = null;

  // Teamtotal / rekord
  ibcIdagTotal: number = 0;
  rekordIbc: number = 0;
  rekordDatum: string | null = null;

  // Motivation
  mottoIndex = 0;
  currentMottos: string[] = MOTTOS_UNDER80;

  // Skiftnedräkning
  skiftSlutOm: string = '';

  // Prognos
  prognos: number | null = null;
  visaPrognos: boolean = false;

  // Live Ranking Config (KPI-kolumner, sortering, refresh)
  lrConfig = {
    columns: {
      ibc_per_hour: true,
      quality_pct: true,
      bonus_level: false,
      goal_progress: true,
      ibc_today: true
    },
    sort_by: 'ibc_per_hour',
    refresh_interval: 30
  };

  private readonly apiUrl = `${environment.apiUrl}?action=rebotling&run=live-ranking`;

  // Live Ranking-inställningar (från rebotling_settings)
  lrSettings: {
    lr_show_quality:  boolean;
    lr_show_progress: boolean;
    lr_show_motto:    boolean;
    lr_poll_interval: number;
    lr_title:         string;
  } = {
    lr_show_quality:  true,
    lr_show_progress: true,
    lr_show_motto:    true,
    lr_poll_interval: 30,
    lr_title:         'Live Ranking'
  };

  private destroy$       = new Subject<void>();
  private pollTimer:       any = null;
  private countdownTimer:  any = null;
  private motivationTimer: any = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadLrSettings();
    this.loadLrConfig();
    this.loadData();
    this.pollTimer = setInterval(() => this.loadData(), 30000);

    // Skiftnedräkning uppdateras varje minut
    this.updateCountdown();
    this.countdownTimer = setInterval(() => {
      this.updateCountdown();
      this.updatePrognos();
    }, 60000);

    // Rotera motivationsmeddelanden var 10s
    this.motivationTimer = setInterval(() => {
      this.mottoIndex = (this.mottoIndex + 1) % this.currentMottos.length;
    }, 10000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    clearInterval(this.pollTimer);
    clearInterval(this.countdownTimer);
    clearInterval(this.motivationTimer);
  }

  loadLrSettings(): void {
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=live-ranking-settings`, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) {
          this.lrSettings = res.data;
          // Uppdatera poll-interval om det skiljer sig från standardvärdet 30s
          const newInterval = (this.lrSettings.lr_poll_interval || 30) * 1000;
          if (newInterval !== 30000 && this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => this.loadData(), newInterval);
          }
        }
      });
  }

  loadLrConfig(): void {
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=live-ranking-config`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) {
          this.lrConfig = res.data;
          // Uppdatera poll-interval baserat på config
          const cfgInterval = (this.lrConfig.refresh_interval || 30) * 1000;
          if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => this.loadData(), cfgInterval);
          }
        }
      });
  }

  sortRanking(): void {
    if (!this.ranking || this.ranking.length === 0) return;
    const sortBy = this.lrConfig.sort_by;
    this.ranking.sort((a, b) => {
      if (sortBy === 'quality_pct') {
        return (b.quality_pct ?? 0) - (a.quality_pct ?? 0);
      }
      if (sortBy === 'ibc_today') {
        return (b.ibc_ok ?? 0) - (a.ibc_ok ?? 0);
      }
      // default: ibc_per_hour
      return (b.ibc_per_hour ?? 0) - (a.ibc_per_hour ?? 0);
    });
  }

  loadData(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.http.get<LiveRankingResponse>(this.apiUrl, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null as unknown as LiveRankingResponse)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res && res.success) {
            this.ranking        = res.ranking       ?? [];
            this.date           = res.date          ?? '';
            this.period         = res.period        ?? '';
            this.goal           = res.goal          ?? 0;
            this.ibcIdagTotal   = res.ibc_idag_total ?? 0;
            this.rekordIbc      = res.rekord_ibc    ?? 0;
            this.rekordDatum    = res.rekord_datum   ?? null;
            this.lastRefresh    = new Date();
            this.error          = null;
            this.sortRanking();
            this.updateCurrentMottos();
            this.updatePrognos();
          } else if (res && !res.success) {
            this.error = res.error ?? 'Kunde inte hämta data';
          }
          this.loading    = false;
          this.isFetching = false;
        },
        error: () => {
          this.loading    = false;
          this.isFetching = false;
          this.error = 'Anslutningsfel — försöker igen om 30 s';
        }
      });
  }

  // ---- Rekordindikator ----

  get rekordStatus(): 'rekord' | 'nara' | 'bra' | 'normal' {
    if (!this.rekordIbc || this.ibcIdagTotal <= 0) return 'normal';
    if (this.ibcIdagTotal >= this.rekordIbc)            return 'rekord';
    if (this.ibcIdagTotal >= this.rekordIbc * 0.9)      return 'nara';
    if (this.ibcIdagTotal >= this.rekordIbc * 0.8)      return 'bra';
    return 'normal';
  }

  // ---- Teamtotal progress ----

  get teamPct(): number {
    if (!this.goal || this.ibcIdagTotal <= 0) return 0;
    return Math.min(Math.round((this.ibcIdagTotal / this.goal) * 100), 100);
  }

  get teamProgressClass(): string {
    const p = this.teamPct;
    if (p >= 100) return 'bar-gold';
    if (p >= 80)  return 'bar-green';
    if (p >= 50)  return 'bar-yellow';
    return 'bar-red';
  }

  // ---- Skiftprognos ----

  updatePrognos(): void {
    const nu        = new Date();
    const skiftStart = new Date(); skiftStart.setHours(6, 0, 0, 0);
    const skiftSlut  = new Date(); skiftSlut.setHours(22, 0, 0, 0);
    const gångenH   = (nu.getTime() - skiftStart.getTime()) / 3600000;
    const kvarH     = Math.max((skiftSlut.getTime() - nu.getTime()) / 3600000, 0);
    const idag      = this.ibcIdagTotal;

    if (gångenH < 1) {
      this.visaPrognos = false;
      this.prognos     = null;
      return;
    }

    const takt      = idag / Math.max(gångenH, 0.1);
    this.prognos    = Math.round(idag + takt * kvarH);
    this.visaPrognos = kvarH > 0;
  }

  // ---- Skiftnedräkning ----

  updateCountdown(): void {
    const nu       = new Date();
    const skiftSlut = new Date(); skiftSlut.setHours(22, 0, 0, 0);
    const diffMs   = skiftSlut.getTime() - nu.getTime();
    if (diffMs <= 0) {
      this.skiftSlutOm = 'Skiftet är slut';
      return;
    }
    const totMin  = Math.floor(diffMs / 60000);
    const hh      = Math.floor(totMin / 60).toString().padStart(2, '0');
    const mm      = (totMin % 60).toString().padStart(2, '0');
    this.skiftSlutOm = `${hh}:${mm}`;
  }

  // ---- Roterande motivation ----

  updateCurrentMottos(): void {
    const pct = this.teamPct;
    if (pct >= 100)     this.currentMottos = MOTTOS_OVER100;
    else if (pct >= 80) this.currentMottos = MOTTOS_OVER80;
    else                this.currentMottos = MOTTOS_UNDER80;
    this.mottoIndex = 0;
  }

  get currentMotto(): string {
    const list = this.currentMottos;
    if (!list || list.length === 0) return '';
    return list[this.mottoIndex % list.length];
  }

  // ---- Helpers ----

  getMedalClass(rank: number): string {
    if (rank === 1) return 'medal-gold';
    if (rank === 2) return 'medal-silver';
    if (rank === 3) return 'medal-bronze';
    return 'medal-default';
  }

  getProgressPct(ibcOk: number): number {
    if (!this.goal) return 0;
    return Math.min(Math.round((ibcOk / this.goal) * 100), 100);
  }

  getProgressClass(ibcOk: number): string {
    const pct = this.getProgressPct(ibcOk);
    if (pct >= 100) return 'bar-gold';
    if (pct >= 80)  return 'bar-green';
    if (pct >= 50)  return 'bar-yellow';
    return 'bar-red';
  }

  formatTime(d: Date): string {
    const hh = d.getHours().toString().padStart(2, '0');
    const mm = d.getMinutes().toString().padStart(2, '0');
    const ss = d.getSeconds().toString().padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
  }

  formatDate(iso: string): string {
    if (!iso) return '';
    const parts = iso.split('-');
    if (parts.length !== 3) return iso;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  trackByIndex(index: number): number { return index; }
}
