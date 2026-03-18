import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface ReasonItem {
  reason: string;
  reason_id: number;
  antal: number;
  registreringar: number;
  andel: number;
}

export interface OverviewData {
  days: number;
  from_date: string;
  to_date: string;
  total_kasserade: number;
  total_producerade: number;
  kassationsgrad: number;
  prev_kassationsgrad: number;
  trend_diff: number;
  trend_direction: 'up' | 'down' | 'flat';
  top_reason: string | null;
  reasons: ReasonItem[];
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface ReasonEvent {
  id: number;
  datum: string;
  skiftraknare: string;
  antal: number;
  kommentar: string;
  registrerad_av: string;
  created_at: string;
  reason: string;
}

export interface ReasonDetailData {
  days: number;
  from_date: string;
  to_date: string;
  reason_id: number;
  events: ReasonEvent[];
  total: number;
}

export interface ReasonDetailResponse {
  success: boolean;
  data: ReasonDetailData;
  timestamp: string;
}

export interface TrendPoint {
  date: string;
  kasserade: number;
  producerade: number;
  kassationsgrad: number;
}

export interface TrendData {
  days: number;
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
export class KassationsDrilldownService {
  private api = `${environment.apiUrl}?action=kassations-drilldown`;

  constructor(private http: HttpClient) {}

  getOverview(days: number): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getReasonDetail(reasonId: number, days: number): Observable<ReasonDetailResponse | null> {
    return this.http.get<ReasonDetailResponse>(
      `${this.api}&run=reason-detail&reason=${reasonId}&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getTrend(days: number): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
