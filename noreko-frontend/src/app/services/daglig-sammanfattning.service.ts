import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface Produktion {
  har_data: boolean;
  total_ibc: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  runtime_min: number;
  skift_start: string | null;
  skift_slut: string | null;
  antal_skiften: number;
  skiften: SkiftData[];
  kvalitet_color: string;
  ibc_per_h_color: string;
  mal_ibc_per_timme: number;
}

export interface SkiftData {
  skiftraknare: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  runtime_min: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  skift_start: string | null;
  skift_slut: string | null;
}

export interface OeeSnapshot {
  oee: number;
  oee_pct: number;
  tillganglighet: number;
  tillganglighet_pct: number;
  prestanda: number;
  prestanda_pct: number;
  kvalitet: number;
  kvalitet_pct: number;
  drifttid_sek: number;
  stopptid_sek: number;
  drifttid_h: number;
  stopptid_h: number;
  status: string;
  color: string;
  label: string;
}

export interface TopOperator {
  plats: number;
  operator_num: number;
  operator_namn: string;
  antal_ibc: number;
  avg_cykeltid_sek: number;
  avg_cykeltid_min: number;
}

export interface StoppOrsak {
  kategori: string;
  ikon: string;
  antal: number;
  total_min: number;
}

export interface Stopptid {
  har_data: boolean;
  total_stopp_min: number;
  antal_stopp: number;
  top3_orsaker: StoppOrsak[];
}

export interface Trend {
  trend: 'up' | 'down' | 'flat';
  diff_pct: number;
  ibc_idag: number;
  ibc_foreg: number;
  foreg_datum: string;
}

export interface Veckosnitt {
  veckosnitt_ibc: number;
  antal_dagar: number;
}

export interface SenasteSkift {
  har_data: boolean;
  skiftraknare?: number;
  ibc_ok?: number;
  ibc_ej_ok?: number;
  runtime_min?: number;
  kvalitet_pct?: number;
  ibc_per_timme?: number;
  skift_start?: string | null;
  skift_slut?: string | null;
}

export interface DailySummaryData {
  datum: string;
  produktion: Produktion;
  oee: OeeSnapshot;
  top_operatorer: TopOperator[];
  stopptid: Stopptid;
  trend: Trend;
  veckosnitt: Veckosnitt;
  senaste_skift: SenasteSkift;
  status_text: string;
}

export interface DailySummaryResponse {
  success: boolean;
  data: DailySummaryData;
  timestamp: string;
}

export interface ComparisonDagData {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  oee_pct: number;
}

export interface ComparisonDiff {
  ibc_pct: number;
  oee_pct: number;
  trend: 'up' | 'down' | 'flat';
}

export interface ComparisonData {
  datum: string;
  idag: ComparisonDagData;
  igar: ComparisonDagData;
  foreg_vecka: ComparisonDagData;
  veckosnitt: Veckosnitt;
  diff_mot_igar: ComparisonDiff;
  diff_mot_foreg_vecka: ComparisonDiff;
}

export interface ComparisonResponse {
  success: boolean;
  data: ComparisonData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class DagligSammanfattningService {
  private api = `${environment.apiUrl}?action=daglig-sammanfattning`;

  constructor(private http: HttpClient) {}

  getDailySummary(date: string): Observable<DailySummaryResponse | null> {
    return this.http.get<DailySummaryResponse>(
      `${this.api}&run=daily-summary&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(20000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getComparison(date: string): Observable<ComparisonResponse | null> {
    return this.http.get<ComparisonResponse>(
      `${this.api}&run=comparison&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(20000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
