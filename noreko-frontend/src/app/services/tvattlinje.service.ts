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
  cycle_time: number;  // Faktisk cykeltid i minuter
  target_cycle_time?: number;  // Mål cykeltid från produkt
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
}

