import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KtaOverviewData {
  days: number;
  from_date: string;
  to_date: string;
  total_rate: number;
  total_producerade: number;
  total_kasserade: number;
  worst_station: string | null;
  worst_station_rate: number;
  worst_operator: string | null;
  worst_operator_rate: number;
  prev_rate: number;
  trend_diff: number;
  trend_direction: 'up' | 'down' | 'flat';
}

export interface KtaOverviewResponse {
  success: boolean;
  data: KtaOverviewData;
  timestamp: string;
}

export interface StationTrendSeries {
  station_id: number;
  station: string;
  values: (number | null)[];
}

export interface StationTrendData {
  days: number;
  from_date: string;
  to_date: string;
  dates: string[];
  series: StationTrendSeries[];
}

export interface StationTrendResponse {
  success: boolean;
  data: StationTrendData;
  timestamp: string;
}

export interface OperatorItem {
  op_num: number;
  op_namn: string;
  total: number;
  kasserade: number;
  rate: number;
  avvikelse: number;
  trend_diff: number | null;
  trend_dir: 'up' | 'down' | 'flat';
}

export interface PerOperatorData {
  days: number;
  from_date: string;
  to_date: string;
  avg_rate: number;
  operators: OperatorItem[];
}

export interface PerOperatorResponse {
  success: boolean;
  data: PerOperatorData;
  timestamp: string;
}

export interface AlarmItem {
  typ: 'station' | 'operator';
  namn: string;
  rate: number;
  total: number;
  kasserade: number;
  niva: 'varning' | 'kritisk';
}

export interface AlarmData {
  days: number;
  from_date: string;
  to_date: string;
  warning_threshold: number;
  critical_threshold: number;
  alarms: AlarmItem[];
  total_alarms: number;
  critical_count: number;
  warning_count: number;
}

export interface AlarmResponse {
  success: boolean;
  data: AlarmData;
  timestamp: string;
}

export interface HeatmapWeek {
  yearweek: string;
  week_start: string;
  label: string;
}

export interface HeatmapRow {
  station_id: number;
  station: string;
  cells: (number | null)[];
}

export interface HeatmapData {
  days: number;
  from_date: string;
  to_date: string;
  weeks: HeatmapWeek[];
  rows: HeatmapRow[];
}

export interface HeatmapResponse {
  success: boolean;
  data: HeatmapData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KvalitetstrendanalysService {
  private api = `${environment.apiUrl}?action=kvalitetstrendanalys`;

  constructor(private http: HttpClient) {}

  getOverview(days: number): Observable<KtaOverviewResponse | null> {
    return this.http.get<KtaOverviewResponse>(
      `${this.api}&run=overview&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerStationTrend(days: number): Observable<StationTrendResponse | null> {
    return this.http.get<StationTrendResponse>(
      `${this.api}&run=per-station-trend&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerOperator(days: number): Observable<PerOperatorResponse | null> {
    return this.http.get<PerOperatorResponse>(
      `${this.api}&run=per-operator&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getAlarm(days: number, warning: number, critical: number): Observable<AlarmResponse | null> {
    return this.http.get<AlarmResponse>(
      `${this.api}&run=alarm&days=${days}&warning=${warning}&critical=${critical}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getHeatmap(days: number): Observable<HeatmapResponse | null> {
    return this.http.get<HeatmapResponse>(
      `${this.api}&run=heatmap&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
