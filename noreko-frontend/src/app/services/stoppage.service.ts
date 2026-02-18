import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

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

@Injectable({ providedIn: 'root' })
export class StoppageService {
  private base = '/noreko-backend/api.php?action=stoppage';

  constructor(private http: HttpClient) {}

  getReasons(): Observable<{ success: boolean; data: StoppageReason[] }> {
    return this.http.get<any>(`${this.base}&run=reasons`, { withCredentials: true });
  }

  getStoppages(line: string = 'rebotling', period: string = 'week'): Observable<{ success: boolean; data: StoppageEntry[] }> {
    return this.http.get<any>(`${this.base}&line=${line}&period=${period}`, { withCredentials: true });
  }

  getStats(line: string = 'rebotling', period: string = 'month'): Observable<{ success: boolean; data: StoppageStats }> {
    return this.http.get<any>(`${this.base}&run=stats&line=${line}&period=${period}`, { withCredentials: true });
  }

  create(entry: { line: string; reason_id: number; start_time: string; end_time?: string; comment?: string }): Observable<any> {
    return this.http.post<any>(this.base, { action: 'create', ...entry }, { withCredentials: true });
  }

  update(id: number, data: any): Observable<any> {
    return this.http.post<any>(this.base, { action: 'update', id, ...data }, { withCredentials: true });
  }

  delete(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'delete', id }, { withCredentials: true });
  }
}
