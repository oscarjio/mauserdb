import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// Legacy interfaces (backward compatibility)
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

// New Rebotling-specific interfaces
export interface Station {
  id: number;
  namn: string;
}

export interface RebotlingUnderhallsPost {
  id: number;
  station_id: number;
  station_namn: string;
  typ: 'planerat' | 'oplanerat';
  beskrivning: string | null;
  varaktighet_min: number;
  stopporsak: string | null;
  utford_av: string | null;
  datum: string;
  skapad: string;
}

export interface Sammanfattning {
  totalt_denna_manad: number;
  total_tid_min: number;
  planerat_antal: number;
  oplanerat_antal: number;
  planerat_pct: number;
  oplanerat_pct: number;
  snitt_tid_min: number;
  top_station: { station_id: number; station_namn: string; antal: number } | null;
}

export interface PerStationRad {
  station_id: number;
  station_namn: string;
  antal: number;
  total_tid: number;
  planerat: number;
  oplanerat: number;
}

export interface ManadsChartData {
  labels: string[];
  planerat: number[];
  oplanerat: number[];
}

@Injectable({ providedIn: 'root' })
export class UnderhallsloggService {
  private base = `${environment.apiUrl}?action=underhallslogg`;

  constructor(private http: HttpClient) {}

  // ---- Legacy endpoints ----

  getCategories(): Observable<{ success: boolean; data: UnderhallKategori[] }> {
    return this.http.get<any>(`${this.base}&run=categories`, { withCredentials: true })
      .pipe(timeout(10000), retry(1), catchError(() => of({ success: false, data: [] })));
  }

  logUnderhall(data: {
    kategori: string;
    typ: 'planerat' | 'oplanerat';
    varaktighet_min: number;
    kommentar: string;
    maskin?: string;
  }): Observable<{ success: boolean; message?: string; error?: string; id?: number }> {
    return this.http.post<any>(
      `${this.base}&run=log`,
      data,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Anslutningsfel' })));
  }

  getList(
    days: number = 30,
    type: string = 'all',
    category: string = 'all'
  ): Observable<{ success: boolean; data: UnderhallsPost[] }> {
    return this.http.get<any>(
      `${this.base}&run=list&days=${days}&type=${encodeURIComponent(type)}&category=${encodeURIComponent(category)}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({ success: false, data: [] })));
  }

  getStats(days: number = 30): Observable<{ success: boolean; data: UnderhallsStats | null }> {
    return this.http.get<any>(
      `${this.base}&run=stats&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({ success: false, data: null as UnderhallsStats | null })));
  }

  deleteEntry(id: number): Observable<{ success: boolean; message?: string; error?: string }> {
    return this.http.post<any>(
      `${this.base}&run=delete`,
      { id },
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Anslutningsfel' })));
  }

  // ---- New Rebotling-specific endpoints ----

  getStationer(): Observable<{ success: boolean; stationer: Station[] }> {
    return this.http.get<any>(
      `${this.base}&run=stationer`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({ success: false, stationer: [] })));
  }

  getLista(filters: {
    station?: number;
    typ?: string;
    from?: string;
    to?: string;
    limit?: number;
  } = {}): Observable<{ success: boolean; items: RebotlingUnderhallsPost[]; antal: number }> {
    let url = `${this.base}&run=lista`;
    if (filters.station && filters.station > 0) url += `&station=${filters.station}`;
    if (filters.typ && filters.typ !== 'alla') url += `&typ=${filters.typ}`;
    if (filters.from) url += `&from=${filters.from}`;
    if (filters.to) url += `&to=${filters.to}`;
    if (filters.limit) url += `&limit=${filters.limit}`;
    return this.http.get<any>(url, { withCredentials: true })
      .pipe(timeout(10000), retry(1), catchError(() => of({ success: false, items: [], antal: 0 })));
  }

  getSammanfattning(): Observable<{ success: boolean } & Sammanfattning> {
    return this.http.get<any>(
      `${this.base}&run=sammanfattning`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({
      success: false,
      totalt_denna_manad: 0, total_tid_min: 0,
      planerat_antal: 0, oplanerat_antal: 0,
      planerat_pct: 0, oplanerat_pct: 0,
      snitt_tid_min: 0, top_station: null,
    })));
  }

  getPerStation(days: number = 30): Observable<{ success: boolean; stationer: PerStationRad[]; days: number }> {
    return this.http.get<any>(
      `${this.base}&run=per-station&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({ success: false, stationer: [], days })));
  }

  getManadsChart(months: number = 6): Observable<{ success: boolean } & ManadsChartData> {
    return this.http.get<any>(
      `${this.base}&run=manadschart&months=${months}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of({ success: false, labels: [], planerat: [], oplanerat: [] })));
  }

  skapa(data: {
    station_id: number;
    typ: 'planerat' | 'oplanerat';
    beskrivning: string;
    varaktighet_min: number;
    stopporsak?: string;
    utford_av?: string;
    datum: string;
  }): Observable<{ success: boolean; id?: number; message?: string; error?: string }> {
    return this.http.post<any>(
      `${this.base}&run=skapa`,
      data,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Anslutningsfel' })));
  }

  taBort(id: number): Observable<{ success: boolean; message?: string; error?: string }> {
    return this.http.post<any>(
      `${this.base}&run=ta-bort`,
      { id },
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(err => of({ success: false, error: err?.error?.error || 'Anslutningsfel' })));
  }
}
