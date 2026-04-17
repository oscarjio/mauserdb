import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    on_rast?: boolean;
    lastUpdate: string | null;
  };
}

export interface TvattlinjeLiveStatsResponse {
  success: boolean;
  data: {
    ibcToday: number;
    ibcTarget: number;
    productionPercentage: number;
    utetemperatur: number | null;
  };
}

export interface ProductionCycle {
  datum: string;
  ibc_count: number;
  produktion_procent: number;
  skiftraknare: number;
  cycle_time: number | null;
  target_cycle_time?: number;
  // PLC-fält (finns när Modbus-läsning är aktiv)
  op1?: number | null;
  op2?: number | null;
  op3?: number | null;
  ibc_ok?: number | null;
  ibc_ej_ok?: number | null;
  omtvaatt?: number | null;
  runtime_plc?: number | null;
  rasttime?: number | null;
  lopnummer?: number | null;
}

export interface OnOffEvent {
  datum: string;
  running: boolean;
  runtime_today: number;
}

export interface RastEvent {
  datum: string;
  rast_status: number; // 0 = arbetar, 1 = rast
}

export interface StatisticsResponse {
  success: boolean;
  data: {
    cycles: ProductionCycle[];
    onoff_events: OnOffEvent[];
    rast_events: RastEvent[];
    summary: {
      total_cycles: number;
      avg_production_percent: number;
      avg_cycle_time: number;
      target_cycle_time: number;
      total_runtime_hours: number;
      net_runtime_minutes: number;
      total_rast_minutes: number;
      days_with_production: number;
    };
  };
}

export interface OeeTrendDay {
  dag: string;
  total_ibc: number;
  total_ok: number;
  total_ej_ok: number;
  oee_pct: number;
  skift_count: number;
}

export interface OeeTrendSummary {
  total_ibc: number;
  snitt_per_dag: number;
  snitt_oee_pct: number;
  basta_dag: string | null;
  basta_ibc: number;
}

export interface OeeTrendResponse {
  success: boolean;
  empty: boolean;
  message?: string;
  data: OeeTrendDay[];
  summary: OeeTrendSummary;
}

@Injectable({ providedIn: 'root' })
export class TvattlinjeService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<any> {
    return this.http.get<TvattlinjeLiveStatsResponse>(
      `${environment.apiUrl}?action=tvattlinje`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getRunningStatus(): Observable<any> {
    return this.http.get<LineStatusResponse>(
      `${environment.apiUrl}?action=tvattlinje&run=status`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStatistics(startDate: string, endDate: string): Observable<any> {
    return this.http.get<StatisticsResponse>(
      `${environment.apiUrl}?action=tvattlinje&run=statistics&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getOeeTrend(dagar: number = 30): Observable<any> {
    return this.http.get<OeeTrendResponse>(
      `${environment.apiUrl}?action=tvattlinje&run=oee-trend&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getSkiftrapportStatistik(startDate: string, endDate: string): Observable<any> {
    return this.http.get<any>(
      `${environment.apiUrl}?action=tvattlinje&run=skiftrapport-statistik&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPlcDiagnostics(startDate: string, endDate: string): Observable<any> {
    return this.http.get<any>(
      `${environment.apiUrl}?action=tvattlinje&run=plc-diagnostics&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    ).pipe(timeout(20000), retry(1), catchError(() => of(null)));
  }
}
