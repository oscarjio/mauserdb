import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KpiData {
  total_produktion: number;
  snitt_oee: number;
  kassationsrate: number;
  produktion_trend: number;
  oee_trend: number;
  kassation_trend: number;
  prev_total: number;
  prev_oee: number;
  prev_kassationsrate: number;
}

export interface KpiResponse {
  success: boolean;
  data: KpiData;
  timestamp: string;
}

export interface WeeklyChartData {
  months: number;
  from_date: string;
  to_date: string;
  labels: string[];
  values: (number | null)[];
  mal?: number;
  troskel?: number;
}

export interface WeeklyChartResponse {
  success: boolean;
  data: WeeklyChartData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class StatistikOverblickService {
  private api = `${environment.apiUrl}?action=statistik-overblick`;

  constructor(private http: HttpClient) {}

  getKpi(): Observable<KpiResponse | null> {
    return this.http.get<KpiResponse>(
      `${this.api}&run=kpi`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getProduktion(months: number): Observable<WeeklyChartResponse | null> {
    return this.http.get<WeeklyChartResponse>(
      `${this.api}&run=produktion&months=${months}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getOee(months: number): Observable<WeeklyChartResponse | null> {
    return this.http.get<WeeklyChartResponse>(
      `${this.api}&run=oee&months=${months}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getKassation(months: number): Observable<WeeklyChartResponse | null> {
    return this.http.get<WeeklyChartResponse>(
      `${this.api}&run=kassation&months=${months}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
