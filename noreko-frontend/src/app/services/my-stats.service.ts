import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ================================================================
// Interfaces
// ================================================================

export interface MyStatsData {
  operator_num: number;
  operator_namn: string;
  period: number;
  from_date: string;
  to_date: string;
  total_ibc: number;
  snitt_ibc_per_h: number;
  kvalitet_pct: number | null;
  bast_dag: string | null;
  bast_dag_ibc: number;
  team_snitt_ibc_per_h: number;
  team_snitt_kvalitet: number | null;
  ranking: number;
  total_ops: number;
}

export interface MyStatsResponse {
  success: boolean;
  data: MyStatsData;
  timestamp: string;
}

export interface MyTrendData {
  operator_num: number;
  period: number;
  from_date: string;
  to_date: string;
  dates: string[];
  my_ibc: number[];
  my_ibc_per_h: number[];
  my_kvalitet: (number | null)[];
  team_ibc_per_h: number[];
}

export interface MyTrendResponse {
  success: boolean;
  data: MyTrendData;
  timestamp: string;
}

export interface MyAchievementsData {
  operator_num: number;
  operator_namn: string;
  karriar_total: number;
  bast_dag_ever: string | null;
  bast_dag_ever_ibc: number;
  streak: number;
  forbattring_pct: number;
  forbattring_direction: 'upp' | 'ner' | 'stabil';
  week1_ibc_per_h: number;
  week2_ibc_per_h: number;
}

export interface MyAchievementsResponse {
  success: boolean;
  data: MyAchievementsData;
  timestamp: string;
}

// ================================================================
// Service
// ================================================================

@Injectable({ providedIn: 'root' })
export class MyStatsService {
  private api = `${environment.apiUrl}?action=my-stats`;

  constructor(private http: HttpClient) {}

  getMyStats(period: 7 | 30 | 90): Observable<MyStatsResponse | null> {
    return this.http.get<MyStatsResponse>(
      `${this.api}&run=my-stats&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getMyTrend(period: 30 | 90): Observable<MyTrendResponse | null> {
    return this.http.get<MyTrendResponse>(
      `${this.api}&run=my-trend&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getMyAchievements(): Observable<MyAchievementsResponse | null> {
    return this.http.get<MyAchievementsResponse>(
      `${this.api}&run=my-achievements`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
