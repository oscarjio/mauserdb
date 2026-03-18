import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SkiftStart {
  date: string;
  shift: string;
  shift_start: string;
  tid_till_forsta_ibc: number;
  ibc_forsta_timme: number;
  intervals: number[];
  bedomning: 'snabb' | 'normal' | 'langssam';
}

export interface AnalysData {
  period: number;
  from_date: string;
  to_date: string;
  snitt_tid_till_forsta: number | null;
  snabbaste_start: number | null;
  langsamma_start: number | null;
  rampup_pct_30min: number;
  avg_kurva: number[];
  interval_labels: string[];
  total_shifts_med_data: number;
  skift_starter: SkiftStart[];
}

export interface AnalysResponse {
  success: boolean;
  data: AnalysData;
  timestamp: string;
}

export interface TrendPoint {
  date: string;
  snitt_tid_till_forsta: number;
  min_tid: number;
  max_tid: number;
  antal_skift: number;
}

export interface TrendData {
  period: number;
  from_date: string;
  to_date: string;
  trend: TrendPoint[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ForstaTimmeAnalysService {
  private api = `${environment.apiUrl}?action=forsta-timme-analys`;

  constructor(private http: HttpClient) {}

  getAnalysis(period: number): Observable<AnalysResponse | null> {
    return this.http.get<AnalysResponse>(
      `${this.api}&run=analysis&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getTrend(period: number): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
