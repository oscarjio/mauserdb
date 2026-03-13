import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface HeatmapCell {
  orsak: string;
  antal: number;
  total_min: number;
}

export interface HeatmapStation {
  station_id: number;
  station_namn: string;
  celler: HeatmapCell[];
}

export interface HeatmapData {
  weeks: number;
  from_date: string;
  to_date: string;
  orsaker: string[];
  matris: HeatmapStation[];
  max_antal: number;
}

export interface HeatmapResponse {
  success: boolean;
  data: HeatmapData;
  timestamp: string;
}

export interface MtbfStation {
  station_id: number;
  station_namn: string;
  antal_stopp: number;
  mtbf_dagar: number;
  senaste_stopp: string | null;
  dagar_sedan_stopp: number;
  risk: string;
  risk_poang: number;
  risk_kvot: number;
  mtbf_trend: string;
}

export interface MtbfData {
  from_date: string;
  to_date: string;
  stationer: MtbfStation[];
}

export interface MtbfResponse {
  success: boolean;
  data: MtbfData;
  timestamp: string;
}

export interface TrendVecka {
  vecka: string;
  label: string;
  antal: number;
}

export interface TrendStation {
  station_id: number;
  station_namn: string;
  veckodata: TrendVecka[];
  totalt: number;
}

export interface TrenderData {
  weeks: number;
  from_date: string;
  to_date: string;
  veckonycklar: string[];
  trender: TrendStation[];
}

export interface TrenderResponse {
  success: boolean;
  data: TrenderData;
  timestamp: string;
}

export interface Rekommendation {
  typ: string;
  prioritet: number;
  station_id: number | null;
  station_namn: string;
  orsak: string | null;
  meddelande: string;
  antal_recent: number;
  antal_old: number;
  okning_pct: number;
}

export interface RekommendationerData {
  from_date: string;
  to_date: string;
  rekommendationer: Rekommendation[];
  antal_varningar: number;
  antal_atgarder: number;
  antal_ok: number;
}

export interface RekommendationerResponse {
  success: boolean;
  data: RekommendationerData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class PrediktivtUnderhallService {
  private api = `${environment.apiUrl}?action=prediktivt-underhall`;

  constructor(private http: HttpClient) {}

  getHeatmap(weeks = 4): Observable<HeatmapResponse | null> {
    return this.http.get<HeatmapResponse>(
      `${this.api}&run=heatmap&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getMtbf(): Observable<MtbfResponse | null> {
    return this.http.get<MtbfResponse>(
      `${this.api}&run=mtbf`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getTrender(weeks = 12): Observable<TrenderResponse | null> {
    return this.http.get<TrenderResponse>(
      `${this.api}&run=trender&weeks=${weeks}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getRekommendationer(): Observable<RekommendationerResponse | null> {
    return this.http.get<RekommendationerResponse>(
      `${this.api}&run=rekommendationer`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
