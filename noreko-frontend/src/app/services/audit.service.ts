import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface AuditEntry {
  id: number;
  action: string;
  entity_type: string;
  entity_id: number | null;
  description: string | null;
  old_value: string | null;
  new_value: string | null;
  user: string;
  ip_address: string | null;
  created_at: string;
}

export interface AuditStats {
  total: number;
  by_action: { action: string; count: number }[];
  by_user: { user: string; count: number }[];
  daily: { dag: string; count: number }[];
}

@Injectable({ providedIn: 'root' })
export class AuditService {
  private base = `${environment.apiUrl}?action=audit`;

  constructor(private http: HttpClient) {}

  getLogs(params: {
    page?: number;
    limit?: number;
    period?: string;
    filter_action?: string;
    filter_user?: string;
    filter_entity?: string;
    search?: string;
    from_date?: string;
    to_date?: string;
  } = {}): Observable<{ success: boolean; data: AuditEntry[]; total: number; page: number; pages: number; hasMore: boolean }> {
    let url = this.base;
    if (params.page)          url += `&page=${params.page}`;
    if (params.limit)         url += `&limit=${params.limit}`;
    if (params.period)        url += `&period=${encodeURIComponent(params.period)}`;
    if (params.filter_action) url += `&filter_action=${encodeURIComponent(params.filter_action)}`;
    if (params.filter_user)   url += `&filter_user=${encodeURIComponent(params.filter_user)}`;
    if (params.filter_entity) url += `&filter_entity=${encodeURIComponent(params.filter_entity)}`;
    if (params.search)        url += `&search=${encodeURIComponent(params.search)}`;
    if (params.from_date)     url += `&from_date=${encodeURIComponent(params.from_date)}`;
    if (params.to_date)       url += `&to_date=${encodeURIComponent(params.to_date)}`;
    return this.http.get<any>(url, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of({ success: false, data: [], total: 0, page: 1, pages: 0, hasMore: false }))
    );
  }

  getStats(period: string = 'month'): Observable<{ success: boolean; data: AuditStats | null }> {
    return this.http.get<any>(`${this.base}&run=stats&period=${period}`, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of({ success: false, data: null as AuditStats | null }))
    );
  }

  getActions(): Observable<{ success: boolean; data: string[] }> {
    return this.http.get<any>(`${this.base}&run=actions`, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of({ success: false, data: [] }))
    );
  }
}
