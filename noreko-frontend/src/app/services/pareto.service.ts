import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface ParetoItem {
  reason: string;
  minutes: number;
  count: number;
  percentage: number;
  cumulative_pct: number;
  in_80pct: boolean;
}

export interface ParetoData {
  items: ParetoItem[];
  total_minutes: number;
  days: number;
  from_date: string;
  to_date: string;
}

export interface ParetoDataResponse {
  success: boolean;
  data: ParetoData;
  timestamp: string;
}

export interface ParetoSummaryData {
  days: number;
  from_date: string;
  to_date: string;
  total_minutes: number;
  total_formatted: string;
  antal_orsaker: number;
  top_orsak: string | null;
  top_orsak_pct: number;
  antal_inom_80pct: number;
}

export interface ParetoSummaryResponse {
  success: boolean;
  data: ParetoSummaryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ParetoService {
  private api = `${environment.apiUrl}?action=pareto`;

  constructor(private http: HttpClient) {}

  getParetoData(days: number): Observable<ParetoDataResponse | null> {
    return this.http.get<ParetoDataResponse>(
      `${this.api}&run=pareto-data&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getSummary(days: number): Observable<ParetoSummaryResponse | null> {
    return this.http.get<ParetoSummaryResponse>(
      `${this.api}&run=summary&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
