import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface HeatmapCell {
  timme: number;
  antal: number;
  status: 'hog' | 'lag' | 'stopp' | 'utanfor';
}

export interface HeatmapRad {
  datum: string;
  veckodag: string;
  datum_kort: string;
  celler: HeatmapCell[];
  total_ibc: number;
  aktiva_timmar: number;
  max_timmar: number;
  drifttid_pct: number;
}

export interface HeatmapData {
  dagar: number;
  from_date: string;
  to_date: string;
  timmar: number[];
  rader: HeatmapRad[];
}

export interface KpiData {
  vecka_drifttid: number;
  vecka_drifttid_str: string;
  vecka_ibc: number;
  snitt_daglig_drifttid: number;
  snitt_str: string;
  snitt_pct: number;
  basta_dag: string | null;
  basta_dag_timmar: number;
  samsta_dag: string | null;
  samsta_dag_timmar: number;
  max_timmar_per_dag: number;
  dagar: number;
  from_date: string;
  to_date: string;
}

export interface DagDetaljTimme {
  timme: number;
  timme_str: string;
  antal: number;
  status: 'hog' | 'lag' | 'stopp' | 'utanfor';
}

export interface DagDetaljData {
  datum: string;
  veckodag: string;
  datum_kort: string;
  timmar: DagDetaljTimme[];
  total_ibc: number;
  aktiva_timmar: number;
  max_timmar: number;
  drifttid_pct: number;
}

export interface StationOption {
  id: string;
  namn: string;
}

export interface StationerData {
  stationer: StationOption[];
}

// ---- Response wrapper ----

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MaskinDrifttidService {
  private api = `${environment.apiUrl}?action=maskin-drifttid`;

  constructor(private http: HttpClient) {}

  getHeatmap(dagar: number): Observable<ApiResponse<HeatmapData> | null> {
    return this.http.get<ApiResponse<HeatmapData>>(
      `${this.api}&run=heatmap&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getKpi(dagar: number): Observable<ApiResponse<KpiData> | null> {
    return this.http.get<ApiResponse<KpiData>>(
      `${this.api}&run=kpi&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDagDetalj(datum: string): Observable<ApiResponse<DagDetaljData> | null> {
    return this.http.get<ApiResponse<DagDetaljData>>(
      `${this.api}&run=dag-detalj&datum=${encodeURIComponent(datum)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStationer(): Observable<ApiResponse<StationerData> | null> {
    return this.http.get<ApiResponse<StationerData>>(
      `${this.api}&run=stationer`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
