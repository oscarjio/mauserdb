import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface StationOee {
  namn: string;
  oee_pct: number;
}

export interface SammanfattningData {
  oee_idag_pct: number;
  oee_7d_pct: number;
  oee_30d_pct: number;
  basta_station: StationOee | null;
  samsta_station: StationOee | null;
  trend: 'up' | 'down' | 'stable';
  tillganglighet_idag_pct: number;
  prestanda_idag_pct: number;
  kvalitet_idag_pct: number;
}

export interface SammanfattningResponse {
  success: boolean;
  data: SammanfattningData;
  timestamp: string;
}

export interface StationRow {
  station_id: number;
  station_namn: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  ok_ibc: number;
  delta_pct: number;
  trend: 'up' | 'down' | 'stable';
  ranking: number;
}

export interface PerStationData {
  stationer: StationRow[];
  days: number;
  from_date: string;
  to_date: string;
}

export interface PerStationResponse {
  success: boolean;
  data: PerStationData;
  timestamp: string;
}

export interface TrendPoint {
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  oee_ma7: number | null;
}

export interface TrendDataResult {
  trend: TrendPoint[];
  avg_oee: number;
  max_oee: number;
  min_oee: number;
  world_class_pct: number;
  typical_pct: number;
  days: number;
  station: number | null;
}

export interface TrendResponse {
  success: boolean;
  data: TrendDataResult;
  timestamp: string;
}

export interface Flaskhals {
  station_id: number;
  station_namn: string;
  oee_pct: number;
  orsak: string;
  orsak_pct: number;
  atgardsforslag: string;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  stopp_info: string | null;
}

export interface FlaskhalserData {
  flaskhalsar: Flaskhals[];
  days: number;
  from_date: string;
  to_date: string;
}

export interface FlaskhalserResponse {
  success: boolean;
  data: FlaskhalserData;
  timestamp: string;
}

export interface JamforelseStation {
  station_id: number;
  station_namn: string;
  period1_oee: number;
  period2_oee: number;
  delta: number;
  forbattrad: boolean;
  forsamrad: boolean;
  period1_t: number;
  period1_p: number;
  period1_k: number;
  period2_t: number;
  period2_p: number;
  period2_k: number;
}

export interface JamforelsePeriod {
  from: string;
  to: string;
  oee_pct: number;
}

export interface JamforelseData {
  stationer: JamforelseStation[];
  period1: JamforelsePeriod;
  period2: JamforelsePeriod;
  total_delta: number;
}

export interface JamforelseResponse {
  success: boolean;
  data: JamforelseData;
  timestamp: string;
}

export interface PrediktionPunkt {
  datum: string;
  oee_pct: number;
  oee_ma7?: number | null;
}

export interface PrediktionData {
  historisk: PrediktionPunkt[];
  prediktion: PrediktionPunkt[];
  slope: number;
  intercept: number;
  r2: number;
  trend: 'up' | 'down' | 'stable';
  medel_30d: number;
}

export interface PrediktionResponse {
  success: boolean;
  data: PrediktionData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OeeTrendanalysService {
  private api = `${environment.apiUrl}?action=oee-trendanalys`;

  constructor(private http: HttpClient) {}

  getSammanfattning(): Observable<SammanfattningResponse | null> {
    return this.http.get<SammanfattningResponse>(
      `${this.api}&run=sammanfattning`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerStation(days: number): Observable<PerStationResponse | null> {
    return this.http.get<PerStationResponse>(
      `${this.api}&run=per-station&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(days: number, station?: number): Observable<TrendResponse | null> {
    let url = `${this.api}&run=trend&days=${days}`;
    if (station) url += `&station=${station}`;
    return this.http.get<TrendResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getFlaskhalsar(days: number): Observable<FlaskhalserResponse | null> {
    return this.http.get<FlaskhalserResponse>(
      `${this.api}&run=flaskhalsar&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getJamforelse(days: number): Observable<JamforelseResponse | null> {
    return this.http.get<JamforelseResponse>(
      `${this.api}&run=jamforelse&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPrediktion(): Observable<PrediktionResponse | null> {
    return this.http.get<PrediktionResponse>(
      `${this.api}&run=prediktion`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
