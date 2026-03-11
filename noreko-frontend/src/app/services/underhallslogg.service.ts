import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

export interface UnderhallKategori {
  id: number;
  namn: string;
}

export interface UnderhallsPost {
  id: number;
  user_id: number;
  kategori: string;
  typ: 'planerat' | 'oplanerat';
  varaktighet_min: number;
  kommentar: string | null;
  maskin: string;
  created_at: string;
  operator_namn: string | null;
}

export interface UnderhallsStats {
  totalt_antal: number;
  total_tid_min: number;
  snitt_per_vecka: number;
  planerat_antal: number;
  oplanerat_antal: number;
  planerat_pct: number;
  oplanerat_pct: number;
  top_kategorier: { kategori: string; antal: number; total_min: number }[];
}

@Injectable({ providedIn: 'root' })
export class UnderhallsloggService {
  private base = '/noreko-backend/api.php?action=underhallslogg';

  constructor(private http: HttpClient) {}

  getCategories(): Observable<{ success: boolean; data: UnderhallKategori[] }> {
    return this.http.get<any>(`${this.base}&run=categories`, { withCredentials: true })
      .pipe(timeout(10000), catchError(() => of({ success: false, data: [] })));
  }

  logUnderhall(data: {
    kategori: string;
    typ: 'planerat' | 'oplanerat';
    varaktighet_min: number;
    kommentar: string;
    maskin?: string;
  }): Observable<{ success: boolean; message: string; id?: number }> {
    return this.http.post<any>(
      `${this.base}&run=log`,
      data,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, message: err?.error?.message || 'Anslutningsfel' })));
  }

  getList(
    days: number = 30,
    type: string = 'all',
    category: string = 'all'
  ): Observable<{ success: boolean; data: UnderhallsPost[] }> {
    return this.http.get<any>(
      `${this.base}&run=list&days=${days}&type=${encodeURIComponent(type)}&category=${encodeURIComponent(category)}`,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(() => of({ success: false, data: [] })));
  }

  getStats(days: number = 30): Observable<{ success: boolean; data: UnderhallsStats | null }> {
    return this.http.get<any>(
      `${this.base}&run=stats&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(() => of({ success: false, data: null as UnderhallsStats | null })));
  }

  deleteEntry(id: number): Observable<{ success: boolean; message: string }> {
    return this.http.post<any>(
      `${this.base}&run=delete`,
      { id },
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, message: err?.error?.message || 'Anslutningsfel' })));
  }
}
