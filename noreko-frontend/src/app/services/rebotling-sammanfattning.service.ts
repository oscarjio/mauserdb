import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface LarmItem {
  id: number;
  typ: string;
  allvarlighetsgrad: string;
  meddelande: string;
  tidsstampel: string;
}

export interface SammanfattningOverview {
  datum: string;
  dagens_produktion: number;
  dagens_ok: number;
  dagens_ej_ok: number;
  kassation_pct: number;
  oee_pct: number | null;
  drifttid_pct: number | null;
  aktiva_larm: number;
  senaste_larm: LarmItem[];
}

export interface SammanfattningOverviewResponse {
  success: boolean;
  data: SammanfattningOverview;
  timestamp: string;
}

export interface Produktion7dItem {
  datum: string;
  label: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
}

export interface Produktion7dData {
  from: string;
  to: string;
  series: Produktion7dItem[];
}

export interface Produktion7dResponse {
  success: boolean;
  data: Produktion7dData;
  timestamp: string;
}

export interface MaskinStatusItem {
  maskin_id: number;
  maskin_namn: string;
  oee: number | null;
  tillganglighet: number | null;
  drifttid_min: number | null;
  planerad_tid_min: number | null;
  stopptid_min: number | null;
  total_output: number | null;
  kassation: number | null;
  status: string; // 'gron' | 'gul' | 'rod'
}

export interface MaskinStatusData {
  datum: string;
  maskiner: MaskinStatusItem[];
}

export interface MaskinStatusResponse {
  success: boolean;
  data: MaskinStatusData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class RebotlingSammanfattningService {
  private api = `${environment.apiUrl}?action=rebotling-sammanfattning`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<SammanfattningOverviewResponse | null> {
    return this.http.get<SammanfattningOverviewResponse>(`${this.api}&run=overview`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getProduktion7d(): Observable<Produktion7dResponse | null> {
    return this.http.get<Produktion7dResponse>(`${this.api}&run=produktion-7d`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  getMaskinStatus(): Observable<MaskinStatusResponse | null> {
    return this.http.get<MaskinStatusResponse>(`${this.api}&run=maskin-status`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }
}
