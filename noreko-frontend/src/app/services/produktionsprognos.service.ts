import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface ForecastData {
  skift_namn: string;
  skift_start: string;
  skift_slut: string;
  ibc_hittills: number;
  ibc_idag: number;
  takt_per_timme: number;
  snitt_takt: number | null;
  trend_status: 'bättre' | 'sämre' | 'i snitt' | 'okant';
  trend_pct: number | null;
  prognos_vid_slut: number;
  dags_mal: number | null;
  progress_pct: number | null;
  tid_kvar_sek: number;
  tid_kvar_h: number;
  tid_kvar_min: number;
  shift_elapsed_pct: number;
  nu: string;
}

export interface ForecastResponse {
  success: boolean;
  data: ForecastData;
  timestamp: string;
}

export interface ShiftHistorik {
  skift_namn: string;
  skift_start: string;
  skift_slut: string;
  datum: string;
  ibc_totalt: number;
  takt_per_timme: number;
}

export interface ShiftHistoryData {
  skift_historik: ShiftHistorik[];
  snitt_ibc: number;
  snitt_takt: number;
  antal_skift: number;
}

export interface ShiftHistoryResponse {
  success: boolean;
  data: ShiftHistoryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsPrognosService {
  private api = `${environment.apiUrl}?action=produktionsprognos`;

  constructor(private http: HttpClient) {}

  getForecast(): Observable<ForecastResponse | null> {
    return this.http.get<ForecastResponse>(
      `${this.api}&run=forecast`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getShiftHistory(): Observable<ShiftHistoryResponse | null> {
    return this.http.get<ShiftHistoryResponse>(
      `${this.api}&run=shift-history`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
