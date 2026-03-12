import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface WaterfallSegment {
  id: string;
  label: string;
  timmar: number;
  procent: number;
  typ: 'total' | 'forlust' | 'effektiv';
  farg: string;
  base: number;
  bar_start: number;
  bar_slut: number;
}

export interface WaterfallData {
  segments: WaterfallSegment[];
  total_timmar: number;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  ok_ibc: number;
  kasserade: number;
  dag_count: number;
  days: number;
  from_date: string;
  to_date: string;
}

export interface WaterfallDataResponse {
  success: boolean;
  data: WaterfallData;
  timestamp: string;
}

export interface OeeSummaryData {
  days: number;
  from_date: string;
  to_date: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  oee_trend: number;
  tillganglighet_trend: number;
  prestanda_trend: number;
  kvalitet_trend: number;
  oee_klass: string;
  total_ibc: number;
  ok_ibc: number;
  kasserade: number;
  dag_count: number;
}

export interface OeeSummaryResponse {
  success: boolean;
  data: OeeSummaryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OeeWaterfallService {
  private api = `${environment.apiUrl}?action=oee-waterfall`;

  constructor(private http: HttpClient) {}

  getWaterfallData(days: number): Observable<WaterfallDataResponse | null> {
    return this.http.get<WaterfallDataResponse>(
      `${this.api}&run=waterfall-data&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getSummary(days: number): Observable<OeeSummaryResponse | null> {
    return this.http.get<OeeSummaryResponse>(
      `${this.api}&run=summary&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
