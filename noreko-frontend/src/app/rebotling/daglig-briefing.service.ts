import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface BastaOperator {
  namn: string;
  total_ibc: number;
}

export interface SammanfattningData {
  datum: string;
  total_ibc: number;
  ok_ibc: number;
  kasserade: number;
  kassationsrate: number;
  oee_pct: number;
  stopp_minuter: number;
  dagsmal: number;
  mal_procent: number;
  basta_operator: BastaOperator | null;
  summering: string;
  oee_mal: number;
  kassation_troskel: number;
}

export interface SammanfattningResponse {
  success: boolean;
  data: SammanfattningData;
  timestamp: string;
}

export interface StopporsakItem {
  orsak: string;
  minuter: number;
  antal: number;
  procent: number;
}

export interface StopporsakerData {
  datum: string;
  orsaker: StopporsakItem[];
  total_min: number;
}

export interface StopporsakerResponse {
  success: boolean;
  data: StopporsakerData;
  timestamp: string;
}

export interface StationsstatusItem {
  station_id: number;
  station_namn: string;
  total_ibc: number;
  oee_pct: number;
  status: string;
}

export interface StationsstatusData {
  datum: string;
  stationer: StationsstatusItem[];
}

export interface StationsstatusResponse {
  success: boolean;
  data: StationsstatusData;
  timestamp: string;
}

export interface VeckotrendItem {
  datum: string;
  dag_kort: string;
  total_ibc: number;
}

export interface VeckotrendData {
  datum: string;
  trend: VeckotrendItem[];
}

export interface VeckotrendResponse {
  success: boolean;
  data: VeckotrendData;
  timestamp: string;
}

export interface BemanningOperator {
  user_id: number;
  namn: string;
  ibc_idag: number;
}

export interface BemanningData {
  datum: string;
  operatorer: BemanningOperator[];
  antal: number;
}

export interface BemanningResponse {
  success: boolean;
  data: BemanningData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class DagligBriefingService {
  private api = `${environment.apiUrl}?action=daglig-briefing`;

  constructor(private http: HttpClient) {}

  getSammanfattning(datum?: string): Observable<SammanfattningResponse | null> {
    let url = `${this.api}&run=sammanfattning`;
    if (datum) url += `&datum=${datum}`;
    return this.http.get<SammanfattningResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getStopporsaker(datum?: string): Observable<StopporsakerResponse | null> {
    let url = `${this.api}&run=stopporsaker`;
    if (datum) url += `&datum=${datum}`;
    return this.http.get<StopporsakerResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getStationsstatus(datum?: string): Observable<StationsstatusResponse | null> {
    let url = `${this.api}&run=stationsstatus`;
    if (datum) url += `&datum=${datum}`;
    return this.http.get<StationsstatusResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getVeckotrend(datum?: string): Observable<VeckotrendResponse | null> {
    let url = `${this.api}&run=veckotrend`;
    if (datum) url += `&datum=${datum}`;
    return this.http.get<VeckotrendResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getBemanning(): Observable<BemanningResponse | null> {
    return this.http.get<BemanningResponse>(
      `${this.api}&run=bemanning`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
