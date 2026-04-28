import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OverviewData {
  aktiva_totalt: number;
  aktiva_kritiska: number;
  larm_idag: number;
  snitt_losningstid: number;
  per_typ: { typ: string; antal: number }[];
  per_allvarlighetsgrad: { allvarlighetsgrad: string; antal: number }[];
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface LarmItem {
  id: number;
  typ: string;
  allvarlighetsgrad: string;
  meddelande: string;
  varde_aktuellt: number | null;
  varde_grans: number | null;
  tidsstampel: string;
  kvitterad?: boolean;
  kvitterad_av?: string | null;
  kvitterad_datum?: string | null;
  kvitterings_kommentar?: string | null;
}

export interface AktivaResponse {
  success: boolean;
  data: { larm: LarmItem[]; antal: number };
  timestamp: string;
}

export interface HistorikData {
  days: number;
  from_date: string;
  to_date: string;
  larm: LarmItem[];
  total: number;
}

export interface HistorikResponse {
  success: boolean;
  data: HistorikData;
  timestamp: string;
}

export interface KvitteraResponse {
  success: boolean;
  data: { kvitterat: boolean; larm_id: number };
  timestamp: string;
}

export interface Regel {
  id: number;
  typ: string;
  allvarlighetsgrad: string;
  grans_varde: number;
  aktiv: boolean;
  beskrivning: string;
  updated_at: string | null;
}

export interface ReglerResponse {
  success: boolean;
  data: { regler: Regel[] };
  timestamp: string;
}

export interface UppdateraRegelResponse {
  success: boolean;
  data: { uppdaterad: boolean; regel_id: number };
  timestamp: string;
}

export interface TrendSeries {
  allvarlighetsgrad: string;
  values: number[];
}

export interface TrendData {
  days: number;
  from_date: string;
  to_date: string;
  dates: string[];
  series: TrendSeries[];
}

export interface TrendResponse {
  success: boolean;
  data: TrendData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class AvvikelselarmService {
  private api = `${environment.apiUrl}?action=avvikelselarm`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getAktiva(): Observable<AktivaResponse | null> {
    return this.http.get<AktivaResponse>(
      `${this.api}&run=aktiva`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getHistorik(period: string, typ: string = '', allvarlighetsgrad: string = ''): Observable<HistorikResponse | null> {
    let url = `${this.api}&run=historik&period=${period}`;
    if (typ) url += `&typ=${typ}`;
    if (allvarlighetsgrad) url += `&allvarlighetsgrad=${allvarlighetsgrad}`;
    return this.http.get<HistorikResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  kvittera(larmId: number, kvitteradAv: string, kommentar: string): Observable<KvitteraResponse | null> {
    return this.http.post<KvitteraResponse>(
      `${this.api}&run=kvittera`,
      { larm_id: larmId, kvitterad_av: kvitteradAv, kommentar },
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(err => { console.error('kvittera failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' } as any); }));
  }

  getRegler(): Observable<ReglerResponse | null> {
    return this.http.get<ReglerResponse>(
      `${this.api}&run=regler`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  uppdateraRegel(regelId: number, gransVarde?: number, aktiv?: boolean): Observable<UppdateraRegelResponse | null> {
    const body: any = { regel_id: regelId };
    if (gransVarde !== undefined) body.grans_varde = gransVarde;
    if (aktiv !== undefined) body.aktiv = aktiv;
    return this.http.post<UppdateraRegelResponse>(
      `${this.api}&run=uppdatera-regel`,
      body,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(err => { console.error('uppdateraRegel failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' } as any); }));
  }

  getTrend(period: string): Observable<TrendResponse | null> {
    return this.http.get<TrendResponse>(
      `${this.api}&run=trend&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
