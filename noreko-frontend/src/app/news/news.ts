import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { of, Subject } from 'rxjs';
import { catchError, timeout, takeUntil } from 'rxjs/operators';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse } from '../services/rebotling.service';
import { TvattlinjeService, TvattlinjeLiveStatsResponse } from '../services/tvattlinje.service';
import { LineSkiftrapportService } from '../services/line-skiftrapport.service';
import { AuthService, AuthUser } from '../services/auth.service';
import { localToday } from '../utils/date-utils';

interface LineSkiftrapportReport {
  id: number;
  datum: string;
  antal_ok: number;
  antal_ej_ok: number;
  totalt: number;
  kommentar: string;
  user_name: string;
  inlagd: number;
  user_id: number;
}

interface LineReportsResponse {
  success: boolean;
  data?: LineSkiftrapportReport[];
  message?: string;
}

export interface NewsEvent {
  id: number | null;
  typ: string;
  datum: string;
  datetime?: string;
  text: string;
  ikon: string;
  category: string;
  pinned: boolean;
}

export type NewsCategory = 'alla' | 'produktion' | 'bonus' | 'system' | 'info' | 'viktig';

@Component({
  selector: 'app-news',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './news.html',
  styleUrl: './news.css'
})
export class News implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  intervalId: ReturnType<typeof setInterval> | null = null;
  eventsIntervalId: ReturnType<typeof setInterval> | null = null;
  loggedIn = false;
  isAdmin = false;

  // Rebotling data
  rebotlingStatus: boolean = false;
  rebotlingToday: number = 0;
  rebotlingTarget: number = 0;
  rebotlingPercentage: number = 0;

  // Tvättlinje data
  tvattlinjeStatus: boolean = false;
  tvattlinjeToday: number = 0;
  tvattlinjeTarget: number = 0;
  tvattlinjePercentage: number = 0;

  // Saglinje data
  saglinjeStatus: boolean = false;
  saglinjeToday: number = 0;
  saglinjeTarget: number = 0;
  saglinjeKvalitetPct: number = 0;
  saglinjeSkiftCount: number = 0;

  // Klassificeringslinje data
  klassificeringslinjeStatus: boolean = false;
  klassificeringslinjeToday: number = 0;
  klassificeringslinjeTarget: number = 0;
  klassificeringslinjeKvalitetPct: number = 0;
  klassificeringslinjeSkiftCount: number = 0;

  // Senaste händelser
  events: NewsEvent[] = [];
  filteredEvents: NewsEvent[] = [];
  loadingEvents = true;
  activeCategory: NewsCategory = 'alla';

  readonly categories: { key: NewsCategory; label: string }[] = [
    { key: 'alla',        label: 'Alla' },
    { key: 'produktion',  label: 'Produktion' },
    { key: 'bonus',       label: 'Bonus' },
    { key: 'system',      label: 'System' },
    { key: 'info',        label: 'Info' },
    { key: 'viktig',      label: 'Viktig' },
  ];

  // Reaktioner (sparas i localStorage)
  reactions: Record<number, { liked: boolean; acked: boolean }> = {};

  // Expanderade nyheter (läs-mer)
  expandedIds = new Set<number | string>();

  private apiBase = '/noreko-backend/api.php';

  constructor(
    private rebotlingService: RebotlingService,
    private tvattlinjeService: TvattlinjeService,
    private lineSkiftrapportService: LineSkiftrapportService,
    private auth: AuthService,
    private http: HttpClient
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((val: AuthUser | null | undefined) => {
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadReactions();

    this.intervalId = setInterval(() => {
      this.fetchAllData();
    }, 5000);
    this.fetchAllData();

    // Händelser: ladda direkt och sedan var 5:e minut
    this.loadEvents();
    this.eventsIntervalId = setInterval(() => {
      this.loadEvents();
    }, 300000);
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
    if (this.eventsIntervalId) {
      clearInterval(this.eventsIntervalId);
    }
  }

  private fetchAllData() {
    this.fetchRebotlingData();
    this.fetchTvattlinjeData();
    this.fetchSaglinjeData();
    this.fetchKlassificeringslinjeData();
  }

  private fetchRebotlingData() {
    this.rebotlingService.getLiveStats().pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: RebotlingLiveStatsResponse | null) => {
      if (res && res.success && res.data) {
        this.rebotlingToday = res.data.ibcToday || 0;
        this.rebotlingTarget = res.data.rebotlingTarget || 0;
        this.rebotlingPercentage = this.rebotlingTarget > 0
          ? Math.round((this.rebotlingToday / this.rebotlingTarget) * 100) : 0;
      }
    });

    this.rebotlingService.getRunningStatus().pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: LineStatusResponse | null) => {
      if (res && res.success && res.data) {
        this.rebotlingStatus = res.data.running;
      }
    });
  }

  private fetchTvattlinjeData() {
    this.tvattlinjeService.getLiveStats().pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: TvattlinjeLiveStatsResponse | null) => {
      if (res && res.success && res.data) {
        this.tvattlinjeToday = res.data.ibcToday;
        this.tvattlinjeTarget = res.data.ibcTarget;
        this.tvattlinjePercentage = res.data.productionPercentage || 0;
      }
    });

    this.tvattlinjeService.getRunningStatus().pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: LineStatusResponse | null) => {
      if (res && res.success && res.data) {
        this.tvattlinjeStatus = res.data.running;
      }
    });
  }

  private fetchSaglinjeData() {
    this.lineSkiftrapportService.getReports('saglinje').pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: LineReportsResponse | null) => {
      if (res?.success && res.data) {
        const today = localToday();
        const reps = res.data.filter((r: LineSkiftrapportReport) => (r.datum || '').substring(0, 10) === today);
        this.saglinjeSkiftCount = reps.length;
        this.saglinjeToday = reps.reduce((s: number, r: LineSkiftrapportReport) => s + (r.antal_ok || 0), 0);
        this.saglinjeTarget = reps.reduce((s: number, r: LineSkiftrapportReport) => s + (r.antal_ej_ok || 0), 0) + this.saglinjeToday;
        this.saglinjeKvalitetPct = this.saglinjeTarget > 0
          ? Math.round((this.saglinjeToday / this.saglinjeTarget) * 100) : 0;
        this.saglinjeStatus = reps.length > 0;
      }
    });
  }

  private fetchKlassificeringslinjeData() {
    this.lineSkiftrapportService.getReports('klassificeringslinje').pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: LineReportsResponse | null) => {
      if (res?.success && res.data) {
        const today = localToday();
        const reps = res.data.filter((r: LineSkiftrapportReport) => (r.datum || '').substring(0, 10) === today);
        this.klassificeringslinjeSkiftCount = reps.length;
        this.klassificeringslinjeToday = reps.reduce((s: number, r: LineSkiftrapportReport) => s + (r.antal_ok || 0), 0);
        this.klassificeringslinjeTarget = reps.reduce((s: number, r: LineSkiftrapportReport) => s + (r.antal_ej_ok || 0), 0) + this.klassificeringslinjeToday;
        this.klassificeringslinjeKvalitetPct = this.klassificeringslinjeTarget > 0
          ? Math.round((this.klassificeringslinjeToday / this.klassificeringslinjeTarget) * 100) : 0;
        this.klassificeringslinjeStatus = reps.length > 0;
      }
    });
  }

  loadEvents() {
    this.http.get<{ success: boolean; events: NewsEvent[] }>(
      `${this.apiBase}?action=news&run=events&antal=15`
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loadingEvents = false;
      if (res?.success && Array.isArray(res.events)) {
        this.events = res.events;
        this.applyFilter();
      }
    });
  }

  // --- Kategorifiltrering ---

  setCategory(cat: NewsCategory): void {
    this.activeCategory = cat;
    this.applyFilter();
  }

  private applyFilter(): void {
    if (this.activeCategory === 'alla') {
      this.filteredEvents = this.events;
    } else {
      this.filteredEvents = this.events.filter(e => e.category === this.activeCategory);
    }
  }

  getCategoryCountFor(cat: NewsCategory): number {
    if (cat === 'alla') return this.events.length;
    return this.events.filter(e => e.category === cat).length;
  }

  // --- Reaktioner (localStorage) ---

  loadReactions(): void {
    try {
      const stored = localStorage.getItem('news_reactions');
      if (stored) this.reactions = JSON.parse(stored);
    } catch {
      this.reactions = {};
    }
  }

  private saveReactions(): void {
    localStorage.setItem('news_reactions', JSON.stringify(this.reactions));
  }

  toggleLike(id: number | null): void {
    if (id === null) return;
    if (!this.reactions[id]) this.reactions[id] = { liked: false, acked: false };
    this.reactions[id].liked = !this.reactions[id].liked;
    this.saveReactions();
  }

  toggleAck(id: number | null): void {
    if (id === null) return;
    if (!this.reactions[id]) this.reactions[id] = { liked: false, acked: false };
    this.reactions[id].acked = !this.reactions[id].acked;
    this.saveReactions();
  }

  isLiked(id: number | null): boolean {
    if (id === null) return false;
    return this.reactions[id]?.liked ?? false;
  }

  isAcked(id: number | null): boolean {
    if (id === null) return false;
    return this.reactions[id]?.acked ?? false;
  }

  // --- Läs-mer / expandera ---

  eventKey(e: NewsEvent): number | string {
    return e.id !== null ? e.id : (e.typ + '_' + e.datum);
  }

  isExpanded(e: NewsEvent): boolean {
    return this.expandedIds.has(this.eventKey(e));
  }

  toggleExpand(e: NewsEvent): void {
    const key = this.eventKey(e);
    if (this.expandedIds.has(key)) {
      this.expandedIds.delete(key);
    } else {
      this.expandedIds.add(key);
    }
  }

  truncate(text: string, len = 200): string {
    return text.length > len ? text.slice(0, len) + '...' : text;
  }

  needsTruncation(text: string, len = 200): boolean {
    return text.length > len;
  }

  // --- Relativ tid ---

  timeAgo(dateStr: string): string {
    if (!dateStr) return '';
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) return 'Just nu';
    if (diff < 3600) return `${Math.floor(diff / 60)} min sedan`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} h sedan`;
    const d = Math.floor(diff / 86400);
    return d === 1 ? 'Igår' : `${d} dagar sedan`;
  }

  // --- Hjälpmetoder för kategori-badge ---

  getCategoryLabel(cat: string): string {
    const labels: Record<string, string> = {
      produktion:    'Produktion',
      bonus:         'Bonus',
      system:        'System',
      info:          'Info',
      viktig:        'Viktig',
      rekord:        'Rekord',
      hog_oee:       'Hog OEE',
      certifiering:  'Certifiering',
      urgent:        'Brådskande',
    };
    return labels[cat] ?? cat;
  }

  getCategoryClass(cat: string): string {
    const classes: Record<string, string> = {
      produktion:    'badge-cat-produktion',
      bonus:         'badge-cat-bonus',
      system:        'badge-cat-system',
      info:          'badge-cat-info',
      viktig:        'badge-cat-viktig',
      rekord:        'badge-cat-rekord',
      hog_oee:       'badge-cat-hog_oee',
      certifiering:  'badge-cat-certifiering',
      urgent:        'badge-cat-urgent',
    };
    return 'badge-category ' + (classes[cat] ?? 'badge-cat-info');
  }

  getPercentageClass(pct: number): string {
    if (pct >= 100) return 'text-success';
    if (pct >= 60) return 'text-warning';
    return 'text-danger';
  }

  getProgressClass(pct: number): string {
    if (pct >= 100) return 'bg-success';
    if (pct >= 60) return 'bg-warning';
    return 'bg-danger';
  }
}
