import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SummaryData {
  idag_pct: number | null;
  idag_drifttid_h: number;
  idag_tillganglig_h: number;
  snitt_7d: number | null;
  snitt_30d: number | null;
  trend: 'improving' | 'declining' | 'stable';
  change_pct: number | null;
  total_drift_7d_h: number;
  total_tillg_7d_h: number;
  total_stopp_7d_h: number;
}

export interface SummaryResponse {
  success: boolean;
  data: SummaryData;
  timestamp: string;
}

export interface DailyRow {
  date: string;
  tillganglig_h: number;
  drifttid_h: number;
  stopptid_h: number;
  okand_tid_h: number;
  utnyttjandegrad: number | null;
  antal_stopp: number;
}

export interface DailyData {
  days: number;
  daily: DailyRow[];
}

export interface DailyResponse {
  success: boolean;
  data: DailyData;
  timestamp: string;
}

export interface LossItem {
  kategori: string;
  timmar: number;
  procent: number;
  farg: string;
  typ: string;
}

export interface TopOrsak {
  orsak: string;
  category: string;
  antal: number;
  total_h: number;
}

export interface LossesData {
  days: number;
  total_tillganglig_h: number;
  total_drifttid_h: number;
  total_stopptid_h: number;
  losses: LossItem[];
  topp_orsaker: TopOrsak[];
}

export interface LossesResponse {
  success: boolean;
  data: LossesData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class UtnyttjandegradService {
  private api = `${environment.apiUrl}?action=utnyttjandegrad`;

  constructor(private http: HttpClient) {}

  getSummary(): Observable<SummaryResponse | null> {
    return this.http.get<SummaryResponse>(
      `${this.api}&run=summary`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getDaily(days: number): Observable<DailyResponse | null> {
    return this.http.get<DailyResponse>(
      `${this.api}&run=daily&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getLosses(days: number): Observable<LossesResponse | null> {
    return this.http.get<LossesResponse>(
      `${this.api}&run=losses&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
