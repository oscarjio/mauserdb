import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface StoppageReason {
  id: number;
  code: string;
  name: string;
  category: 'planned' | 'unplanned';
  color: string;
}

export interface StoppageEntry {
  id: number;
  line: string;
  reason_id: number;
  reason_code: string;
  reason_name: string;
  category: string;
  color: string;
  start_time: string;
  end_time: string | null;
  duration_minutes: number | null;
  comment: string;
  user_id: number;
  user_name: string;
}

export interface StoppageStats {
  reasons: { code: string; name: string; category: string; color: string; count: number; total_minutes: number; avg_minutes: number }[];
  total_minutes: number;
  total_count: number;
  planned_minutes: number;
  unplanned_minutes: number;
  daily: { dag: string; total_minutes: number; count: number }[];
}

export interface ParetoOrsak {
  orsak: string;
  antal: number;
  total_minuter: number;
  pct: number;
  kumulativ_pct: number;
}

export interface ParetoData {
  orsaker: ParetoOrsak[];
  total_minuter: number;
  dagar: number;
}

export interface StoppageWeeklySummary {
  this_week: { count: number; total_minutes: number; avg_minutes: number };
  prev_week: { count: number; total_minutes: number; avg_minutes: number };
  daily_14: { dag: string; count: number; total_minutes: number }[];
}

@Injectable({ providedIn: 'root' })
export class StoppageService {
  private base = `${environment.apiUrl}?action=stoppage`;

  constructor(private http: HttpClient) {}

  getReasons(): Observable<{ success: boolean; data: StoppageReason[] } | null> {
    return this.http.get<{ success: boolean; data: StoppageReason[] }>(`${this.base}&run=reasons`, { withCredentials: true }).pipe(
      timeout(10000), retry(1), catchError(() => of(null))
    );
  }

  getStoppages(line: string = 'rebotling', period: string = 'week'): Observable<{ success: boolean; data: StoppageEntry[] } | null> {
    return this.http.get<{ success: boolean; data: StoppageEntry[] }>(`${this.base}&line=${line}&period=${period}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  getStats(line: string = 'rebotling', period: string = 'month'): Observable<{ success: boolean; data: StoppageStats } | null> {
    return this.http.get<{ success: boolean; data: StoppageStats }>(`${this.base}&run=stats&line=${line}&period=${period}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  create(entry: { line: string; reason_id: number; start_time: string; end_time?: string; comment?: string }): Observable<any> {
    return this.http.post<any>(this.base, { action: 'create', ...entry }, { withCredentials: true }).pipe(
      timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Nätverksfel' }))
    );
  }

  update(id: number, data: any): Observable<any> {
    return this.http.post<any>(this.base, { action: 'update', id, ...data }, { withCredentials: true }).pipe(
      timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Nätverksfel' }))
    );
  }

  delete(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'delete', id }, { withCredentials: true }).pipe(
      timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Nätverksfel' }))
    );
  }

  getWeeklySummary(line: string = 'rebotling'): Observable<{ success: boolean; data: StoppageWeeklySummary } | null> {
    return this.http.get<{ success: boolean; data: StoppageWeeklySummary }>(`${this.base}&run=weekly_summary&line=${line}`, { withCredentials: true }).pipe(
      timeout(10000), retry(1), catchError(() => of(null))
    );
  }

  getPareto(line: string = 'rebotling', dagar: number = 30): Observable<({ success: boolean } & ParetoData) | null> {
    return this.http.get<{ success: boolean } & ParetoData>(`${this.base}&run=pareto&line=${line}&dagar=${dagar}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  getPatternAnalysis(line: string = 'rebotling', days: number = 30): Observable<any> {
    return this.http.get<any>(`${this.base}&run=pattern-analysis&line=${line}&days=${days}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }
}
