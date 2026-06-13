import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SammanfattningData {
  total_ibc: number;
  hogsta_poang: number;
  antal_operatorer: number;
  avg_poang: number;
  period: string;
  from_date: string;
  to_date: string;
}

export interface OperatorRank {
  rank: number;
  user_id: number;
  operator_namn: string;
  total_ibc: number;
  ok_ibc: number;
  ok_pct: number;
  ibc_per_h: number;
  produktions_poang: number;
  kvalitets_bonus: number;
  tempo_bonus: number;
  stopp_bonus: number;
  total_bonus: number;
  total_poang: number;
  antal_stopp: number;
  stopptid_sek: number;
  streak: number;
  streak_bonus: number;
  // Tvättlinje extras (optional)
  skift_count?: number;
  avg_ibc_per_skift?: number;
}

export interface TvattOpSammanfattning {
  total_ibc: number;
  aktiva_operatorer: number;
  snitt_ibc_per_h: number;
  snitt_poang?: number;
  hogsta_poang?: number;
  basta_operator: { namn: string; ibc_per_h: number; total_poang?: number } | null;
}

export interface RankingData {
  ranking: OperatorRank[];
  period: string;
  from_date: string;
  to_date: string;
}

export interface TopplistaData {
  topplista: OperatorRank[];
  period: string;
  from_date: string;
  to_date: string;
}

export interface PoangFordelningItem {
  operator_namn: string;
  produktions_poang: number;
  kvalitets_bonus: number;
  tempo_bonus: number;
  stopp_bonus: number;
  streak_bonus: number;
  total_poang: number;
}

export interface PoangFordelningData {
  chart_data: PoangFordelningItem[];
  period: string;
  from_date: string;
  to_date: string;
}

export interface HistorikDataset {
  user_id: number;
  operator_namn: string;
  data: number[];
}

export interface HistorikData {
  dates: string[];
  datasets: HistorikDataset[];
  operatorer: string[];
}

export interface MvpData {
  mvp: OperatorRank | null;
  typ: string;
  from_date: string;
  to_date: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorRankingService {
  constructor(private http: HttpClient) {}

  private action(line: 'rebotling' | 'tvattlinje'): string {
    return `${environment.apiUrl}?action=${line === 'tvattlinje' ? 'tvattlinje-operator' : 'operator-ranking'}`;
  }

  getSammanfattning(period: string, line: 'rebotling' | 'tvattlinje' = 'rebotling'): Observable<any | null> {
    return this.http.get<any>(
      `${this.action(line)}&run=sammanfattning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getRanking(period: string, line: 'rebotling' | 'tvattlinje' = 'rebotling'): Observable<any | null> {
    return this.http.get<any>(
      `${this.action(line)}&run=ranking&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTopplista(period: string, line: 'rebotling' | 'tvattlinje' = 'rebotling'): Observable<any | null> {
    return this.http.get<any>(
      `${this.action(line)}&run=topplista&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPoangfordelning(period: string, line: 'rebotling' | 'tvattlinje' = 'rebotling'): Observable<any | null> {
    return this.http.get<any>(
      `${this.action(line)}&run=poangfordelning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getHistorik(days: number = 90): Observable<ApiResponse<HistorikData> | null> {
    return this.http.get<ApiResponse<HistorikData>>(
      `${environment.apiUrl}?action=operator-ranking&run=historik&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getMvp(typ: string, line: 'rebotling' | 'tvattlinje' = 'rebotling'): Observable<ApiResponse<MvpData> | null> {
    if (line === 'tvattlinje') {
      return this.http.get<ApiResponse<MvpData>>(
        `${this.action('tvattlinje')}&run=mvp&period=${typ}`,
        { withCredentials: true }
      ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
    }
    return this.http.get<ApiResponse<MvpData>>(
      `${environment.apiUrl}?action=operator-ranking&run=mvp&typ=${typ}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
