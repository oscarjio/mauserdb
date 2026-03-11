import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export interface DagData {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  kvalitet: number;
  farg: 'gron' | 'gul' | 'rod' | 'ingen';
  ibc_h: number | null;
  har_data: boolean;
}

export interface VeckoData {
  vecka: number;
  dagar: string[];
  snitt_ibc: number;
  snitt_kval: number;
  foreg_snitt: number | null;
  trend: 'upp' | 'ner' | 'stabil' | null;
}

export interface MonthlySummary {
  totalt_ibc: number;
  snitt_kvalitet: number;
  basta_dag: string | null;
  samsta_dag: string | null;
  grona_dagar: number;
  gula_dagar: number;
  roda_dagar: number;
  dagar_med_data: number;
}

export interface MonthData {
  year: number;
  month: number;
  from_date: string;
  to_date: string;
  mal: number;
  dagar: { [datum: string]: DagData };
  summary: MonthlySummary;
  veckor: VeckoData[];
}

export interface MonthDataResponse {
  success: boolean;
  data: MonthData;
  timestamp: string;
}

export interface TopOperator {
  rank: number;
  operator_id: number;
  namn: string;
  ibc_ok: number;
}

export interface Stopporsak {
  orsak: string;
  sekunder: number;
  minuter: number;
  antal: number;
}

export interface DayDetail {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  kvalitet: number;
  ibc_h: number | null;
  drifttid: number;
  stopptid: number;
  oee: number | null;
  topp5: TopOperator[];
  stopporsaker: Stopporsak[];
}

export interface DayDetailResponse {
  success: boolean;
  data: DayDetail;
  timestamp: string;
}

@Injectable({ providedIn: 'root' })
export class ProduktionskalenderService {
  private readonly apiBase = '/api/api.php';

  constructor(private http: HttpClient) {}

  getMonthData(year: number, month: number): Observable<MonthDataResponse | null> {
    const url = `${this.apiBase}?action=produktionskalender&run=month-data&year=${year}&month=${month}`;
    return this.http.get<MonthDataResponse>(url, { withCredentials: true }).pipe(
      timeout(20000),
      catchError(() => of(null))
    );
  }

  getDayDetail(date: string): Observable<DayDetailResponse | null> {
    const url = `${this.apiBase}?action=produktionskalender&run=day-detail&date=${date}`;
    return this.http.get<DayDetailResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
