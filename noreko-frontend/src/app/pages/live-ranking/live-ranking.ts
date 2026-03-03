import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

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
  error?: string;
}

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

  mottoIndex = 0;
  private mottoTimer: any = null;

  readonly mottos: string[] = [
    'Håll farten uppe!',
    'Teamet gör ett bra jobb idag!',
    'Varje IBC räknas — fortsätt!',
    'Tillsammans når vi målet!',
    'Bra jobbat — ge allt!',
    'Ni är grymma — kör på!',
    'Fokus ger resultat!',
    'Ännu ett IBC — bra!',
  ];

  private readonly apiUrl = '/noreko-backend/api.php?action=rebotling&run=live-ranking';

  private pollInterval: any = null;
  private dataSub: Subscription | null = null;
  private destroy$ = new Subject<void>();

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadData();
    this.pollInterval = setInterval(() => this.loadData(), 30000);
    this.mottoTimer = setInterval(() => {
      this.mottoIndex = (this.mottoIndex + 1) % this.mottos.length;
    }, 6000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.mottoTimer) clearInterval(this.mottoTimer);
    this.dataSub?.unsubscribe();
  }

  loadData(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.dataSub?.unsubscribe();
    this.dataSub = this.http.get<LiveRankingResponse>(this.apiUrl)
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.ranking = res.ranking ?? [];
            this.date = res.date ?? '';
            this.period = res.period ?? '';
            this.goal = res.goal ?? 0;
            this.lastRefresh = new Date();
            this.error = null;
          } else if (res) {
            this.error = res.error ?? 'Kunde inte hämta data';
          }
          this.loading = false;
          this.isFetching = false;
        },
        error: () => {
          this.loading = false;
          this.isFetching = false;
          this.error = 'Anslutningsfel — försöker igen om 30 s';
        }
      });
  }

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
    if (pct >= 80) return 'bar-green';
    if (pct >= 50) return 'bar-yellow';
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
}
