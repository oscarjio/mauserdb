import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface WeeklyReasonRow {
  reason: string;
  count: number;
  total_minutes: number;
}

export interface WeeklyRow {
  week: string;
  week_label: string;
  reasons: WeeklyReasonRow[];
  total_count: number;
  total_minutes: number;
}

export interface WeeklyData {
  weeks: number;
  veckonycklar: string[];
  top_reasons: string[];
  veckor: WeeklyRow[];
  total_stopp_senaste_vecka: number;
  total_min_senaste_vecka: number;
}

export interface WeeklyResponse {
  success: boolean;
  data: WeeklyData;
  timestamp: string;
}

export interface SummaryRow {
  reason: string;
  current_avg: number;
  previous_avg: number;
  change_pct: number;
  trend: 'increasing' | 'decreasing' | 'stable';
  total_current: number;
}

export interface SummaryData {
  summaries: SummaryRow[];
  most_improved: string | null;
  vanligaste_orsak: string | null;
  senaste_veckor: string[];
  foregaende_veckor: string[];
}

export interface SummaryResponse {
  success: boolean;
  data: SummaryData;
  timestamp: string;
}

export interface DetailRow {
  week: string;
  week_label: string;
  count: number;
  total_minutes: number;
}

export interface DetailData {
  reason: string;
  weeks: number;
  tidslinje: DetailRow[];
  total_count: number;
  total_minutes: number;
  avg_per_week: number;
  change_pct: number;
  trend: 'increasing' | 'decreasing' | 'stable';
}

export interface DetailResponse {
  success: boolean;
  data: DetailData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class StopporsakTrendService {
  private api = `${environment.apiUrl}?action=stopporsak-trend`;

  constructor(private http: HttpClient) {}

  getWeekly(weeks: number): Observable<WeeklyResponse | null> {
    return this.http.get<WeeklyResponse>(
      `${this.api}&run=weekly&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getSummary(weeks: number): Observable<SummaryResponse | null> {
    return this.http.get<SummaryResponse>(
      `${this.api}&run=summary&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getDetail(reason: string, weeks: number): Observable<DetailResponse | null> {
    return this.http.get<DetailResponse>(
      `${this.api}&run=detail&reason=${encodeURIComponent(reason)}&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
