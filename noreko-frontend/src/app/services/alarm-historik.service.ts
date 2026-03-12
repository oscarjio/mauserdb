import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export type AlarmSeverity = 'critical' | 'warning' | 'info';
export type AlarmStatus   = 'active' | 'resolved';

export interface Alarm {
  id: string;
  datum: string;
  tid: string;
  typ: string;
  severity: AlarmSeverity;
  beskrivning: string;
  varaktighet_min: number | null;
  status: AlarmStatus;
  kalla: string;
}

export interface AlarmListData {
  alarms: Alarm[];
  count: number;
  days: number;
  from_date: string;
  to_date: string;
}

export interface AlarmListResponse {
  success: boolean;
  data: AlarmListData;
  timestamp: string;
}

export interface AlarmSummaryData {
  days: number;
  from_date: string;
  to_date: string;
  total: number;
  critical: number;
  warning: number;
  info: number;
  per_typ: Record<string, number>;
  vanligast_typ: string | null;
  snitt_per_dag: number;
  dagar_med_larm: number;
}

export interface AlarmSummaryResponse {
  success: boolean;
  data: AlarmSummaryData;
  timestamp: string;
}

export interface TimelineDataset {
  label: string;
  data: number[];
  backgroundColor: string;
  borderColor: string;
  borderWidth: number;
  stack: string;
}

export interface AlarmTimelineData {
  labels: string[];
  dates: string[];
  datasets: TimelineDataset[];
  har_data: boolean;
  days: number;
}

export interface AlarmTimelineResponse {
  success: boolean;
  data: AlarmTimelineData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class AlarmHistorikService {
  private api = `${environment.apiUrl}?action=alarm-historik`;

  constructor(private http: HttpClient) {}

  getList(
    days: number,
    status: string = 'all',
    severity: string = 'all',
    typ: string = 'all'
  ): Observable<AlarmListResponse | null> {
    const url = `${this.api}&run=list&days=${days}&status=${encodeURIComponent(status)}&severity=${encodeURIComponent(severity)}&typ=${encodeURIComponent(typ)}`;
    return this.http.get<AlarmListResponse>(url, { withCredentials: true }).pipe(
      timeout(20000),
      catchError(() => of(null))
    );
  }

  getSummary(days: number): Observable<AlarmSummaryResponse | null> {
    return this.http.get<AlarmSummaryResponse>(
      `${this.api}&run=summary&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(20000),
      catchError(() => of(null))
    );
  }

  getTimeline(days: number): Observable<AlarmTimelineResponse | null> {
    return this.http.get<AlarmTimelineResponse>(
      `${this.api}&run=timeline&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(20000),
      catchError(() => of(null))
    );
  }
}
