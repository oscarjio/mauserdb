import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SkiftKassationsorsak {
  orsak: string;
  antal: number;
}

export interface SkiftData {
  skift: string;
  start: string;
  slut: string;
  producerade: number;
  godkanda: number;
  kasserade: number;
  kassationsgrad_pct: number;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  drifttid_h: number;
  stopptid_h: number;
  top_kassationsorsaker: SkiftKassationsorsak[];
}

export interface DagligSammanstallningData {
  datum: string;
  skift: SkiftData[];
  totalt: {
    producerade: number;
    godkanda: number;
    kasserade: number;
    kassationsgrad_pct: number;
  };
}

export interface VeckodagData {
  datum: string;
  veckodag: string;
  dag: SkiftData;
  kvall: SkiftData;
  natt: SkiftData;
  totalt_producerade: number;
  totalt_kasserade: number;
  totalt_oee_pct: number;
}

export interface VeckosammanstallningData {
  dagar: VeckodagData[];
}

export interface SkiftSnitt {
  totalt_producerade: number;
  totalt_kasserade: number;
  totalt_godkanda: number;
  snitt_oee_pct: number;
  snitt_producerade_per_dag: number;
}

export interface SkiftjamforelseEntry {
  datum: string;
  dag_producerade: number;
  dag_oee_pct: number;
  kvall_producerade: number;
  kvall_oee_pct: number;
  natt_producerade: number;
  natt_oee_pct: number;
}

export interface SkiftjamforelseData {
  antal_dagar: number;
  dagdata: SkiftjamforelseEntry[];
  snitt: {
    dag: SkiftSnitt;
    kvall: SkiftSnitt;
    natt: SkiftSnitt;
  };
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class SkiftrapportSammanstallningService {
  private api = `${environment.apiUrl}?action=skiftrapport`;

  constructor(private http: HttpClient) {}

  getDagligSammanstallning(datum: string): Observable<ApiResponse<DagligSammanstallningData> | null> {
    return this.http.get<ApiResponse<DagligSammanstallningData>>(
      `${this.api}&run=daglig-sammanstallning&datum=${datum}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getVeckosammanstallning(): Observable<ApiResponse<VeckosammanstallningData> | null> {
    return this.http.get<ApiResponse<VeckosammanstallningData>>(
      `${this.api}&run=veckosammanstallning`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getSkiftjamforelse(dagar: number = 30): Observable<ApiResponse<SkiftjamforelseData> | null> {
    return this.http.get<ApiResponse<SkiftjamforelseData>>(
      `${this.api}&run=skiftjamforelse&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
