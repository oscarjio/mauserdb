import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KassationOverview {
  datum: string;
  total_kassation: number;
  kassation_pct: number;
  total_producerade: number;
  kassation_igar: number;
  kassation_pct_igar: number;
  trend: 'up' | 'down' | 'flat';
  trend_diff: number;
  varsta_station: string | null;
  varsta_station_antal: number;
}

export interface KassationStation {
  id: string;
  namn: string;
  order: number;
  kasserade: number;
  kass_andel: number;
  kass_pct: number;
}

export interface PerStationData {
  dagar: number;
  from_date: string;
  to_date: string;
  total_kassation: number;
  total_producerade: number;
  kassation_pct: number;
  snitt_per_station: number;
  stationer: KassationStation[];
}

export interface TopOrsak {
  orsak_id: number;
  orsak: string;
  antal: number;
  procent: number;
}

export interface StationOption {
  id: string;
  namn: string;
}

export interface TopOrsakerData {
  dagar: number;
  from_date: string;
  to_date: string;
  station: string | null;
  station_namn: string;
  stationer: StationOption[];
  orsaker: TopOrsak[];
}

export interface TrendSerie {
  station_id: string;
  station_namn: string;
  kass_andel: number;
  values: (number | null)[];
}

export interface TrendData {
  dagar: number;
  from_date: string;
  to_date: string;
  labels: string[];
  series: TrendSerie[];
}

export interface StationDetalj {
  station_id: string;
  station_namn: string;
  order: number;
  totalt: number;
  kasserade: number;
  kassation_pct: number;
  kassation_pct_foreg: number;
  top_orsak: string | null;
  trend: 'up' | 'down' | 'flat';
  trend_diff: number;
}

export interface DetaljerData {
  dagar: number;
  from_date: string;
  to_date: string;
  total_kassation: number;
  total_producerade: number;
  detaljer: StationDetalj[];
}

// ---- Response wrappers ----

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KassationsorsakPerStationService {
  private api = `${environment.apiUrl}?action=kassationsorsak-per-station`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<ApiResponse<KassationOverview> | null> {
    return this.http.get<ApiResponse<KassationOverview>>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getPerStation(dagar: number): Observable<ApiResponse<PerStationData> | null> {
    return this.http.get<ApiResponse<PerStationData>>(
      `${this.api}&run=per-station&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getTopOrsaker(dagar: number, station?: string): Observable<ApiResponse<TopOrsakerData> | null> {
    let url = `${this.api}&run=top-orsaker&dagar=${dagar}`;
    if (station) url += `&station=${encodeURIComponent(station)}`;
    return this.http.get<ApiResponse<TopOrsakerData>>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getTrend(dagar: number): Observable<ApiResponse<TrendData> | null> {
    return this.http.get<ApiResponse<TrendData>>(
      `${this.api}&run=trend&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getDetaljer(dagar: number): Observable<ApiResponse<DetaljerData> | null> {
    return this.http.get<ApiResponse<DetaljerData>>(
      `${this.api}&run=detaljer&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
