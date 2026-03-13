import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface AktuelltMalData {
  mal: {
    id: number;
    typ: 'vecka' | 'manad';
    mal_antal: number;
    start_datum: string;
    slut_datum: string;
    skapad_av: number | null;
    skapad_datum: string;
  } | null;
  har_mal: boolean;
}

export interface DagligProduktion {
  datum: string;
  antal: number;
}

export interface ProgressData {
  har_mal: boolean;
  meddelande?: string;
  mal?: {
    id: number;
    typ: 'vecka' | 'manad';
    mal_antal: number;
    start_datum: string;
    slut_datum: string;
  };
  producerat: number;
  aterstaar: number;
  procent: number;
  dagar_kvar: number;
  arbetsdagar_kvar: number;
  arbetsdagar_hittills: number;
  arbetsdagar_totalt: number;
  snitt_per_dag: number;
  mal_per_dag: number;
  prognos_slut: number;
  prognos_status: string;
  prognos_farg: 'gron' | 'rod' | 'neutral';
  behover_oka_med: number;
  daglig_produktion: DagligProduktion[];
}

export interface MalHistorikRad {
  id: number;
  typ: 'vecka' | 'manad';
  mal_antal: number;
  start_datum: string;
  slut_datum: string;
  skapad_datum: string;
  faktiskt: number;
  procent: number;
  uppnadd: boolean;
  avslutad: boolean;
  differens: number;
}

export interface MalHistorikData {
  antal: number;
  historik: MalHistorikRad[];
}

export interface SattMalResponse {
  meddelande: string;
  id: number;
  typ: string;
  mal_antal: number;
  start_datum: string;
  slut_datum: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
  error?: string;
}

// Legacy interfaces (bakatkompabilitet)
export interface DagSummary {
  datum: string; mal: number; faktiskt: number; uppfyllnad: number;
  prognos_ibc: number; prognos_pct: number; status: 'ahead' | 'on_track' | 'behind';
  elapsed_h: number; total_h: number;
}
export interface VeckaSummary {
  veckonr: number; start: string; slut: string; mal: number; full_mal: number;
  faktiskt: number; uppfyllnad: number; status: 'ahead' | 'on_track' | 'behind';
}
export interface ManadSummary {
  manad: string; start: string; slut: string; mal: number; full_mal: number;
  faktiskt: number; uppfyllnad: number; status: 'ahead' | 'on_track' | 'behind';
}
export interface SummaryData { dag: DagSummary; vecka: VeckaSummary; manad: ManadSummary; }
export interface SummaryResponse { success: boolean; data: SummaryData; timestamp: string; }
export interface DailyRow {
  datum: string; veckodag: string; mal: number; faktiskt: number; uppfyllnad: number;
  kum_mal: number; kum_faktiskt: number; kum_pct: number;
}
export interface DailyData { days: number; daily: DailyRow[]; }
export interface DailyResponse { success: boolean; data: DailyData; timestamp: string; }
export interface WeeklyRow {
  veckonr: number; ar: number; start: string; slut: string;
  mal: number; faktiskt: number; uppfyllnad: number; status: 'ahead' | 'on_track' | 'behind';
}
export interface WeeklyData { weeks: number; weekly: WeeklyRow[]; }
export interface WeeklyResponse { success: boolean; data: WeeklyData; timestamp: string; }

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsmalService {
  private api = `${environment.apiUrl}?action=produktionsmal`;

  constructor(private http: HttpClient) {}

  // Nya endpoints
  getAktuelltMal(): Observable<ApiResponse<AktuelltMalData> | null> {
    return this.http.get<ApiResponse<AktuelltMalData>>(
      `${this.api}&run=aktuellt-mal`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getProgress(): Observable<ApiResponse<ProgressData> | null> {
    return this.http.get<ApiResponse<ProgressData>>(
      `${this.api}&run=progress`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  sattMal(typ: string, antal: number, startdatum: string): Observable<ApiResponse<SattMalResponse> | null> {
    return this.http.post<ApiResponse<SattMalResponse>>(
      `${this.api}&run=satt-mal`,
      { typ, antal, startdatum },
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getMalHistorik(limit: number = 12): Observable<ApiResponse<MalHistorikData> | null> {
    return this.http.get<ApiResponse<MalHistorikData>>(
      `${this.api}&run=mal-historik&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  // Legacy endpoints
  getSummary(): Observable<SummaryResponse | null> {
    return this.http.get<SummaryResponse>(
      `${this.api}&run=summary`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getDaily(days: number): Observable<DailyResponse | null> {
    return this.http.get<DailyResponse>(
      `${this.api}&run=daily&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getWeekly(weeks: number): Observable<WeeklyResponse | null> {
    return this.http.get<WeeklyResponse>(
      `${this.api}&run=weekly&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
