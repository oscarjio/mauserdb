import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface StationerData {
  stationer: string[];
}

export interface StationKpiData {
  station: string;
  period_dagar: number;
  from_date: string;
  to_date: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  drifttid_h: number;
  planerad_h: number;
  total_ibc: number;
  ok_ibc: number;
  kasserade_ibc: number;
  kassationsgrad_pct: number;
  avg_cykeltid_sek: number;
  arbetsdagar: number;
}

export interface DrifttidDag {
  datum: string;
  drifttid_h: number;
  total_ibc: number;
}

export interface StationDrifttidData {
  station: string;
  period_dagar: number;
  dagdata: DrifttidDag[];
}

export interface OeeTrendDag {
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
}

export interface StationOeeTrendData {
  station: string;
  period_dagar: number;
  dagdata: OeeTrendDag[];
}

export interface StoppRad {
  id: number;
  start_time: string;
  stop_time: string | null;
  varaktighet_sek: number;
  varaktighet_min: number;
  status: string;
}

export interface StationStoppData {
  stopp: StoppRad[];
  antal: number;
}

export interface JamforelseRad {
  station: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  drifttid_h: number;
  total_ibc: number;
  kasserade_ibc: number;
  kassationsgrad_pct: number;
  avg_cykeltid_sek: number;
  rang: 'bast' | 'samst' | 'normal';
}

export interface JamforelseData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  jamforelse: JamforelseRad[];
  antal: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MaskinhistorikService {
  private api = `${environment.apiUrl}?action=maskinhistorik`;

  constructor(private http: HttpClient) {}

  getStationer(): Observable<ApiResponse<StationerData> | null> {
    return this.http.get<ApiResponse<StationerData>>(
      `${this.api}&run=stationer`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStationKpi(station: string, period: number): Observable<ApiResponse<StationKpiData> | null> {
    return this.http.get<ApiResponse<StationKpiData>>(
      `${this.api}&run=station-kpi&station=${encodeURIComponent(station)}&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getStationDrifttid(station: string, period: number): Observable<ApiResponse<StationDrifttidData> | null> {
    return this.http.get<ApiResponse<StationDrifttidData>>(
      `${this.api}&run=station-drifttid&station=${encodeURIComponent(station)}&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getStationOeeTrend(station: string, period: number): Observable<ApiResponse<StationOeeTrendData> | null> {
    return this.http.get<ApiResponse<StationOeeTrendData>>(
      `${this.api}&run=station-oee-trend&station=${encodeURIComponent(station)}&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getStationStopp(station: string, limit: number = 20): Observable<ApiResponse<StationStoppData> | null> {
    return this.http.get<ApiResponse<StationStoppData>>(
      `${this.api}&run=station-stopp&station=${encodeURIComponent(station)}&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getJamforelse(period: number): Observable<ApiResponse<JamforelseData> | null> {
    return this.http.get<ApiResponse<JamforelseData>>(
      `${this.api}&run=jamforelse&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
