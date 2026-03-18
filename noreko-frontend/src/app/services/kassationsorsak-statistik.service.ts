import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OverviewData {
  days: number;
  from_date: string;
  to_date: string;
  total_kasserade: number;
  total_producerade: number;
  kassationsgrad: number;
  prev_kasserade: number;
  prev_kassationsgrad: number;
  trend_diff: number;
  trend_direction: 'up' | 'down' | 'flat';
  top_reason: string | null;
  unique_reasons: number;
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface ParetoItem {
  orsak_id: number;
  orsak: string;
  antal: number;
  procent: number;
  kumulativ_pct: number;
}

export interface ParetoData {
  days: number;
  from_date: string;
  to_date: string;
  total: number;
  pareto: ParetoItem[];
}

export interface ParetoResponse {
  success: boolean;
  data: ParetoData;
  timestamp: string;
}

export interface TrendSeries {
  orsak_id: number;
  orsak: string;
  values: number[];
}

export interface TrendData {
  days: number;
  from_date: string;
  to_date: string;
  dates: string[];
  series: TrendSeries[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

export interface OperatorOrsak {
  orsak_id: number;
  orsak: string;
  antal: number;
  andel: number;
  snitt: number;
  diff: number;
}

export interface OperatorItem {
  op_id: number;
  op_namn: string;
  total: number;
  orsaker: OperatorOrsak[];
}

export interface PerOperatorData {
  days: number;
  from_date: string;
  to_date: string;
  operators: OperatorItem[];
  snitt_andelar: { [key: string]: number };
}

export interface PerOperatorResponse {
  success: boolean;
  data: PerOperatorData;
  timestamp: string;
}

export interface ShiftOrsak {
  orsak_id: number;
  orsak: string;
  antal: number;
  andel: number;
}

export interface ShiftItem {
  skift: string;
  total: number;
  orsaker: ShiftOrsak[];
}

export interface PerShiftData {
  days: number;
  from_date: string;
  to_date: string;
  shifts: ShiftItem[];
}

export interface PerShiftResponse {
  success: boolean;
  data: PerShiftData;
  timestamp: string;
}

export interface DrilldownEvent {
  id: number;
  datum: string;
  skift: string;
  antal: number;
  kommentar: string;
  operator: string;
  op_id: number;
  created_at: string;
}

export interface DrilldownDagItem {
  datum: string;
  antal: number;
}

export interface DrilldownData {
  days: number;
  from_date: string;
  to_date: string;
  orsak_id: number;
  events: DrilldownEvent[];
  dag_series: DrilldownDagItem[];
  total: number;
  total_antal: number;
}

export interface DrilldownResponse {
  success: boolean;
  data: DrilldownData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KassationsorsakStatistikService {
  private api = `${environment.apiUrl}?action=kassationsorsakstatistik`;

  constructor(private http: HttpClient) {}

  getOverview(days: number): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPareto(days: number): Observable<ParetoResponse | null> {
    return this.http.get<ParetoResponse>(
      `${this.api}&run=pareto&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(days: number): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerOperator(days: number): Observable<PerOperatorResponse | null> {
    return this.http.get<PerOperatorResponse>(
      `${this.api}&run=per-operator&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerShift(days: number): Observable<PerShiftResponse | null> {
    return this.http.get<PerShiftResponse>(
      `${this.api}&run=per-shift&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDrilldown(orsakId: number, days: number): Observable<DrilldownResponse | null> {
    return this.http.get<DrilldownResponse>(
      `${this.api}&run=drilldown&orsak=${orsakId}&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
