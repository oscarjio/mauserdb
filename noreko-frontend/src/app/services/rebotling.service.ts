import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface RebotlingLiveStatsResponse {
  success: boolean;
  data: {
    rebotlingToday: number;
    rebotlingTarget: number;
    rebotlingThisHour: number;
    hourlyTarget: number;
    ibcToday: number;
    productionPercentage: number;
    utetemperatur: number | null;
  };
}

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    lastUpdate: string | null;
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

export interface DayStats {
  date: string;
  total_cycles: number;
  avg_production_percent: number;
  total_runtime_minutes: number;
  shifts_count: number;
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
export class RebotlingService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<RebotlingLiveStatsResponse> {
    return this.http.get<RebotlingLiveStatsResponse>(
      '/noreko-backend/api.php?action=rebotling',
      { withCredentials: true }
    );
  }

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=rebotling&run=status',
      { withCredentials: true }
    );
  }

  getStatistics(startDate: string, endDate: string): Observable<StatisticsResponse> {
    return this.http.get<StatisticsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=statistics&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    );
  }

  getDayStats(date: string): Observable<any> {
    return this.http.get(
      `/noreko-backend/api.php?action=rebotling&run=day-stats&date=${date}`,
      { withCredentials: true }
    );
  }

  getCycleTrend(days: number = 30): Observable<CycleTrendResponse> {
    return this.http.get<CycleTrendResponse>(
      `/noreko-backend/api.php?action=rebotling&run=cycle-trend&days=${days}`,
      { withCredentials: true }
    );
  }

  getProductionReport(period: string = 'week'): Observable<any> {
    return this.http.get(
      `/noreko-backend/api.php?action=rebotling&run=report&period=${period}`,
      { withCredentials: true }
    );
  }

  downloadReportCSV(period: string = 'week'): void {
    window.open(`/noreko-backend/api.php?action=rebotling&run=report&period=${period}&format=csv`, '_blank');
  }

  getOEE(period: string = 'today'): Observable<OEEResponse> {
    return this.http.get<OEEResponse>(
      `/noreko-backend/api.php?action=rebotling&run=oee&period=${period}`,
      { withCredentials: true }
    );
  }
}

export interface CycleTrendResponse {
  success: boolean;
  data?: {
    daily: { dag: string; cycles: number; avg_runtime: number; avg_ibc_per_hour: number; total_ibc_ok: number; total_ibc_ej_ok: number; total_bur_ej_ok: number }[];
    moving_average: { dag: string; moving_avg: number }[];
    trend: 'increasing' | 'decreasing' | 'stable';
    avg_runtime: number;
    total_cycles: number;
    alert: { type: string; message: string; change_pct: number; current_avg: number; previous_avg: number } | null;
  };
  error?: string;
}

export interface OEEResponse {
  success: boolean;
  data?: {
    period: string;
    oee: number;
    availability: number;
    performance: number;
    quality: number;
    total_ibc: number;
    good_ibc: number;
    rejected_ibc: number;
    runtime_hours: number;
    operating_hours: number;
    cycles: number;
    world_class_benchmark: number;
  };
  error?: string;
}



