import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

const API = `${environment.apiUrl}?action=skiftrapport-export`;

// ---- Interfaces ----

export interface Produktion {
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  ibc_total: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  skift_start: string;
  skift_slut: string;
  antal_skiften: number;
}

export interface Cykeltider {
  avg_sek: number | null;
  min_sek: number | null;
  max_sek: number | null;
  antal_cykler: number;
}

export interface Drifttid {
  drifttid_min: number;
  stopptid_min: number;
  rast_min: number;
  planerad_min: number;
  drifttid_pct: number;
  stopptid_pct: number;
}

export interface OEE {
  oee_pct: number;
  tillganglighet: number;
  prestanda: number;
  kvalitet: number;
  teoretisk_max_ibc_per_h: number;
}

export interface Operator {
  op_num: number;
  namn: string;
  antal_ibc: number;
  avg_cykeltid: number;
}

export interface Trender {
  prev_datum: string;
  prev_ibc_ok: number;
  prev_kvalitet: number;
  prev_ibc_per_h: number;
  diff_ibc_ok_pct: number | null;
  diff_kvalitet: number | null;
  diff_ibc_per_h_pct: number | null;
}

export interface SkiftInfo {
  skiftraknare: number;
  skift_datum: string;
  skift_start: string;
  skift_slut: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  runtime_min: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  lopnummer_range?: string;
  lopnummer_count?: number;
}

export interface ReportData {
  datum: string;
  har_data: boolean;
  produktion: Produktion | null;
  cykeltider: Cykeltider | null;
  drifttid: Drifttid | null;
  oee: OEE | null;
  operatorer: Operator[];
  trender: Trender | null;
  skiften: SkiftInfo[];
}

export interface ReportResponse {
  success: boolean;
  error?: string;
  data: ReportData;
  timestamp: string;
}

export interface DagSummary {
  dag: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  runtime_min: number;
  stopptid_min: number;
  drifttid_pct: number;
  oee_pct: number;
  antal_skiften: number;
}

export interface MultiDaySumma {
  ibc_ok: number;
  ibc_total: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  snitt_ibc_per_dag: number;
}

export interface MultiDayData {
  start: string;
  end: string;
  antal_dagar: number;
  dagar: DagSummary[];
  summa: MultiDaySumma;
}

export interface MultiDayResponse {
  success: boolean;
  error?: string;
  data: MultiDayData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class SkiftrapportExportService {
  constructor(private http: HttpClient) {}

  getReportData(date: string): Observable<ReportResponse | null> {
    return this.http.get<ReportResponse>(
      `${API}&run=report-data&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getMultiDayData(startDate: string, endDate: string): Observable<MultiDayResponse | null> {
    return this.http.get<MultiDayResponse>(
      `${API}&run=multi-day&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
