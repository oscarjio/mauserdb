import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OverviewData {
  totalt_komponenter: number;
  forsenade: number;
  snart_forfaller: number;
  ok: number;
  nasta_underhall_datum: string | null;
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface SchemaRad {
  komponent_id: number;
  schema_id: number;
  komponent: string;
  maskin: string;
  kategori: string;
  intervall_dagar: number;
  senaste_underhall: string | null;
  nasta_datum: string | null;
  dagar_kvar: number | null;
  status: 'ok' | 'snart' | 'forsenat';
  progress_pct: number;
  ansvarig: string | null;
  noteringar: string | null;
  aldrig_underhalls: boolean;
}

export interface ScheduleData {
  schema: SchemaRad[];
  totalt: number;
  forsenade: number;
  snart: number;
  ok: number;
}

export interface ScheduleResponse {
  success: boolean;
  data: ScheduleData;
  timestamp: string;
}

export interface HistorikPost {
  id: number;
  titel: string;
  maskin: string;
  typ: string;
  datum: string;
  varaktighet_min: number | null;
  utforare: string | null;
  noteringar: string | null;
  status: string;
  kalla: string;
}

export interface HistoryData {
  poster: HistorikPost[];
  totalt: number;
  dagar: number;
}

export interface HistoryResponse {
  success: boolean;
  data: HistoryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class UnderhallsprognosService {
  private api = `${environment.apiUrl}?action=underhallsprognos`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getSchedule(): Observable<ScheduleResponse | null> {
    return this.http.get<ScheduleResponse>(
      `${this.api}&run=schedule`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getHistory(days: number = 90, limit: number = 50): Observable<HistoryResponse | null> {
    return this.http.get<HistoryResponse>(
      `${this.api}&run=history&days=${days}&limit=${limit}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
