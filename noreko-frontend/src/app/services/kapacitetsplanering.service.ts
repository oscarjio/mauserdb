import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface FlaskhalsFaktor {
  station: string;
  typ: string;
  antal_idag?: number;
  snitt_alla?: number;
  gap_pct?: number;
  avg_cykeltid_sek?: number;
  forklaring: string;
}

export interface KpiIdagData {
  datum: string;
  faktisk_idag: number;
  teormax_idag: number;
  utnyttjande_pct: number;
  antal_stationer: number;
  drifttid_h: number;
}

export interface KpiPeriodData {
  from_date: string;
  to_date: string;
  snitt_per_dag: number;
  teormax_per_dag: number;
  utnyttjande_snitt_pct: number;
  antal_stationer: number;
  optimal_cykeltid_sek: number;
  avg_cykeltid_sek: number;
  ibc_per_timme_optimal: number;
  prognos_vecka: number;
}

export interface KpiData {
  idag: KpiIdagData;
  period: KpiPeriodData;
  flaskhals: FlaskhalsFaktor;
}

export interface DagligKapacitetRad {
  datum: string;
  faktisk: number;
  teor_max: number;
  mal: number | null;
  outnyttjad: number;
  utnyttjande_pct: number;
  drifttid_h: number;
  antal_stationer: number;
}

export interface DagligKapacitetData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  dagdata: DagligKapacitetRad[];
  genomsnitt: number;
}

export interface StationUtnyttjandeRad {
  station: string;
  total_ibc: number;
  ok_ibc: number;
  aktiva_dagar: number;
  teor_max: number;
  utnyttjande_pct: number;
  kassationsgrad_pct: number;
}

export interface StationUtnyttjandeData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  drifttid_h: number;
  stationer: StationUtnyttjandeRad[];
  antal_stationer: number;
}

export interface StoppOrsakRad {
  namn: string;
  sek: number;
  min: number;
  andel_pct: number;
}

export interface StoppOrsakerData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  planerad_h: number;
  drifttid_h: number;
  stopp_h: number;
  antal_stopp: number;
  avg_stopp_min: number;
  orsaker: StoppOrsakRad[];
}

export interface TidFordelningRad {
  datum: string;
  produktiv_h: number;
  stopp_h: number;
  idle_h: number;
  drifttid_h: number;
  planerad_h: number;
  antal_ibc: number;
}

export interface TidFordelningData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  dagdata: TidFordelningRad[];
}

export interface VeckaRad {
  vecka: number;
  ar: number;
  from_datum: string;
  to_datum: string;
  total_ibc: number;
  max_kapacitet: number;
  utnyttjande_pct: number;
  trend: 'upp' | 'ned' | 'neutral';
  basta_dag: string | null;
  samsta_dag: string | null;
  basta_dag_antal: number;
  samsta_dag_antal: number;
}

export interface VeckoOversiktData {
  antal_veckor: number;
  veckor: VeckaRad[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KapacitetsplaneringService {
  private api = `${environment.apiUrl}?action=kapacitetsplanering`;

  constructor(private http: HttpClient) {}

  getKpi(): Observable<ApiResponse<KpiData> | null> {
    return this.http.get<ApiResponse<KpiData>>(
      `${this.api}&run=kpi`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getDagligKapacitet(period: number): Observable<ApiResponse<DagligKapacitetData> | null> {
    return this.http.get<ApiResponse<DagligKapacitetData>>(
      `${this.api}&run=daglig-kapacitet&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getStationUtnyttjande(period: number): Observable<ApiResponse<StationUtnyttjandeData> | null> {
    return this.http.get<ApiResponse<StationUtnyttjandeData>>(
      `${this.api}&run=station-utnyttjande&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getStopporsaker(period: number): Observable<ApiResponse<StoppOrsakerData> | null> {
    return this.http.get<ApiResponse<StoppOrsakerData>>(
      `${this.api}&run=stopporsaker&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getTidFordelning(period: number): Observable<ApiResponse<TidFordelningData> | null> {
    return this.http.get<ApiResponse<TidFordelningData>>(
      `${this.api}&run=tid-fordelning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getVeckoOversikt(): Observable<ApiResponse<VeckoOversiktData> | null> {
    return this.http.get<ApiResponse<VeckoOversiktData>>(
      `${this.api}&run=vecko-oversikt`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }
}
