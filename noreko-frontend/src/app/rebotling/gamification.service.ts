import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface LeaderboardEntry {
  rank: number;
  user_id: number;
  operator_namn: string;
  total_ibc: number;
  ok_ibc: number;
  kassations_rate: number;
  kvalitets_faktor: number;
  antal_stopp: number;
  stopptid_sek: number;
  stopp_multiplikator: number;
  total_poang: number;
  streak: number;
}

export interface LeaderboardData {
  leaderboard: LeaderboardEntry[];
  period: string;
  from_date: string;
  to_date: string;
}

export interface Badge {
  id: string;
  namn: string;
  beskrivning: string;
  ikon: string;
  farg: string;
  uppnadd: boolean;
  tilldelad: string | null;
}

export interface BadgesData {
  operator_id: number;
  badges: Badge[];
  antal_badges: number;
  total_badges: number;
}

export interface Milstolpe {
  namn: string;
  krav: number;
  ikon: string;
  farg: string;
  uppnadd: boolean;
  progress: number;
  nuvarande: number;
}

export interface MinProfilData {
  user_id: number;
  operator_namn: string;
  rank: number | null;
  total_operatorer: number;
  total_poang: number;
  total_ibc: number;
  streak: number;
  badges: Badge[];
  antal_badges: number;
  milstolpar: Milstolpe[];
  period: string;
}

export interface OverviewData {
  total_operatorer: number;
  total_poang: number;
  total_ibc: number;
  avg_poang: number;
  total_badges_utdelade: number;
  avg_streak: number;
  max_streak: number;
  top3: LeaderboardEntry[];
  period: string;
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
export class GamificationService {
  private api = `${environment.apiUrl}?action=gamification`;

  constructor(private http: HttpClient) {}

  getLeaderboard(period: string): Observable<ApiResponse<LeaderboardData> | null> {
    return this.http.get<ApiResponse<LeaderboardData>>(
      `${this.api}&run=leaderboard&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getBadges(operatorId: number): Observable<ApiResponse<BadgesData> | null> {
    return this.http.get<ApiResponse<BadgesData>>(
      `${this.api}&run=badges&operator_id=${operatorId}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getMinProfil(): Observable<ApiResponse<MinProfilData> | null> {
    return this.http.get<ApiResponse<MinProfilData>>(
      `${this.api}&run=min-profil`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getOverview(): Observable<ApiResponse<OverviewData> | null> {
    return this.http.get<ApiResponse<OverviewData>>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
