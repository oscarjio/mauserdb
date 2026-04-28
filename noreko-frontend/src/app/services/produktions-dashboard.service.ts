import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OversiktData {
  // Produktion
  ibc_idag: number;
  ibc_ok_idag: number;
  ibc_igar: number;
  prod_trend_pct: number;
  prod_trend_riktning: 'upp' | 'ned' | 'neutral';
  dagligt_mal: number;
  mal_uppfyllnad_pct: number | null;

  // OEE
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  oee_trend_pct: number;
  oee_trend_riktning: 'upp' | 'ned' | 'neutral';
  oee_forrad_vecka_pct: number;

  // Kassation
  kassationsgrad_pct: number;
  kassationsgrad_farg: 'green' | 'yellow' | 'red';
  kasserade_ibc: number;

  // Drifttid
  drifttid_h: number;
  drifttid_pct: number;
  planerad_h: number;

  // Stationer
  aktiva_stationer: number;
  totalt_stationer: number;

  // Skift
  skift_namn: string;
  skift_start: string;
  skift_slut: string;
  skift_kvarvarande_min: number;

  datum: string;
}

export interface ProduktionsDag {
  datum: string;
  total: number;
  mal: number;
  veckodag: string;
}

export interface VeckoProduktionData {
  dagar: ProduktionsDag[];
  period: number;
}

export interface OeeDag {
  datum: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  total_ibc: number;
  veckodag: string;
}

export interface VeckoOeeData {
  dagar: OeeDag[];
  period: number;
}

export interface StationStatus {
  station: string;
  status: 'kor' | 'stopp';
  ibc_idag: number;
  ok_idag: number;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  senaste_ibc_tid: string | null;
}

export interface StationerStatusData {
  stationer: StationStatus[];
  antal: number;
  datum: string;
}

export interface AlarmRad {
  id: number;
  start_time: string;
  stop_time: string | null;
  varaktighet_sek: number;
  varaktighet_min: number;
  status: string;
  typ: string;
}

export interface SenasteAlarmData {
  alarm: AlarmRad[];
  antal: number;
}

export interface IbcRad {
  id: number;
  datum: string;
  station: string;
  ok: number;
  status_text: string;
}

export interface SenasteIbcData {
  ibc: IbcRad[];
  antal: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsDashboardService {
  private api = `${environment.apiUrl}?action=produktionsdashboard`;

  constructor(private http: HttpClient) {}

  getOversikt(): Observable<ApiResponse<OversiktData> | null> {
    return this.http.get<ApiResponse<OversiktData>>(
      `${this.api}&run=oversikt`,
      { withCredentials: true }
    ).pipe(timeout(20000), retry(1), catchError(() => of(null)));
  }

  getVeckoProduktion(): Observable<ApiResponse<VeckoProduktionData> | null> {
    return this.http.get<ApiResponse<VeckoProduktionData>>(
      `${this.api}&run=vecko-produktion`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getVeckoOee(): Observable<ApiResponse<VeckoOeeData> | null> {
    return this.http.get<ApiResponse<VeckoOeeData>>(
      `${this.api}&run=vecko-oee`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStationerStatus(): Observable<ApiResponse<StationerStatusData> | null> {
    return this.http.get<ApiResponse<StationerStatusData>>(
      `${this.api}&run=stationer-status`,
      { withCredentials: true }
    ).pipe(timeout(20000), retry(1), catchError(() => of(null)));
  }

  getSenasteAlarm(): Observable<ApiResponse<SenasteAlarmData> | null> {
    return this.http.get<ApiResponse<SenasteAlarmData>>(
      `${this.api}&run=senaste-alarm`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getSenasteIbc(): Observable<ApiResponse<SenasteIbcData> | null> {
    return this.http.get<ApiResponse<SenasteIbcData>>(
      `${this.api}&run=senaste-ibc`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
