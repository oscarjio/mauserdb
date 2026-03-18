import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface CurrentRateData {
  current_rate: number;
  current_count: number;
  prev_rate: number;
  trend: 'up' | 'down' | 'stable';
  diff: number;
  avg_4h: number;
  avg_today: number;
  avg_week: number;
  count_today: number;
  target: number;
  target_status: 'green' | 'yellow' | 'red';
  target_ratio: number;
  alert_active: boolean;
  alert_message: string | null;
}

export interface CurrentRateResponse {
  success: boolean;
  data: CurrentRateData;
  timestamp: string;
}

export interface HourlyEntry {
  hour: string;
  hour_label: string;
  ibc_count: number;
  rate: number;
  target: number;
  status: 'green' | 'yellow' | 'red';
}

export interface HourlyHistoryData {
  history: HourlyEntry[];
  target: number;
}

export interface HourlyHistoryResponse {
  success: boolean;
  data: HourlyHistoryData;
  timestamp: string;
}

export interface TargetData {
  target: number;
}

export interface TargetResponse {
  success: boolean;
  data: TargetData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsTaktService {
  private api = `${environment.apiUrl}?action=produktionstakt`;

  constructor(private http: HttpClient) {}

  getCurrentRate(): Observable<CurrentRateResponse | null> {
    return this.http.get<CurrentRateResponse>(
      `${this.api}&run=current-rate`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getHourlyHistory(): Observable<HourlyHistoryResponse | null> {
    return this.http.get<HourlyHistoryResponse>(
      `${this.api}&run=hourly-history`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getTarget(): Observable<TargetResponse | null> {
    return this.http.get<TargetResponse>(
      `${this.api}&run=get-target`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of(null))
    );
  }

  setTarget(target: number): Observable<TargetResponse | null> {
    return this.http.post<TargetResponse>(
      `${this.api}&run=set-target`,
      { target },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }
}
