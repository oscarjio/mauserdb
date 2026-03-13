import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface StationerData {
  stationer: string[];
}

export interface KpiIdagData {
  station: string;
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  drifttid_h: number;
  drifttid_procent: number;
  planerad_h: number;
  total_ibc: number;
  ok_ibc: number;
  kasserade_ibc: number;
  kassationsgrad_pct: number;
  avg_cykeltid_sek: number;
}

export interface IbcRad {
  id: number;
  datum: string;
  ok: boolean;
  resultat: string;
  cykeltid_sek: number;
  cykeltid_fmt: string;
}

export interface SenasteIbcData {
  station: string;
  ibc: IbcRad[];
  antal: number;
}

export interface StoppRad {
  id: number;
  start_time: string;
  stop_time: string | null;
  varaktighet_sek: number;
  varaktighet_min: number;
  varaktighet_fmt: string;
  status: string;
}

export interface StopphistorikData {
  stopp: StoppRad[];
  antal: number;
  notat: string;
}

export interface OeeTrendDag {
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  drifttid_h: number;
}

export interface OeeTrendData {
  station: string;
  dagar: number;
  trend: OeeTrendDag[];
}

export interface RealtidOeeData {
  station: string;
  from_dt: string;
  to_dt: string;
  aktiv_nu: boolean;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  ok_ibc: number;
  kasserade_ibc: number;
  avg_cykeltid_sek: number;
  drifttid_sek: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class RebotlingStationsdetaljService {
  private api = `${environment.apiUrl}?action=rebotling-stationsdetalj`;

  constructor(private http: HttpClient) {}

  getStationer(): Observable<ApiResponse<StationerData> | null> {
    return this.http.get<ApiResponse<StationerData>>(
      `${this.api}&run=stationer`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getKpiIdag(station: string): Observable<ApiResponse<KpiIdagData> | null> {
    return this.http.get<ApiResponse<KpiIdagData>>(
      `${this.api}&run=kpi-idag&station=${encodeURIComponent(station)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getSenasteIbc(station: string, limit = 20): Observable<ApiResponse<SenasteIbcData> | null> {
    return this.http.get<ApiResponse<SenasteIbcData>>(
      `${this.api}&run=senaste-ibc&station=${encodeURIComponent(station)}&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getStopphistorik(station: string, limit = 20): Observable<ApiResponse<StopphistorikData> | null> {
    return this.http.get<ApiResponse<StopphistorikData>>(
      `${this.api}&run=stopphistorik&station=${encodeURIComponent(station)}&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getOeeTrend(station: string, dagar = 30): Observable<ApiResponse<OeeTrendData> | null> {
    return this.http.get<ApiResponse<OeeTrendData>>(
      `${this.api}&run=oee-trend&station=${encodeURIComponent(station)}&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getRealtidOee(station: string): Observable<ApiResponse<RealtidOeeData> | null> {
    return this.http.get<ApiResponse<RealtidOeeData>>(
      `${this.api}&run=realtid-oee&station=${encodeURIComponent(station)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
