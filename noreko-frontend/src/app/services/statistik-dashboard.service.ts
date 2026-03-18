import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ====================================================
// Interfaces
// ====================================================

export interface DashboardDaySummary {
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid_h: number;
  drifttid_pct?: number;
  ibc_per_h?: number;
}

export interface DashboardWeekSummary {
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid_h: number;
  vecko_start?: string;
}

export interface ActiveOperator {
  operator_id: number;
  operator_name: string;
  senaste_datum: string;
}

export interface DashboardSummary {
  idag: DashboardDaySummary;
  igar: DashboardDaySummary;
  denna_vecka: DashboardWeekSummary;
  forra_veckan: DashboardWeekSummary;
  aktiv_operator: ActiveOperator | null;
  snitt_ibc_per_h: number;
  mal_ibc_per_h: number;
  mal_kassation: number;
  planerad_drift_h: number;
}

export interface ProductionTrendItem {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid_h: number;
  ibc_per_h: number;
}

export interface ProductionTrendData {
  daily: ProductionTrendItem[];
  period: number;
  snitt_ibc_dag: number;
  snitt_ibc_h: number;
  mal_ibc_per_h: number;
}

export interface BastaOperator {
  operator_id: number;
  operator_name: string;
  ibc_ok: number;
}

export interface DailyTableRow {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid_h: number;
  ibc_per_h: number;
  basta_operator: BastaOperator | null;
  fargklass: 'grön' | 'gul' | 'röd';
}

export interface DailyTableVeckosnitt {
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
  drifttid_h: number;
  ibc_per_h: number;
}

export interface DailyTableData {
  rows: DailyTableRow[];
  veckosnitt: DailyTableVeckosnitt;
  mal_kassation: number;
}

export interface StatusIndicator {
  status: 'grön' | 'gul' | 'röd';
  status_text: string;
  status_icon: string;
  problem: string[];
  varning: string[];
  kassation_idag: number;
  ibc_per_h_idag: number;
  stopp_min_idag: number;
  mal_ibc_per_h: number;
  mal_kassation: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
  error?: string;
}

// ====================================================
// Service
// ====================================================

@Injectable({ providedIn: 'root' })
export class StatistikDashboardService {
  private baseUrl = `${environment.apiUrl}?action=statistikdashboard`;

  constructor(private http: HttpClient) {}

  getSummary(): Observable<ApiResponse<DashboardSummary> | null> {
    return this.http.get<ApiResponse<DashboardSummary>>(
      `${this.baseUrl}&run=summary`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getProductionTrend(period: number = 30): Observable<ApiResponse<ProductionTrendData> | null> {
    return this.http.get<ApiResponse<ProductionTrendData>>(
      `${this.baseUrl}&run=production-trend&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDailyTable(): Observable<ApiResponse<DailyTableData> | null> {
    return this.http.get<ApiResponse<DailyTableData>>(
      `${this.baseUrl}&run=daily-table`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStatusIndicator(): Observable<ApiResponse<StatusIndicator> | null> {
    return this.http.get<ApiResponse<StatusIndicator>>(
      `${this.baseUrl}&run=status-indicator`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
