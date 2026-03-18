import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// === Legacy interfaces (bakatkompat) ===

export interface PulsItem {
  id: number;
  datum: string;
  operator: string;
  produkt: string;
  cykeltid: number | null;
  target_cykeltid: number | null;
  kasserad: boolean;
  over_target: boolean;
  ibc_nr: number;
  skift: number;
}

export interface PulsLatestResponse {
  success: boolean;
  data: PulsItem[];
}

export interface HourData {
  ibc_count: number;
  godkanda: number;
  kasserade: number;
  snitt_cykeltid: number | null;
}

export interface PulsHourlyResponse {
  success: boolean;
  current: HourData;
  previous: HourData;
}

// === Nya interfaces for realtids-ticker ===

export interface PulseEvent {
  type: 'ibc' | 'onoff' | 'stopp';
  time: string;
  label: string;
  detail: string;
  color: 'success' | 'danger' | 'warning' | 'info';
  icon: string;
}

export interface PulseResponse {
  success: boolean;
  data: PulseEvent[];
  timestamp: string;
}

export interface Driftstatus {
  running: boolean;
  sedan: string | null;
}

export interface TidSedanSenasteStopp {
  minuter: number | null;
  senaste_stopp: string | null;
}

export interface LiveKpiResponse {
  success: boolean;
  ibc_idag: number;
  ibc_per_timme: number;
  driftstatus: Driftstatus;
  tid_sedan_senaste_stopp: TidSedanSenasteStopp;
  timestamp: string;
}

@Injectable({ providedIn: 'root' })
export class ProduktionspulsService {
  private api = `${environment.apiUrl}?action=produktionspuls`;

  constructor(private http: HttpClient) {}

  // Legacy
  getLatest(limit = 50): Observable<PulsLatestResponse | null> {
    return this.http.get<PulsLatestResponse>(
      `${this.api}&run=latest&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  // Legacy
  getHourlyStats(): Observable<PulsHourlyResponse | null> {
    return this.http.get<PulsHourlyResponse>(
      `${this.api}&run=hourly-stats`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  // Ny: kronologisk handelsefeed
  getPulse(limit = 20): Observable<PulseResponse | null> {
    return this.http.get<PulseResponse>(
      `${this.api}&run=pulse&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  // Ny: realtids-KPI:er
  getLiveKpi(): Observable<LiveKpiResponse | null> {
    return this.http.get<LiveKpiResponse>(
      `${this.api}&run=live-kpi`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
}
