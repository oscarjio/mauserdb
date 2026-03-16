import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface BonusOverviewData {
  period: string;
  snitt_bonus: number;
  hogsta_bonus: number;
  hogsta_namn: string;
  lagsta_bonus: number;
  lagsta_namn: string;
  total_utbetald: number;
  antal_kvalificerade: number;
  antal_operatorer: number;
}

export interface BonusOverviewResponse {
  success: boolean;
  data: BonusOverviewData;
}

export interface OperatorBonus {
  operator_id: number;
  operator_namn: string;
  ibc_per_timme: number;
  kvalitet: number;
  narvaro: number;
  team_mal: number;
  bonus_ibc: number;
  bonus_kvalitet: number;
  bonus_narvaro: number;
  bonus_team: number;
  total_bonus: number;
  pct_ibc: number;
  pct_kvalitet: number;
  pct_narvaro: number;
  pct_team: number;
}

export interface BonusKonfig {
  [key: string]: {
    vikt: number;
    mal_varde: number;
    max_bonus_kr: number;
    beskrivning: string;
  };
}

export interface PerOperatorData {
  period: string;
  from: string;
  to: string;
  konfig: BonusKonfig;
  operatorer: OperatorBonus[];
}

export interface PerOperatorResponse {
  success: boolean;
  data: PerOperatorData;
}

export interface KonfigItem {
  faktor: string;
  label: string;
  vikt: number;
  mal_varde: number;
  max_bonus_kr: number;
  beskrivning: string;
}

export interface KonfigResponse {
  success: boolean;
  konfig: KonfigItem[];
  max_total: number;
}

export interface SimuleringInput {
  ibc_per_timme: number;
  kvalitet: number;
  narvaro: number;
  team_mal: number;
}

export interface SimuleringData {
  input: SimuleringInput;
  bonus_ibc: number;
  bonus_kvalitet: number;
  bonus_narvaro: number;
  bonus_team: number;
  total_bonus: number;
  max_total: number;
  pct_av_max: number;
  konfig: BonusKonfig;
}

export interface SimuleringResponse {
  success: boolean;
  data: SimuleringData;
}

export interface HistorikResponse {
  success: boolean;
  data: {
    utbetalningar: any[];
    total: number;
  };
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorsbonusService {
  private api = `${environment.apiUrl}?action=operatorsbonus`;

  constructor(private http: HttpClient) {}

  getOverview(period: string = 'dag'): Observable<BonusOverviewResponse | null> {
    return this.http.get<BonusOverviewResponse>(
      `${this.api}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getPerOperator(period: string = 'dag'): Observable<PerOperatorResponse | null> {
    return this.http.get<PerOperatorResponse>(
      `${this.api}&run=per-operator&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  getKonfiguration(): Observable<KonfigResponse | null> {
    return this.http.get<KonfigResponse>(
      `${this.api}&run=konfiguration`,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(() => of(null)));
  }

  sparaKonfiguration(items: { faktor: string; vikt: number; mal_varde: number; max_bonus_kr: number }[]): Observable<any> {
    return this.http.post(
      `${this.api}&run=spara-konfiguration`,
      items,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => of({ success: false, error: err?.error?.error || 'Okant fel' }))
    );
  }

  getHistorik(operatorId?: number, from?: string, to?: string): Observable<HistorikResponse | null> {
    let url = `${this.api}&run=historik`;
    if (operatorId) url += `&operator_id=${operatorId}`;
    if (from) url += `&from=${from}`;
    if (to) url += `&to=${to}`;
    return this.http.get<HistorikResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(10000), catchError(() => of(null)));
  }

  getSimulering(ibcPerTimme: number, kvalitet: number, narvaro: number, teamMal: number): Observable<SimuleringResponse | null> {
    return this.http.get<SimuleringResponse>(
      `${this.api}&run=simulering&ibc_per_timme=${ibcPerTimme}&kvalitet=${kvalitet}&narvaro=${narvaro}&team_mal=${teamMal}`,
      { withCredentials: true }
    ).pipe(timeout(10000), catchError(() => of(null)));
  }
}
