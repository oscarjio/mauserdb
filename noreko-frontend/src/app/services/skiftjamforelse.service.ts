import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export interface SammanfattningData {
  mest_produktiva_idag: { skift: string; label: string; ibc_per_h: number } | null;
  snitt_oee: { FM: number; EM: number; Natt: number };
  mest_forbattrad: { skift: string; label: string; delta: number } | null;
  antal_skift: number;
  days: number;
  from_date: string;
  to_date: string;
}

export interface SkiftRow {
  skift: string;
  label: string;
  antal_pass: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  runtime_min: number;
  ibc_per_h: number;
  kvalitet_pct: number;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  avg_cykeltid_sek: number;
  stopptid_min: number;
}

export interface RadarAxel {
  tillganglighet: number;
  prestanda: number;
  kvalitet: number;
  volym: number;
  stabilitet: number;
}

export interface JamforelseData {
  skift: SkiftRow[];
  radar: { FM: RadarAxel; EM: RadarAxel; Natt: RadarAxel };
  days: number;
  from_date: string;
  to_date: string;
}

export interface TrendPoint {
  datum: string;
  FM: number | null;
  EM: number | null;
  Natt: number | null;
}

export interface TrendData {
  trend: TrendPoint[];
  days: number;
}

export interface BestPractice {
  skift: string;
  label: string;
  oee_pct: number;
  ibc_per_h: number;
  kvalitet_pct: number;
  stopptid_min: number;
  basta_station: string | null;
  basta_station_oee: number | null;
  insights: string[];
}

export interface BestPracticesData {
  practices: BestPractice[];
  days: number;
  from_date: string;
  to_date: string;
}

export interface DetaljRow {
  datum: string;
  skift: string;
  skift_label: string;
  station: string;
  operator: string;
  ibc_ok: number;
  ibc_total: number;
  oee_pct: number;
  stopptid_min: number;
  runtime_min: number;
}

export interface DetaljerData {
  detaljer: DetaljRow[];
  days: number;
  from_date: string;
  to_date: string;
  total: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class SkiftjamforelseService {
  private api = '../../noreko-backend/api.php?action=skiftjamforelse';

  constructor(private http: HttpClient) {}

  getSammanfattning(days: number): Observable<ApiResponse<SammanfattningData> | null> {
    return this.http.get<ApiResponse<SammanfattningData>>(
      `${this.api}&run=sammanfattning&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getJamforelse(days: number): Observable<ApiResponse<JamforelseData> | null> {
    return this.http.get<ApiResponse<JamforelseData>>(
      `${this.api}&run=jamforelse&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getTrend(days: number): Observable<ApiResponse<TrendData> | null> {
    return this.http.get<ApiResponse<TrendData>>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getBestPractices(days: number): Observable<ApiResponse<BestPracticesData> | null> {
    return this.http.get<ApiResponse<BestPracticesData>>(
      `${this.api}&run=best-practices&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getDetaljer(days: number): Observable<ApiResponse<DetaljerData> | null> {
    return this.http.get<ApiResponse<DetaljerData>>(
      `${this.api}&run=detaljer&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
