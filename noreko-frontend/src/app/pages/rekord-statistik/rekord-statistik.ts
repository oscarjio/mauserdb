import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface BestShift {
  rank: number;
  datum: string;
  ibc_ok: number;
  drifttid: number;
  ibc_per_h: number;
  op1: number; op2: number; op3: number;
  op1_name: string; op2_name: string; op3_name: string;
}

interface CareerStat {
  rank: number;
  number: number;
  name: string;
  career_shifts: number;
  career_ibc: number;
  career_hours: number;
  career_ibc_h: number;
  best_ibc_h: number;
}

interface MonthlyChampion {
  month: string;
  champion_num: number | null;
  champion_name: string | null;
  ibc_h: number | null;
  month_shifts: number;
  month_ibc: number;
}

interface RekordsResponse {
  success: boolean;
  best_shifts: BestShift[];
  career_stats: CareerStat[];
  monthly_champions: MonthlyChampion[];
}

@Component({
  standalone: true,
  selector: 'app-rekord-statistik',
  imports: [CommonModule, RouterModule],
  templateUrl: './rekord-statistik.html',
  styleUrl: './rekord-statistik.css'
})
export class RekordStatistikPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';
  activeTab: 'skift' | 'karriar' | 'manader' = 'skift';

  bestShifts: BestShift[] = [];
  careerStats: CareerStat[] = [];
  monthlyChampions: MonthlyChampion[] = [];

  Math = Math;

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

    this.http.get<RekordsResponse>(
      `${environment.apiUrl}?action=rebotling&run=rekord-statistik`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta rekordstatistik.';
        return;
      }
      this.bestShifts       = res.best_shifts;
      this.careerStats      = res.career_stats;
      this.monthlyChampions = res.monthly_champions;
    });
  }

  setTab(t: typeof this.activeTab): void { this.activeTab = t; }

  monthLabel(m: string): string {
    const [year, month] = m.split('-');
    const names = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    return `${names[parseInt(month, 10) - 1]} ${year}`;
  }

  rankMedal(rank: number): string {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return `#${rank}`;
  }

  drifttidLabel(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  ibcHColor(val: number, ref: number): string {
    if (ref <= 0) return '';
    const pct = (val - ref) / ref * 100;
    if (pct >= 15) return 'text-success-bright';
    if (pct >= 0)  return 'text-success';
    return 'text-muted-light';
  }

  // Career rank badge color
  rankColor(rank: number): string {
    if (rank === 1) return '#f6c90e';
    if (rank === 2) return '#c0c0c0';
    if (rank === 3) return '#cd7f32';
    return '#4a5568';
  }

  get topCareerIbc(): number {
    return this.careerStats.length > 0 ? this.careerStats[0].career_ibc : 1;
  }

  careerBar(ibc: number): number {
    return Math.round((ibc / Math.max(this.topCareerIbc, 1)) * 100);
  }

  // Count how many times each operator won a month
  get championCounts(): Record<number, number> {
    const counts: Record<number, number> = {};
    for (const mc of this.monthlyChampions) {
      if (mc.champion_num !== null) {
        counts[mc.champion_num] = (counts[mc.champion_num] ?? 0) + 1;
      }
    }
    return counts;
  }

  isCurrentMonth(m: string): boolean {
    return m === new Date().toISOString().slice(0, 7);
  }

  // Sorted list of operators by monthly wins (for display)
  get championRanking(): { number: number; name: string; wins: number }[] {
    const nameMap: Record<number, string> = {};
    for (const op of this.careerStats) {
      nameMap[op.number] = op.name;
    }
    const counts = this.championCounts;
    return Object.entries(counts)
      .map(([num, wins]) => ({ number: +num, name: nameMap[+num] ?? `#${num}`, wins }))
      .filter(x => x.wins > 0)
      .sort((a, b) => b.wins - a.wins);
  }

  // Returns an array of length n for *ngFor repetition
  crownArray(n: number): null[] {
    return Array(Math.min(n, 12)).fill(null);
  }
}
