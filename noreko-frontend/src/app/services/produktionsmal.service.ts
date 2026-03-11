import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface DagSummary {
  datum: string;
  mal: number;
  faktiskt: number;
  uppfyllnad: number;
  prognos_ibc: number;
  prognos_pct: number;
  status: 'ahead' | 'on_track' | 'behind';
  elapsed_h: number;
  total_h: number;
}

export interface VeckaSummary {
  veckonr: number;
  start: string;
  slut: string;
  mal: number;
  full_mal: number;
  faktiskt: number;
  uppfyllnad: number;
  status: 'ahead' | 'on_track' | 'behind';
}

export interface ManadSummary {
  manad: string;
  start: string;
  slut: string;
  mal: number;
  full_mal: number;
  faktiskt: number;
  uppfyllnad: number;
  status: 'ahead' | 'on_track' | 'behind';
}

export interface SummaryData {
  dag: DagSummary;
  vecka: VeckaSummary;
  manad: ManadSummary;
}

export interface SummaryResponse {
  success: boolean;
  data: SummaryData;
  timestamp: string;
}

export interface DailyRow {
  datum: string;
  veckodag: string;
  mal: number;
  faktiskt: number;
  uppfyllnad: number;
  kum_mal: number;
  kum_faktiskt: number;
  kum_pct: number;
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

export interface WeeklyRow {
  veckonr: number;
  ar: number;
  start: string;
  slut: string;
  mal: number;
  faktiskt: number;
  uppfyllnad: number;
  status: 'ahead' | 'on_track' | 'behind';
}

export interface WeeklyData {
  weeks: number;
  weekly: WeeklyRow[];
}

export interface WeeklyResponse {
  success: boolean;
  data: WeeklyData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsmalService {
  private api = `${environment.apiUrl}?action=produktionsmal`;

  constructor(private http: HttpClient) {}

  getSummary(): Observable<SummaryResponse | null> {
    return this.http.get<SummaryResponse>(
      `${this.api}&run=summary`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getDaily(days: number): Observable<DailyResponse | null> {
    return this.http.get<DailyResponse>(
      `${this.api}&run=daily&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getWeekly(weeks: number): Observable<WeeklyResponse | null> {
    return this.http.get<WeeklyResponse>(
      `${this.api}&run=weekly&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
