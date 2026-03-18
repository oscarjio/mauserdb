import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SammanfattningData {
  days: number;
  from_date: string;
  to_date: string;
  antal_stopp: number;
  total_timmar: number;
  snitt_min: number;
  vanligaste_orsak: string | null;
  prev_antal: number;
  trend_pct: number;
}

export interface SammanfattningResponse {
  success: boolean;
  data: SammanfattningData;
  timestamp: string;
}

export interface ParetoItem {
  kategori_id: number;
  orsak: string;
  antal: number;
  total_min: number;
  procent: number;
  kumulativ_pct: number;
}

export interface ParetoData {
  days: number;
  from_date: string;
  to_date: string;
  total: number;
  pareto: ParetoItem[];
}

export interface ParetoResponse {
  success: boolean;
  data: ParetoData;
  timestamp: string;
}

export interface StationRow {
  station_id: number;
  station_namn: string;
  antal: number;
  total_min: number;
}

export interface PerStationData {
  days: number;
  from_date: string;
  to_date: string;
  stationer: StationRow[];
}

export interface PerStationResponse {
  success: boolean;
  data: PerStationData;
  timestamp: string;
}

export interface TrendData {
  days: number;
  from_date: string;
  to_date: string;
  dates: string[];
  antal: number[];
  minuter: number[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

export interface OrsakRow {
  kategori_id: number;
  orsak: string;
  antal: number;
  total_min: number;
  snitt_min: number;
  andel_pct: number;
  prev_antal: number;
  trend_pct: number;
}

export interface OrsakerTabellData {
  days: number;
  from_date: string;
  to_date: string;
  orsaker: OrsakRow[];
  total: number;
}

export interface OrsakerTabellResponse {
  success: boolean;
  data: OrsakerTabellData;
  timestamp: string;
}

export interface UnderhallLink {
  underhall_id: number;
  station_namn: string;
  beskrivning: string;
}

export interface DetaljRow {
  id: number;
  start_time: string;
  end_time: string | null;
  orsak: string;
  ikon: string;
  varaktighet_min: number | null;
  kommentar: string | null;
  operator_namn: string;
  underhall: UnderhallLink | null;
}

export interface DetaljerData {
  days: number;
  from_date: string;
  to_date: string;
  detaljer: DetaljRow[];
  total: number;
}

export interface DetaljerResponse {
  success: boolean;
  data: DetaljerData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class StopporsakerService {
  private api = `${environment.apiUrl}?action=stopporsak-dashboard`;

  constructor(private http: HttpClient) {}

  getSammanfattning(days: number): Observable<SammanfattningResponse | null> {
    return this.http.get<SammanfattningResponse>(
      `${this.api}&run=sammanfattning&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPareto(days: number): Observable<ParetoResponse | null> {
    return this.http.get<ParetoResponse>(
      `${this.api}&run=pareto&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerStation(days: number): Observable<PerStationResponse | null> {
    return this.http.get<PerStationResponse>(
      `${this.api}&run=per-station&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(days: number): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getOrsakerTabell(days: number): Observable<OrsakerTabellResponse | null> {
    return this.http.get<OrsakerTabellResponse>(
      `${this.api}&run=orsaker-tabell&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetaljer(days: number): Observable<DetaljerResponse | null> {
    return this.http.get<DetaljerResponse>(
      `${this.api}&run=detaljer&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
