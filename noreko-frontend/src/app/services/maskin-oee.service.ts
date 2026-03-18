import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OeeOverviewData {
  days: number;
  from_date: string;
  to_date: string;
  total_oee_idag: number | null;
  basta_maskin: { namn: string; oee: number } | null;
  samsta_maskin: { namn: string; oee: number } | null;
  trend_diff: number | null;
  trend_direction: 'up' | 'down' | 'flat';
  oee_mal: number;
}

export interface OeeOverviewResponse {
  success: boolean;
  data: OeeOverviewData;
  timestamp: string;
}

export interface OeeMaskinItem {
  maskin_id: number;
  maskin_namn: string;
  tillganglighet: number;
  prestanda: number;
  kvalitet: number;
  oee: number;
  oee_mal: number;
  total_planerad: number;
  total_drifttid: number;
  total_stopptid: number;
  total_output: number;
  total_ok: number;
  total_kassation: number;
  kassation_pct: number;
}

export interface OeePerMaskinData {
  days: number;
  from_date: string;
  to_date: string;
  maskiner: OeeMaskinItem[];
}

export interface OeePerMaskinResponse {
  success: boolean;
  data: OeePerMaskinData;
  timestamp: string;
}

export interface OeeTrendSeries {
  maskin_id: number;
  maskin_namn: string;
  values: (number | null)[];
}

export interface OeeTrendData {
  days: number;
  from_date: string;
  to_date: string;
  maskin_id: number;
  dates: string[];
  series: OeeTrendSeries[];
  oee_mal: number;
}

export interface OeeTrendResponse {
  success: boolean;
  data: OeeTrendData;
  timestamp: string;
}

export interface OeeBenchmarkItem {
  maskin_id: number;
  maskin_namn: string;
  avg_oee: number;
  min_oee: number;
  max_oee: number;
  avg_t: number;
  avg_p: number;
  avg_k: number;
  oee_mal: number;
  over_mal: boolean;
  diff_vs_mal: number;
}

export interface OeeBenchmarkData {
  days: number;
  from_date: string;
  to_date: string;
  benchmark: OeeBenchmarkItem[];
}

export interface OeeBenchmarkResponse {
  success: boolean;
  data: OeeBenchmarkData;
  timestamp: string;
}

export interface OeeDetaljItem {
  maskin_id: number;
  maskin_namn: string;
  datum: string;
  planerad_tid_min: number;
  drifttid_min: number;
  stopptid_min: number;
  total_output: number;
  ok_output: number;
  kassation: number;
  ideal_cykeltid_sek: number;
  tillganglighet: number;
  prestanda: number;
  kvalitet: number;
  oee: number;
  kassation_pct: number;
}

export interface OeeDetaljData {
  days: number;
  from_date: string;
  to_date: string;
  maskin_id: number;
  detaljer: OeeDetaljItem[];
  total: number;
}

export interface OeeDetaljResponse {
  success: boolean;
  data: OeeDetaljData;
  timestamp: string;
}

export interface Maskin {
  id: number;
  namn: string;
  beskrivning: string;
}

export interface MaskinerResponse {
  success: boolean;
  data: { maskiner: Maskin[] };
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MaskinOeeService {
  private api = `${environment.apiUrl}?action=maskin-oee`;

  constructor(private http: HttpClient) {}

  getOverview(period: string): Observable<OeeOverviewResponse | null> {
    return this.http.get<OeeOverviewResponse>(
      `${this.api}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerMaskin(period: string): Observable<OeePerMaskinResponse | null> {
    return this.http.get<OeePerMaskinResponse>(
      `${this.api}&run=per-maskin&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(period: string, maskinId: number = 0): Observable<OeeTrendResponse | null> {
    const maskinParam = maskinId > 0 ? `&maskin_id=${maskinId}` : '';
    return this.http.get<OeeTrendResponse>(
      `${this.api}&run=trend&period=${period}${maskinParam}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getBenchmark(period: string): Observable<OeeBenchmarkResponse | null> {
    return this.http.get<OeeBenchmarkResponse>(
      `${this.api}&run=benchmark&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetalj(period: string, maskinId: number = 0): Observable<OeeDetaljResponse | null> {
    const maskinParam = maskinId > 0 ? `&maskin_id=${maskinId}` : '';
    return this.http.get<OeeDetaljResponse>(
      `${this.api}&run=detalj&period=${period}${maskinParam}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getMaskiner(): Observable<MaskinerResponse | null> {
    return this.http.get<MaskinerResponse>(
      `${this.api}&run=maskiner`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
}
