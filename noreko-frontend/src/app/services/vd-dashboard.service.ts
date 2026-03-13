import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OversiktData {
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  ok_ibc: number;
  aktiva_operatorer: number;
  dagsmal: number;
  mal_procent: number;
  datum: string;
}

export interface AktivtStopp {
  id: number;
  station_id: number;
  station_namn: string;
  orsak: string;
  start_time: string;
  varaktighet_min: number;
}

export interface StoppNuData {
  aktiva_stopp: AktivtStopp[];
  antal_stopp: number;
  stoppade_stationer: any[];
  allt_kor: boolean;
}

export interface TopOperator {
  rank: number;
  user_id: number;
  operator_namn: string;
  total_ibc: number;
}

export interface TopOperatorerData {
  top_operatorer: TopOperator[];
  datum: string;
}

export interface StationOee {
  station_id: number;
  station_namn: string;
  oee_pct: number;
  total_ibc: number;
}

export interface StationOeeData {
  stationer: StationOee[];
  datum: string;
}

export interface TrendPunkt {
  datum: string;
  dag_kort: string;
  oee_pct: number;
  total_ibc: number;
}

export interface VeckotrendData {
  trend: TrendPunkt[];
}

export interface SkiftstatusData {
  skift: string;
  skift_start: string;
  skift_slut: string;
  kvar_timmar: number;
  kvar_minuter: number;
  ibc_aktuellt: number;
  ibc_forra: number;
  jamforelse: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class VdDashboardService {
  private api = `${environment.apiUrl}?action=vd-dashboard`;

  constructor(private http: HttpClient) {}

  getOversikt(): Observable<ApiResponse<OversiktData> | null> {
    return this.http.get<ApiResponse<OversiktData>>(
      `${this.api}&run=oversikt`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getStoppNu(): Observable<ApiResponse<StoppNuData> | null> {
    return this.http.get<ApiResponse<StoppNuData>>(
      `${this.api}&run=stopp-nu`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getTopOperatorer(): Observable<ApiResponse<TopOperatorerData> | null> {
    return this.http.get<ApiResponse<TopOperatorerData>>(
      `${this.api}&run=top-operatorer`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getStationOee(): Observable<ApiResponse<StationOeeData> | null> {
    return this.http.get<ApiResponse<StationOeeData>>(
      `${this.api}&run=station-oee`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getVeckotrend(): Observable<ApiResponse<VeckotrendData> | null> {
    return this.http.get<ApiResponse<VeckotrendData>>(
      `${this.api}&run=veckotrend`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getSkiftstatus(): Observable<ApiResponse<SkiftstatusData> | null> {
    return this.http.get<ApiResponse<SkiftstatusData>>(
      `${this.api}&run=skiftstatus`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
