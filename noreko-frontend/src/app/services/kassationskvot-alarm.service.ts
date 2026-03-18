import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface Troskel {
  varning_procent: number;
  alarm_procent: number;
}

export interface KvotPeriod {
  period: string;
  godkanda: number;
  kasserade: number;
  totalt: number;
  kvot_pct: number;
  farg: 'gron' | 'gul' | 'rod';
}

export interface AktuellSkiftPeriod extends KvotPeriod {
  skift_namn: string;
  fran: string;
  till: string;
}

export interface AktuellKvotData {
  troskel: Troskel;
  senaste_timme: KvotPeriod;
  aktuellt_skift: AktuellSkiftPeriod;
  idag: KvotPeriod;
}

export interface AlarmEntry {
  datum: string;
  skiftraknare: number;
  skift_namn: string;
  kvot_pct: number;
  kasserade: number;
  totalt: number;
  troskel_pct: number;
  status: 'varning' | 'alarm';
  skift_start: string;
  skift_slut: string;
}

export interface AlarmHistorikData {
  troskel: Troskel;
  historik: AlarmEntry[];
  antal: number;
}

export interface TimvisTrendEntry {
  timme: string;
  kvot_pct: number;
  kasserade: number;
  totalt: number;
  farg: 'gron' | 'gul' | 'rod';
}

export interface TimvisTrendData {
  troskel: Troskel;
  trend: TimvisTrendEntry[];
}

export interface SkiftKvot {
  kvot_pct: number;
  kasserade: number;
  totalt: number;
  farg: 'gron' | 'gul' | 'rod';
}

export interface PerSkiftDag {
  datum: string;
  dag: SkiftKvot | null;
  kvall: SkiftKvot | null;
  natt: SkiftKvot | null;
}

export interface PerSkiftData {
  troskel: Troskel;
  dagar: PerSkiftDag[];
}

export interface TopOrsak {
  orsak: string;
  antal: number;
}

export interface TopOrsakerData {
  troskel: Troskel;
  alarm_skift_antal: number;
  orsaker: TopOrsak[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KassationskvotAlarmService {
  private api = `${environment.apiUrl}?action=kassationskvotalarm`;

  constructor(private http: HttpClient) {}

  getAktuellKvot(): Observable<ApiResponse<AktuellKvotData> | null> {
    return this.http.get<ApiResponse<AktuellKvotData>>(
      `${this.api}&run=aktuell-kvot`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getAlarmHistorik(dagar: number = 30): Observable<ApiResponse<AlarmHistorikData> | null> {
    return this.http.get<ApiResponse<AlarmHistorikData>>(
      `${this.api}&run=alarm-historik&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getTroskel(): Observable<ApiResponse<Troskel> | null> {
    return this.http.get<ApiResponse<Troskel>>(
      `${this.api}&run=troskel-hamta`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  sparaTroskel(varning_procent: number, alarm_procent: number): Observable<ApiResponse<Troskel> | null> {
    return this.http.post<ApiResponse<Troskel>>(
      `${this.api}&run=troskel-spara`,
      { varning_procent, alarm_procent },
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getTimvisTrend(): Observable<ApiResponse<TimvisTrendData> | null> {
    return this.http.get<ApiResponse<TimvisTrendData>>(
      `${this.api}&run=timvis-trend`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getPerSkift(): Observable<ApiResponse<PerSkiftData> | null> {
    return this.http.get<ApiResponse<PerSkiftData>>(
      `${this.api}&run=per-skift`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getTopOrsaker(dagar: number = 30): Observable<ApiResponse<TopOrsakerData> | null> {
    return this.http.get<ApiResponse<TopOrsakerData>>(
      `${this.api}&run=top-orsaker&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
