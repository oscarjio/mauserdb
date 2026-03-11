import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export type SkiftTyp = 'dag' | 'kvall' | 'natt';

export interface SkiftData {
  skift: SkiftTyp;
  label: string;
  antal_pass: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  runtime_min: number;
  ibc_per_h: number;
  kvalitet_pct: number;
  oee_pct: number;
  tillganglighet_pct: number;
  stopptid_min: number;
  ar_bast: boolean;
  diff_fran_snitt_pct: number;
}

export interface ShiftComparisonData {
  period: number;
  from_date: string;
  to_date: string;
  skift: SkiftData[];
  basta_skift: SkiftTyp | null;
  snitt_ibc_h: number;
  sammanfattning: string;
}

export interface ShiftComparisonResponse {
  success: boolean;
  data: ShiftComparisonData;
  timestamp: string;
}

export interface VeckaData {
  vecka: string;
  label: string;
  dag: number | null;
  kvall: number | null;
  natt: number | null;
}

export interface ShiftTrendData {
  period: number;
  veckor: VeckaData[];
}

export interface ShiftTrendResponse {
  success: boolean;
  data: ShiftTrendData;
  timestamp: string;
}

export interface SkiftOperator {
  plats: number;
  operator_num: number;
  operator_namn: string;
  antal_ibc: number;
  avg_cykeltid_sek: number;
}

export interface ShiftOperatorsData {
  skift: SkiftTyp;
  label: string;
  period: number;
  from_date: string;
  to_date: string;
  operatorer: SkiftOperator[];
}

export interface ShiftOperatorsResponse {
  success: boolean;
  data: ShiftOperatorsData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class SkiftjamforelseService {
  private api = '../../noreko-backend/api.php?action=skiftjamforelse';

  constructor(private http: HttpClient) {}

  getShiftComparison(period: number): Observable<ShiftComparisonResponse | null> {
    return this.http.get<ShiftComparisonResponse>(
      `${this.api}&run=shift-comparison&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getShiftTrend(period: number): Observable<ShiftTrendResponse | null> {
    return this.http.get<ShiftTrendResponse>(
      `${this.api}&run=shift-trend&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getShiftOperators(shift: SkiftTyp, period: number): Observable<ShiftOperatorsResponse | null> {
    return this.http.get<ShiftOperatorsResponse>(
      `${this.api}&run=shift-operators&shift=${shift}&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
