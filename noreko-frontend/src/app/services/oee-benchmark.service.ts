import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface CurrentOeeData {
  oee: number;
  oee_pct: number;
  tillganglighet: number;
  tillganglighet_pct: number;
  prestanda: number;
  prestanda_pct: number;
  kvalitet: number;
  kvalitet_pct: number;
  drifttid_h: number;
  stopptid_h: number;
  schema_h: number;
  total_ibc: number;
  ok_ibc: number;
  status: string;
  color: string;
  days: number;
  from_date: string;
  to_date: string;
}

export interface CurrentOeeResponse {
  success: boolean;
  data: CurrentOeeData;
}

export interface BenchmarkItem {
  namn: string;
  mal: number;
  mal_pct: number;
  gap: number;
  gap_pct: number;
  over_target: boolean;
  color: string;
}

export interface BenchmarkData {
  oee_pct: number;
  benchmarks: BenchmarkItem[];
  lagsta_faktor: string;
  lagsta_faktor_pct: number;
  forbattringsforslag: string[];
  days: number;
}

export interface BenchmarkResponse {
  success: boolean;
  data: BenchmarkData;
}

export interface TrendPoint {
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
}

export interface TrendData {
  trend: TrendPoint[];
  avg_oee: number;
  max_oee: number;
  min_oee: number;
  world_class_pct: number;
  typical_pct: number;
  days: number;
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
}

export interface SparkPoint {
  datum: string;
  pct: number;
}

export interface FaktorItem {
  id: string;
  namn: string;
  visningsnamn: string;
  pct: number;
  prev_pct: number;
  trend: 'up' | 'down' | 'flat';
  forklaring: string;
  drifttid_h?: number;
  stopptid_h?: number;
  total_ibc?: number;
  ideal_ibc?: number;
  ok_ibc?: number;
  kasserade?: number;
  icon: string;
  color: string;
}

export interface BreakdownData {
  faktorer: FaktorItem[];
  spark: { tillganglighet: SparkPoint[]; prestanda: SparkPoint[]; kvalitet: SparkPoint[] };
  oee_pct: number;
  prev_oee_pct: number;
  oee_trend: 'up' | 'down' | 'flat';
  days: number;
}

export interface BreakdownResponse {
  success: boolean;
  data: BreakdownData;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OeeBenchmarkService {
  private api = `${environment.apiUrl}?action=oee-benchmark`;

  constructor(private http: HttpClient) {}

  getCurrentOee(days: number = 30): Observable<CurrentOeeResponse | null> {
    return this.http.get<CurrentOeeResponse>(
      `${this.api}&run=current-oee&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getBenchmark(days: number = 30): Observable<BenchmarkResponse | null> {
    return this.http.get<BenchmarkResponse>(
      `${this.api}&run=benchmark&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getTrend(days: number = 30): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getBreakdown(days: number = 30): Observable<BreakdownResponse | null> {
    return this.http.get<BreakdownResponse>(
      `${this.api}&run=breakdown&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
