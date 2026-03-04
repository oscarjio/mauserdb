import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    on_rast?: boolean;
    lastUpdate: string | null;
  };
}

export interface TvattlinjeLiveStatsResponse {
  success: boolean;
  data: {
    ibcToday: number;
    ibcTarget: number;
    productionPercentage: number;
    utetemperatur: number | null;
  };
}

export interface ProductionCycle {
  datum: string;
  ibc_count: number;
  produktion_procent: number;
  skiftraknare: number;
  cycle_time: number;
  target_cycle_time?: number;
}

export interface OnOffEvent {
  datum: string;
  running: boolean;
  runtime_today: number;
}

export interface StatisticsResponse {
  success: boolean;
  data: {
    cycles: ProductionCycle[];
    onoff_events: OnOffEvent[];
    summary: {
      total_cycles: number;
      avg_production_percent: number;
      avg_cycle_time: number;
      target_cycle_time: number;
      total_runtime_hours: number;
      days_with_production: number;
    };
  };
}

export interface ReportDayData {
  total_ibc: number;
  total_ok: number;
  total_ej_ok: number;
  kvalitet_pct: number;
  runtime_minutes: number;
  rast_minutes: number;
  ibc_per_hour: number;
  delta_ibc: number;
  prev_ibc: number;
  skift_count: number;
  skift_data: any[];
}

export interface ReportDayResponse {
  success: boolean;
  empty: boolean;
  message?: string;
  datum: string;
  data: ReportDayData;
}

export interface OeeTrendDay {
  dag: string;
  total_ibc: number;
  total_ok: number;
  total_ej_ok: number;
  oee_pct: number;
  skift_count: number;
}

export interface OeeTrendSummary {
  total_ibc: number;
  snitt_per_dag: number;
  snitt_oee_pct: number;
  basta_dag: string | null;
  basta_ibc: number;
}

export interface OeeTrendResponse {
  success: boolean;
  empty: boolean;
  message?: string;
  data: OeeTrendDay[];
  summary: OeeTrendSummary;
}

@Injectable({ providedIn: 'root' })
export class TvattlinjeService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<TvattlinjeLiveStatsResponse> {
    return this.http.get<TvattlinjeLiveStatsResponse>(
      '/noreko-backend/api.php?action=tvattlinje',
      { withCredentials: true }
    );
  }

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=tvattlinje&run=status',
      { withCredentials: true }
    );
  }

  getStatistics(startDate: string, endDate: string): Observable<StatisticsResponse> {
    return this.http.get<StatisticsResponse>(
      `/noreko-backend/api.php?action=tvattlinje&run=statistics&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    );
  }

  getReport(datum: string): Observable<ReportDayResponse> {
    return this.http.get<ReportDayResponse>(
      `/noreko-backend/api.php?action=tvattlinje&run=report&datum=${datum}`,
      { withCredentials: true }
    );
  }

  getOeeTrend(dagar: number = 30): Observable<OeeTrendResponse> {
    return this.http.get<OeeTrendResponse>(
      `/noreko-backend/api.php?action=tvattlinje&run=oee-trend&dagar=${dagar}`,
      { withCredentials: true }
    );
  }
}
