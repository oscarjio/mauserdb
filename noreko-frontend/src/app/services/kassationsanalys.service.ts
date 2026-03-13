import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface PeriodData {
  dagar: number;
  kasserade: number;
  produktion: number;
  andel_pct: number;
  prev_andel_pct: number;
  diff_pct: number;
  trend: string;
}

export interface VarstaStationData {
  station: string;
  kasserade: number;
  totalt: number;
  andel_pct: number;
}

export interface SammanfattningData {
  perioder: { [key: number]: PeriodData };
  varsta_station: VarstaStationData | null;
}

export interface OrsakRad {
  id: number;
  namn: string;
  antal: number;
  andel_pct: number;
  kumulativ_pct: number;
  prev_antal: number;
  trend: string;
}

export interface OrsakerData {
  days: number;
  total: number;
  orsaker: OrsakRad[];
}

export interface OrsakerTrendDataset {
  label: string;
  data: number[];
  borderColor: string;
}

export interface OrsakerTrendData {
  days: number;
  group: string;
  labels: string[];
  datasets: OrsakerTrendDataset[];
  har_data: boolean;
}

export interface StationRad {
  station: string;
  kasserade: number;
  godkanda: number;
  totalt: number;
  andel_pct: number;
}

export interface PerStationData {
  days: number;
  stationer: StationRad[];
}

export interface OperatorRad {
  operator: string;
  kasserade: number;
  totalt: number;
  andel_pct: number;
  ranking: number;
}

export interface PerOperatorData {
  days: number;
  operatorer: OperatorRad[];
}

export interface DetaljIbc {
  id: number;
  datum: string;
  station: string;
  operator: string;
  orsak: string;
}

export interface DetaljerData {
  days: number;
  total: number;
  ibc: DetaljIbc[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KassationsanalysService {
  private api = `${environment.apiUrl}?action=kassationsanalys`;

  constructor(private http: HttpClient) {}

  getSammanfattning(): Observable<ApiResponse<SammanfattningData> | null> {
    return this.http.get<ApiResponse<SammanfattningData>>(
      `${this.api}&run=sammanfattning`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getOrsaker(days: number): Observable<ApiResponse<OrsakerData> | null> {
    return this.http.get<ApiResponse<OrsakerData>>(
      `${this.api}&run=orsaker&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getOrsakerTrend(days: number, group: string = 'day'): Observable<ApiResponse<OrsakerTrendData> | null> {
    return this.http.get<ApiResponse<OrsakerTrendData>>(
      `${this.api}&run=orsaker-trend&days=${days}&group=${group}`,
      { withCredentials: true }
    ).pipe(timeout(20000), catchError(() => of(null)));
  }

  getPerStation(days: number): Observable<ApiResponse<PerStationData> | null> {
    return this.http.get<ApiResponse<PerStationData>>(
      `${this.api}&run=per-station&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getPerOperator(days: number): Observable<ApiResponse<PerOperatorData> | null> {
    return this.http.get<ApiResponse<PerOperatorData>>(
      `${this.api}&run=per-operator&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getDetaljer(days: number, limit: number = 100): Observable<ApiResponse<DetaljerData> | null> {
    return this.http.get<ApiResponse<DetaljerData>>(
      `${this.api}&run=detaljer&days=${days}&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
