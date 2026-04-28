import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KostnadOverview {
  kostnad_per_ibc_idag: number;
  totalkostnad_idag: number;
  ibc_ok_idag: number;
  ibc_ej_ok_idag: number;
  trend_pct: number;
  trend_riktning: 'uppat' | 'nedat' | 'stabil';
  kostnad_per_ibc_forr_vecka: number;
  kassationskostnad_idag: number;
  kassation_andel_pct: number;
}

export interface KostnadOverviewResponse {
  success: boolean;
  data: KostnadOverview;
}

export interface KostnadBreakdown {
  period: string;
  from: string;
  to: string;
  energi: number;
  bemanning: number;
  material: number;
  kassation: number;
  overhead: number;
  total: number;
  kostnad_per_ibc: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  drift_timmar: number;
}

export interface KostnadBreakdownResponse {
  success: boolean;
  data: KostnadBreakdown;
}

export interface TrendDay {
  date: string;
  kostnad_per_ibc: number;
  total_kostnad: number;
  ibc_ok: number;
  ibc_ej_ok: number;
}

export interface KostnadTrend {
  period: number;
  from: string;
  to: string;
  snitt: number;
  trend: TrendDay[];
}

export interface KostnadTrendResponse {
  success: boolean;
  data: KostnadTrend;
}

export interface DailyTableRow {
  date: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  total_kostnad: number;
  kostnad_per_ibc: number;
  kassationskostnad: number;
  stopp_minuter: number;
}

export interface DailyTable {
  from: string;
  to: string;
  rows: DailyTableRow[];
  total_ibc_ok: number;
  total_kostnad: number;
  snitt_per_ibc: number;
}

export interface DailyTableResponse {
  success: boolean;
  data: DailyTable;
}

export interface SkiftRow {
  dag: string;
  skiftraknare: number;
  label: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  kostnad_per_ibc: number;
  total_kostnad: number;
}

export interface ShiftComparison {
  period: string;
  from: string;
  to: string;
  skift: SkiftRow[];
}

export interface ShiftComparisonResponse {
  success: boolean;
  data: ShiftComparison;
}

export interface KonfigFaktor {
  faktor: string;
  label: string;
  varde: number;
  enhet: string;
}

export interface KostnadConfigResponse {
  success: boolean;
  config: KonfigFaktor[];
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionskostnadService {
  private api = `${environment.apiUrl}?action=produktionskostnad`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<KostnadOverviewResponse | null> {
    return this.http.get<KostnadOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getBreakdown(period: string = 'dag', date?: string): Observable<KostnadBreakdownResponse | null> {
    let url = `${this.api}&run=breakdown&period=${period}`;
    if (date) url += `&date=${date}`;
    return this.http.get<KostnadBreakdownResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(period: number = 30): Observable<KostnadTrendResponse | null> {
    return this.http.get<KostnadTrendResponse>(
      `${this.api}&run=trend&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDailyTable(from?: string, to?: string): Observable<DailyTableResponse | null> {
    let url = `${this.api}&run=daily-table`;
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    return this.http.get<DailyTableResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getShiftComparison(period: string = 'dag', date?: string): Observable<ShiftComparisonResponse | null> {
    let url = `${this.api}&run=shift-comparison&period=${period}`;
    if (date) url += `&date=${date}`;
    return this.http.get<ShiftComparisonResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getConfig(): Observable<KostnadConfigResponse | null> {
    return this.http.get<KostnadConfigResponse>(
      `${this.api}&run=config`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  updateConfig(items: { faktor: string; varde: number }[]): Observable<any> {
    return this.http.post(
      `${this.api}&run=update-config`,
      items,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(err => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }
}
