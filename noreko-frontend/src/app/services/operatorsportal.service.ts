import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SkiftInfo {
  aktiv: boolean;
  skiftraknare: number | null;
  ibc_idag: number;
  runtime_min: number;
  senaste_aktivitet: string | null;
}

export interface RankingInfo {
  position: number;
  total_ops: number;
}

export interface MyStatsData {
  operator_id: number;
  operator_name: string;
  ibc_idag: number;
  ibc_vecka: number;
  ibc_manad: number;
  team_snitt_idag: number;
  team_snitt_vecka: number;
  team_snitt_manad: number;
  ibc_per_timme: number;
  team_ibc_per_timme: number;
  ranking: RankingInfo;
  skift: SkiftInfo;
}

export interface MyStatsResponse {
  success: boolean;
  data: MyStatsData;
  timestamp: string;
}

export interface MyTrendData {
  labels: string[];
  my_ibc: number[];
  team_snitt: number[];
  days: number;
  from_date: string;
  to_date: string;
}

export interface MyTrendResponse {
  success: boolean;
  data: MyTrendData;
  timestamp: string;
}

export interface MyBonusData {
  timmar_arbetade: number;
  ibc_totalt: number;
  ibc_per_timme: number;
  team_ibc_per_timme: number;
  diff_vs_team: number;
  bonus_poang: number;
  avg_bonus_poang: number;
  bonus_mal: number;
  bonus_pct: number;
  antal_skift: number;
  period_from: string;
  period_to: string;
}

export interface MyBonusResponse {
  success: boolean;
  data: MyBonusData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorsportalService {
  private api = `${environment.apiUrl}?action=operatorsportal`;

  constructor(private http: HttpClient) {}

  getMyStats(): Observable<MyStatsResponse | null> {
    return this.http.get<MyStatsResponse>(
      `${this.api}&run=my-stats`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getMyTrend(days = 30): Observable<MyTrendResponse | null> {
    return this.http.get<MyTrendResponse>(
      `${this.api}&run=my-trend&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getMyBonus(): Observable<MyBonusResponse | null> {
    return this.http.get<MyBonusResponse>(
      `${this.api}&run=my-bonus`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
