import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export interface VeckaTrendPunkt {
  vecka: string;
  year: number;
  week: number;
  ibc: number;
  rank: number | null;
}

export interface OperatorTrend {
  operator_id: number;
  operator_namn: string;
  trend: VeckaTrendPunkt[];
}

export interface WeeklyRankingsData {
  veckor: string[];
  op_trender: OperatorTrend[];
  weeks: number;
}

export interface WeeklyRankingsResponse {
  success: boolean;
  data: WeeklyRankingsData;
}

export interface RankingAndring {
  operator_id: number;
  operator_namn: string;
  rank_nu: number | null;
  rank_foreg: number | null;
  andring: number | null;
  ibc_nu: number;
  ibc_foreg: number;
  vecka_nu: string;
  vecka_foreg: string;
}

export interface RankingChangesData {
  andringar: RankingAndring[];
}

export interface RankingChangesResponse {
  success: boolean;
  data: RankingChangesData;
}

export interface StreakItem {
  operator_id: number;
  operator_namn: string;
  rank_nu: number | null;
  positiv_streak: number;
  negativ_streak: number;
  rankningssekvens: (number | null)[];
}

export interface StreakData {
  streaks: StreakItem[];
  langsta_pos_streak: number;
  mest_konsekvent: string | null;
  weeks: number;
}

export interface StreakResponse {
  success: boolean;
  data: StreakData;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class RankingHistorikService {
  private api = '../../noreko-backend/api.php?action=ranking-historik';

  constructor(private http: HttpClient) {}

  getWeeklyRankings(weeks: number = 12): Observable<WeeklyRankingsResponse | null> {
    return this.http.get<WeeklyRankingsResponse>(
      `${this.api}&run=weekly-rankings&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getRankingChanges(): Observable<RankingChangesResponse | null> {
    return this.http.get<RankingChangesResponse>(
      `${this.api}&run=ranking-changes`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getStreakData(weeks: number = 12): Observable<StreakResponse | null> {
    return this.http.get<StreakResponse>(
      `${this.api}&run=streak-data&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
