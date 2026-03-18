import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OperatorItem {
  op_id: number;
  namn: string;
}

export interface MinProduktionData {
  success: boolean;
  total_ibc: number;
  operator_namn: string;
  timmar: string[];
  ibc_per_timme: number[];
  datum: string;
}

export interface MittTempoData {
  success: boolean;
  min_ibc_per_h: number;
  snitt_ibc_per_h: number;
  procent_vs_snitt: number;
  antal_operatorer: number;
  datum: string;
}

export interface MinBonusData {
  success: boolean;
  total_poang: number;
  produktions_poang: number;
  kvalitets_bonus: number;
  tempo_bonus: number;
  stopp_bonus: number;
  total_bonus: number;
  total_ibc: number;
  ok_pct: number;
  ibc_per_h: number;
  snitt_ibc_per_h: number;
  antal_stopp: number;
  datum: string;
}

export interface StoppItem {
  id: number;
  orsak: string;
  start_time: string;
  end_time: string | null;
  varaktighet_sek: number;
  varaktighet_min: number;
}

export interface MinaStoppData {
  success: boolean;
  stopp: StoppItem[];
  antal_stopp: number;
  total_stopptid_sek: number;
  total_stopptid_min: number;
  datum: string;
}

export interface MinVeckotrendData {
  success: boolean;
  dates: string[];
  values: number[];
  from: string;
  to: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorPersonalDashboardService {
  private api = `${environment.apiUrl}?action=operator-dashboard`;

  constructor(private http: HttpClient) {}

  getOperatorer(): Observable<{ success: boolean; operatorer: OperatorItem[] } | null> {
    return this.http.get<{ success: boolean; operatorer: OperatorItem[] }>(
      `${this.api}&run=operatorer`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getMinProduktion(opId: number): Observable<MinProduktionData | null> {
    return this.http.get<MinProduktionData>(
      `${this.api}&run=min-produktion&op=${opId}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getMittTempo(opId: number): Observable<MittTempoData | null> {
    return this.http.get<MittTempoData>(
      `${this.api}&run=mitt-tempo&op=${opId}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getMinBonus(opId: number): Observable<MinBonusData | null> {
    return this.http.get<MinBonusData>(
      `${this.api}&run=min-bonus&op=${opId}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getMinaStopp(opId: number): Observable<MinaStoppData | null> {
    return this.http.get<MinaStoppData>(
      `${this.api}&run=mina-stopp&op=${opId}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getMinVeckotrend(opId: number): Observable<MinVeckotrendData | null> {
    return this.http.get<MinVeckotrendData>(
      `${this.api}&run=min-veckotrend&op=${opId}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
}
