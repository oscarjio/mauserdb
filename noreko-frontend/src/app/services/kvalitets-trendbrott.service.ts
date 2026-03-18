import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface TrendbrottDailyItem {
  datum: string;
  kassation_pct: number;
  ok: number;
  ej_ok: number;
  total: number;
  ma7: number;
  avvikelse: boolean;
  avvikelse_sigma: number;
  avvikelse_typ: string | null;
}

export interface TrendbrottOverviewData {
  daily: TrendbrottDailyItem[];
  snitt_kassation: number;
  stddev: number;
  upper_bound: number;
  lower_bound: number;
  antal_avvikelser: number;
  senaste_avvikelse: {
    datum: string;
    kassation_pct: number;
    typ: string;
    sigma: number;
  } | null;
  period: number;
  trend: string;
}

export interface TrendbrottAlert {
  datum: string;
  kassation_pct: number;
  avvikelse_sigma: number;
  typ: string;
  ok: number;
  ej_ok: number;
  total: number;
  skift: any[];
  operators: { id: number; name: string }[];
}

export interface TrendbrottAlertsData {
  alerts: TrendbrottAlert[];
  period: number;
  snitt_kassation: number;
  stddev: number;
}

export interface TrendbrottSkiftDetail {
  skiftraknare: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid: number;
  operators: { id: number; name: string }[];
}

export interface TrendbrottOperatorDetail {
  id: number;
  name: string;
  ok: number;
  ej_ok: number;
  total: number;
  kassation_pct: number;
}

export interface TrendbrottStopReason {
  orsak: string;
  antal: number;
  minuter: number;
}

export interface TrendbrottDailyDetailData {
  datum: string;
  kassation_pct: number;
  ok: number;
  ej_ok: number;
  total: number;
  avvikelse: boolean;
  avvikelse_sigma: number;
  ref_snitt: number;
  ref_stddev: number;
  skift: TrendbrottSkiftDetail[];
  per_operator: TrendbrottOperatorDetail[];
  stopporsaker: TrendbrottStopReason[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
  error?: string;
}

@Injectable({ providedIn: 'root' })
export class KvalitetsTrendbrottService {
  private baseUrl = `${environment.apiUrl}?action=kvalitetstrendbrott`;

  constructor(private http: HttpClient) {}

  getOverview(period: number): Observable<ApiResponse<TrendbrottOverviewData> | null> {
    return this.http.get<ApiResponse<TrendbrottOverviewData>>(
      `${this.baseUrl}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getAlerts(period: number): Observable<ApiResponse<TrendbrottAlertsData> | null> {
    return this.http.get<ApiResponse<TrendbrottAlertsData>>(
      `${this.baseUrl}&run=alerts&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDailyDetail(date: string): Observable<ApiResponse<TrendbrottDailyDetailData> | null> {
    return this.http.get<ApiResponse<TrendbrottDailyDetailData>>(
      `${this.baseUrl}&run=daily-detail&date=${date}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
