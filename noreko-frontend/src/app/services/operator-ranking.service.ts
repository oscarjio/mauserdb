import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
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
  private api = `${environment.apiUrl}?action=operator-ranking`;

  constructor(private http: HttpClient) {}

  getSammanfattning(period: string): Observable<ApiResponse<SammanfattningData> | null> {
    return this.http.get<ApiResponse<SammanfattningData>>(
      `${this.api}&run=sammanfattning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getRanking(period: string): Observable<ApiResponse<RankingData> | null> {
    return this.http.get<ApiResponse<RankingData>>(
      `${this.api}&run=ranking&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getTopplista(period: string): Observable<ApiResponse<TopplistaData> | null> {
    return this.http.get<ApiResponse<TopplistaData>>(
      `${this.api}&run=topplista&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getPoangfordelning(period: string): Observable<ApiResponse<PoangFordelningData> | null> {
    return this.http.get<ApiResponse<PoangFordelningData>>(
      `${this.api}&run=poangfordelning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getHistorik(): Observable<ApiResponse<HistorikData> | null> {
    return this.http.get<ApiResponse<HistorikData>>(
      `${this.api}&run=historik`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getMvp(typ: string): Observable<ApiResponse<MvpData> | null> {
    return this.http.get<ApiResponse<MvpData>>(
      `${this.api}&run=mvp&typ=${typ}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
