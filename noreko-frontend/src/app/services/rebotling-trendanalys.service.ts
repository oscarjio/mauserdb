import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface TrendKort {
  nuvarande: number;
  slope: number;
  r2: number;
  medel_7d: number;
  medel_30d: number;
  trend: 'up' | 'down' | 'stable';
  alert: 'ok' | 'warning' | 'critical';
  sparkline: { datum: string; varde: number }[];
}

export interface TrenderData {
  oee: TrendKort;
  produktion: TrendKort;
  kassation: TrendKort;
}

export interface DagligHistorikRad {
  datum: string;
  oee: number;
  produktion: number;
  kassation: number;
  oee_ma7: number | null;
  prod_ma7: number | null;
  kass_ma7: number | null;
}

export interface VeckoRad {
  ar: number;
  vecka: number;
  from_datum: string;
  to_datum: string;
  produktion: number;
  oee: number;
  kassation: number;
  prod_diff_pct: number | null;
  oee_diff_pct: number | null;
  kass_diff_pct: number | null;
  basta_produktion: boolean;
  samsta_produktion: boolean;
  basta_oee: boolean;
  samsta_oee: boolean;
}

export interface Anomali {
  datum: string;
  typ: string;
  nyckel: string;
  varde: number;
  medel: number;
  stdav: number;
  avvikelse: number;
  enhet: string;
  positivt: boolean;
}

export interface PrognosDag {
  datum: string;
  oee: number;
  produktion: number;
  kassation: number;
}

export interface PrognisData {
  oee: number | null;
  produktion: number | null;
  kassation: number | null;
  dagar: PrognosDag[];
  oee_slope: number;
  prod_slope: number;
  kass_slope: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class RebotlingTrendanalysService {
  private api = `${environment.apiUrl}?action=rebotlingtrendanalys`;

  constructor(private http: HttpClient) {}

  getTrender(): Observable<ApiResponse<TrenderData> | null> {
    return this.http.get<ApiResponse<TrenderData>>(
      `${this.api}&run=trender`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getDagligHistorik(): Observable<ApiResponse<DagligHistorikRad[]> | null> {
    return this.http.get<ApiResponse<DagligHistorikRad[]>>(
      `${this.api}&run=daglig-historik`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getVeckosammanfattning(): Observable<ApiResponse<VeckoRad[]> | null> {
    return this.http.get<ApiResponse<VeckoRad[]>>(
      `${this.api}&run=veckosammanfattning`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getAnomalier(): Observable<ApiResponse<Anomali[]> | null> {
    return this.http.get<ApiResponse<Anomali[]>>(
      `${this.api}&run=anomalier`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getPrognos(): Observable<ApiResponse<PrognisData> | null> {
    return this.http.get<ApiResponse<PrognisData>>(
      `${this.api}&run=prognos`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
