import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface PeriodOption {
  value: string;
  label: string;
}

export interface PerioderData {
  manader: PeriodOption[];
  kvartal: PeriodOption[];
}

export interface PerioderResponse {
  success: boolean;
  data: PerioderData;
  timestamp: string;
}

export interface PeriodInfo {
  typ: string;
  from: string;
  to: string;
  label: string;
  prev_from: string;
  prev_to: string;
  prev_label: string;
}

export interface PeriodKpi {
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  runtime_min: number;
  antal_skift: number;
  ibc_per_h: number;
  kvalitet_pct: number;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  stopptid_min: number;
  snitt_ibc_per_dag: number;
  dag_count: number;
}

export interface Jamforelse {
  oee_delta: number;
  ibc_delta: number;
  snitt_delta: number;
  stopptid_delta: number;
  kvalitet_delta: number;
}

export interface Flaskhals {
  station: string;
  oee_pct: number;
}

export interface TopOperator {
  op_num: number;
  namn: string;
  ibc_ok: number;
}

export interface RapportData {
  period: PeriodInfo;
  current: PeriodKpi;
  previous: PeriodKpi;
  jamforelse: Jamforelse;
  flaskhals: Flaskhals | null;
  top_operator: TopOperator | null;
  rapport_text: string;
}

export interface RapportResponse {
  success: boolean;
  data: RapportData;
  timestamp: string;
}

export interface TrendPoint {
  datum: string;
  oee_pct: number;
  ibc_ok: number;
  oee_ma7: number | null;
}

export interface TrendData {
  period: PeriodInfo;
  trend: TrendPoint[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

export interface OperatorRow {
  rank: number;
  op_num: number;
  namn: string;
  ibc_ok: number;
  ibc_total: number;
  oee_pct: number;
  ibc_per_h: number;
  kvalitet_pct: number;
  prev_ibc: number;
  ibc_delta: number;
  trend: 'up' | 'down' | 'stable';
}

export interface OperatorerData {
  period: PeriodInfo;
  operatorer: OperatorRow[];
}

export interface OperatorerResponse {
  success: boolean;
  data: OperatorerData;
  timestamp: string;
}

export interface StationRow {
  station_id: number;
  station_namn: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  ibc_ok: number;
  stopptid_min: number;
  oee_delta: number;
  trend: 'up' | 'down' | 'stable';
  prev_oee_pct: number;
}

export interface StationerData {
  period: PeriodInfo;
  stationer: StationRow[];
}

export interface StationerResponse {
  success: boolean;
  data: StationerData;
  timestamp: string;
}

export interface StopporsakRow {
  orsak: string;
  antal: number;
  total_min: number;
  total_h: number;
  andel_pct: number;
  cumulative_pct: number;
}

export interface StopporsakerData {
  period: PeriodInfo;
  stopporsaker: StopporsakRow[];
  total_min: number;
  total_h: number;
}

export interface StopporsakerResponse {
  success: boolean;
  data: StopporsakerData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class HistoriskSammanfattningService {
  private api = `${environment.apiUrl}?action=historisk-sammanfattning`;

  constructor(private http: HttpClient) {}

  private params(typ: string, period: string): string {
    return `&typ=${encodeURIComponent(typ)}&period=${encodeURIComponent(period)}`;
  }

  getPerioder(): Observable<PerioderResponse | null> {
    return this.http.get<PerioderResponse>(
      `${this.api}&run=perioder`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getRapport(typ: string, period: string): Observable<RapportResponse | null> {
    return this.http.get<RapportResponse>(
      `${this.api}&run=rapport${this.params(typ, period)}`,
      { withCredentials: true }
    ).pipe(timeout(20000), retry(1), catchError(() => of(null)));
  }

  getTrend(typ: string, period: string): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend${this.params(typ, period)}`,
      { withCredentials: true }
    ).pipe(timeout(20000), retry(1), catchError(() => of(null)));
  }

  getOperatorer(typ: string, period: string): Observable<OperatorerResponse | null> {
    return this.http.get<OperatorerResponse>(
      `${this.api}&run=operatorer${this.params(typ, period)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStationer(typ: string, period: string): Observable<StationerResponse | null> {
    return this.http.get<StationerResponse>(
      `${this.api}&run=stationer${this.params(typ, period)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStopporsaker(typ: string, period: string): Observable<StopporsakerResponse | null> {
    return this.http.get<StopporsakerResponse>(
      `${this.api}&run=stopporsaker${this.params(typ, period)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
