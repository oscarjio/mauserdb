import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface TrendRad {
  date: string;
  ibc_count: number;
  drift_hours: number;
  ibc_per_hour: number | null;
  moving_avg_7d: number | null;
}

export interface TrendData {
  days: number;
  trend: TrendRad[];
  snitt_30d: number | null;
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

export interface DayRef {
  date: string;
  value: number;
}

export interface SummaryData {
  current: number | null;
  avg_7d: number | null;
  avg_30d: number | null;
  best_day: DayRef | null;
  worst_day: DayRef | null;
  trend: 'improving' | 'declining' | 'stable';
  change_pct: number | null;
}

export interface SummaryResponse {
  success: boolean;
  data: SummaryData;
  timestamp: string;
}

export interface SkiftRad {
  skift: string;
  label: string;
  ibc_count: number;
  drift_hours: number;
  ibc_per_hour: number | null;
  dagar: number;
  ar_bast: boolean;
}

export interface SkiftData {
  days: number;
  skift: SkiftRad[];
  basta_skift: string | null;
}

export interface SkiftResponse {
  success: boolean;
  data: SkiftData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class EffektivitetService {
  private api = `${environment.apiUrl}?action=effektivitet`;

  constructor(private http: HttpClient) {}

  getTrend(days: number): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(20000),
      catchError(() => of(null))
    );
  }

  getSummary(): Observable<SummaryResponse | null> {
    return this.http.get<SummaryResponse>(
      `${this.api}&run=summary`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getByShift(days: number): Observable<SkiftResponse | null> {
    return this.http.get<SkiftResponse>(
      `${this.api}&run=by-shift&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
