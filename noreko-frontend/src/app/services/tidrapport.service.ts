import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SammanfattningData {
  period: string;
  from_date: string;
  to_date: string;
  total_timmar: number;
  vecko_timmar: number;
  manad_timmar: number;
  antal_skift: number;
  snitt_per_skift: number;
  mest_aktiv: string | null;
  mest_aktiv_timmar: number;
  antal_operatorer: number;
}

export interface SammanfattningResponse {
  success: boolean;
  data: SammanfattningData;
  timestamp: string;
}

export interface OperatorRow {
  user_id: number;
  namn: string;
  antal_skift: number;
  total_timmar: number;
  snitt_per_skift: number;
  senaste_skift: string | null;
  formiddag: number;
  eftermiddag: number;
  natt: number;
  formiddag_pct: number;
  eftermiddag_pct: number;
  natt_pct: number;
}

export interface PerOperatorData {
  period: string;
  from_date: string;
  to_date: string;
  operatorer: OperatorRow[];
  total: number;
}

export interface PerOperatorResponse {
  success: boolean;
  data: PerOperatorData;
  timestamp: string;
}

export interface VeckodataDataset {
  label: string;
  data: number[];
  color: string;
}

export interface VeckodataData {
  from_date: string;
  to_date: string;
  veckor: number;
  dates: string[];
  datasets: VeckodataDataset[];
}

export interface VeckodataResponse {
  success: boolean;
  data: VeckodataData;
  timestamp: string;
}

export interface DetaljRow {
  id: number;
  operator_namn: string;
  user_id: number;
  datum: string;
  start_time: string;
  end_time: string;
  station: string;
  antal: number;
  timmar: number;
  skift_typ: string;
}

export interface DetaljerData {
  period: string;
  from_date: string;
  to_date: string;
  detaljer: DetaljRow[];
  total: number;
}

export interface DetaljerResponse {
  success: boolean;
  data: DetaljerData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class TidrapportService {
  private api = `${environment.apiUrl}?action=tidrapport`;

  constructor(private http: HttpClient) {}

  private periodParams(period: string, from?: string, to?: string): string {
    let params = `&period=${period}`;
    if (period === 'anpassat' && from && to) {
      params += `&from=${from}&to=${to}`;
    }
    return params;
  }

  getSammanfattning(period: string, from?: string, to?: string): Observable<SammanfattningResponse | null> {
    return this.http.get<SammanfattningResponse>(
      `${this.api}&run=sammanfattning${this.periodParams(period, from, to)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerOperator(period: string, from?: string, to?: string): Observable<PerOperatorResponse | null> {
    return this.http.get<PerOperatorResponse>(
      `${this.api}&run=per-operator${this.periodParams(period, from, to)}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getVeckodata(veckor: number = 4): Observable<VeckodataResponse | null> {
    return this.http.get<VeckodataResponse>(
      `${this.api}&run=veckodata&veckor=${veckor}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetaljer(period: string, from?: string, to?: string, operatorId?: number): Observable<DetaljerResponse | null> {
    let url = `${this.api}&run=detaljer${this.periodParams(period, from, to)}`;
    if (operatorId) {
      url += `&operator_id=${operatorId}`;
    }
    return this.http.get<DetaljerResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getExportCsvUrl(period: string, from?: string, to?: string, operatorId?: number): string {
    let url = `${this.api}&run=export-csv${this.periodParams(period, from, to)}`;
    if (operatorId) {
      url += `&operator_id=${operatorId}`;
    }
    return url;
  }
}
