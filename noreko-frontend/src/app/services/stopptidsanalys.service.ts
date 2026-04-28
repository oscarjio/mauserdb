import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OverviewData {
  days: number;
  from_date: string;
  to_date: string;
  total_idag_min: number;
  antal_stopp_idag: number;
  snitt_per_stopp_min: number;
  flaskhals_maskin: string | null;
  flaskhals_maskin_min: number;
  period_total_min: number;
  prev_total_min: number;
  trend_diff_min: number;
  trend_direction: 'up' | 'down' | 'flat';
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface MaskinItem {
  maskin_id: number;
  maskin_namn: string;
  total_min: number;
  antal_stopp: number;
  snitt_min: number;
  max_stopp_min: number;
  senaste_stopp: string;
  andel_pct: number;
}

export interface PerMaskinData {
  days: number;
  from_date: string;
  to_date: string;
  total_min: number;
  maskiner: MaskinItem[];
}

export interface PerMaskinResponse {
  success: boolean;
  data: PerMaskinData;
  timestamp: string;
}

export interface TrendSeries {
  maskin_id: number;
  maskin_namn: string;
  values: number[];
}

export interface TrendData {
  days: number;
  from_date: string;
  to_date: string;
  maskin_id: number;
  dates: string[];
  series: TrendSeries[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

export interface FordelningItem {
  maskin_id: number;
  maskin_namn: string;
  total_min: number;
  antal_stopp: number;
  andel_pct: number;
}

export interface FordelningData {
  days: number;
  from_date: string;
  to_date: string;
  total_min: number;
  fordelning: FordelningItem[];
}

export interface FordelningResponse {
  success: boolean;
  data: FordelningData;
  timestamp: string;
}

export interface StoppEvent {
  id: number;
  maskin_id: number;
  maskin_namn: string;
  startad_at: string;
  avslutad_at: string;
  duration_min: number;
  orsak: string;
  orsak_kategori: string;
  operator_namn: string;
  kommentar: string | null;
}

export interface DetaljData {
  days: number;
  from_date: string;
  to_date: string;
  maskin_id: number;
  stopp: StoppEvent[];
  total: number;
}

export interface DetaljResponse {
  success: boolean;
  data: DetaljData;
  timestamp: string;
}

export interface Maskin {
  id: number;
  namn: string;
  beskrivning: string;
}

export interface MaskinerResponse {
  success: boolean;
  data: { maskiner: Maskin[] };
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class StopptidsanalysService {
  private api = `${environment.apiUrl}?action=stopptidsanalys`;

  constructor(private http: HttpClient) {}

  getOverview(period: string): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPerMaskin(period: string): Observable<PerMaskinResponse | null> {
    return this.http.get<PerMaskinResponse>(
      `${this.api}&run=per-maskin&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getTrend(period: string, maskinId: number = 0): Observable<TrendResponse | null> {
    const maskinParam = maskinId > 0 ? `&maskin_id=${maskinId}` : '';
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&period=${period}${maskinParam}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getFordelning(period: string): Observable<FordelningResponse | null> {
    return this.http.get<FordelningResponse>(
      `${this.api}&run=fordelning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetaljtabell(period: string, maskinId: number = 0): Observable<DetaljResponse | null> {
    const maskinParam = maskinId > 0 ? `&maskin_id=${maskinId}` : '';
    return this.http.get<DetaljResponse>(
      `${this.api}&run=detaljtabell&period=${period}${maskinParam}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getMaskiner(): Observable<MaskinerResponse | null> {
    return this.http.get<MaskinerResponse>(
      `${this.api}&run=maskiner`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
