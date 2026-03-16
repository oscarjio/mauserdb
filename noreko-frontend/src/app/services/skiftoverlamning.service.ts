import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

const API = `${environment.apiUrl}?action=skiftoverlamning`;

// --- Interfaces ---

export interface ChecklistaItem {
  key: string;
  label: string;
  checked: boolean;
}

export interface SkiftoverlamningItem {
  id: number;
  operator_id: number;
  operator_namn: string;
  skift_typ: 'dag' | 'kvall' | 'natt';
  skift_typ_label: string;
  datum: string;
  ibc_totalt: number;
  ibc_per_h: number;
  stopptid_min: number;
  kassationer: number;
  problem_text: string | null;
  pagaende_arbete: string | null;
  instruktioner: string | null;
  kommentar: string | null;
  har_pagaende_problem: boolean;
  checklista: ChecklistaItem[] | null;
  mal_nasta_skift: string | null;
  allvarlighetsgrad: string;
  skapad: string;
}

export interface ListResponse {
  success: boolean;
  error?: string;
  items: SkiftoverlamningItem[];
  total: number;
  limit: number;
  offset: number;
}

export interface DetailResponse {
  success: boolean;
  error?: string;
  item: SkiftoverlamningItem;
}

export interface ShiftKpis {
  skiftraknare: number;
  skift_datum: string;
  skift_start: string;
  skift_slut: string;
  skift_typ: string;
  ibc_totalt: number;
  ibc_ok: number;
  ibc_per_h: number;
  stopptid_min: number;
  kassationer: number;
  drifttid_min: number;
}

export interface ShiftKpisResponse {
  success: boolean;
  error?: string;
  kpis: ShiftKpis | null;
  message?: string;
}

export interface SenastOverlamning {
  id: number;
  skapad: string;
  operator_namn: string;
  skift_typ: string;
  datum: string;
}

export interface PagaendeProblem {
  id: number;
  datum: string;
  skift_typ: string;
  skift_typ_label: string;
  operator_namn: string;
  problem_text: string;
  pagaende_arbete: string;
  allvarlighetsgrad: string;
}

export interface SummaryResponse {
  success: boolean;
  error?: string;
  senaste_overlamning: SenastOverlamning | null;
  antal_denna_vecka: number;
  snitt_produktion_10: number;
  pagaende_problem_antal: number;
  pagaende_problem_lista: PagaendeProblem[];
}

export interface OperatorOption {
  id: number;
  namn: string;
}

export interface OperatorsResponse {
  success: boolean;
  error?: string;
  operators: OperatorOption[];
}

export interface CreateResponse {
  success: boolean;
  error?: string;
  id?: number;
  message?: string;
}

export interface CreatePayload {
  skift_typ: string;
  datum: string;
  ibc_totalt: number;
  ibc_per_h: number;
  stopptid_min: number;
  kassationer: number;
  problem_text: string;
  pagaende_arbete: string;
  instruktioner: string;
  kommentar: string;
  har_pagaende_problem: boolean;
  allvarlighetsgrad: string;
  checklista: ChecklistaItem[];
  mal_nasta_skift: string;
}

export interface AktuelltSkift {
  skift_typ: string;
  skift_typ_label: string;
  skift_start: string;
  skift_slut: string;
  tid_gatt_min: number;
  tid_kvar_min: number;
  ibc_totalt: number;
  ibc_ok: number;
  kasserade: number;
  ibc_per_h: number;
  oee_pct: number;
  drifttid_min: number;
  aktiv_nu: boolean;
  operator: string | null;
}

export interface AktuelltSkiftResponse {
  success: boolean;
  error?: string;
  skift_typ: string;
  skift_typ_label: string;
  skift_start: string;
  skift_slut: string;
  tid_gatt_min: number;
  tid_kvar_min: number;
  ibc_totalt: number;
  ibc_ok: number;
  kasserade: number;
  ibc_per_h: number;
  oee_pct: number;
  drifttid_min: number;
  aktiv_nu: boolean;
  operator: string | null;
}

export interface SkiftMal {
  oee_mal: number;
  ibc_mal: number;
  kassation_mal: number;
  drifttid_mal: number;
}

export interface SkiftSammanfattning {
  forra_skift_typ: string;
  forra_skift_typ_label: string;
  forra_datum: string;
  ibc_totalt: number;
  ibc_ok: number;
  kasserade: number;
  kassationsgrad_pct: number;
  ibc_per_h: number;
  oee_pct: number;
  drifttid_h: number;
  drifttid_pct: number;
  mal: SkiftMal;
  overlamning: any | null;
}

export interface SkiftSammanfattningResponse {
  success: boolean;
  error?: string;
  forra_skift_typ: string;
  forra_skift_typ_label: string;
  forra_datum: string;
  ibc_totalt: number;
  ibc_ok: number;
  kasserade: number;
  kassationsgrad_pct: number;
  ibc_per_h: number;
  oee_pct: number;
  drifttid_h: number;
  drifttid_pct: number;
  mal: SkiftMal;
  overlamning: any | null;
}

export interface OppnaProblemItem {
  id: number;
  datum: string;
  skift_typ: string;
  skift_typ_label: string;
  operator_namn: string;
  problem_text: string;
  pagaende_arbete: string;
  instruktioner: string;
  allvarlighetsgrad: string;
  skapad: string;
}

export interface OppnaProblemResponse {
  success: boolean;
  error?: string;
  problem: OppnaProblemItem[];
  antal: number;
}

export interface ChecklistaResponse {
  success: boolean;
  error?: string;
  checklista: ChecklistaItem[];
}

export interface HistorikResponse {
  success: boolean;
  error?: string;
  items: SkiftoverlamningItem[];
}

// --- Service ---

@Injectable({ providedIn: 'root' })
export class SkiftoverlamningService {
  constructor(private http: HttpClient) {}

  getList(filters: {
    skift_typ?: string;
    operator_id?: number;
    from?: string;
    to?: string;
    limit?: number;
    offset?: number;
  } = {}): Observable<ListResponse> {
    let params = new HttpParams();
    if (filters.skift_typ) params = params.set('skift_typ', filters.skift_typ);
    if (filters.operator_id) params = params.set('operator_id', String(filters.operator_id));
    if (filters.from) params = params.set('from', filters.from);
    if (filters.to) params = params.set('to', filters.to);
    if (filters.limit) params = params.set('limit', String(filters.limit));
    if (filters.offset) params = params.set('offset', String(filters.offset));

    return this.http.get<ListResponse>(`${API}&run=list`, { params, withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of({ success: false, error: 'Natverksfel', items: [], total: 0, limit: 50, offset: 0 } as ListResponse))
    );
  }

  getDetail(id: number): Observable<DetailResponse> {
    return this.http.get<DetailResponse>(`${API}&run=detail&id=${id}`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel' } as any))
    );
  }

  getShiftKpis(): Observable<ShiftKpisResponse> {
    return this.http.get<ShiftKpisResponse>(`${API}&run=shift-kpis`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel', kpis: null } as ShiftKpisResponse))
    );
  }

  getSummary(): Observable<SummaryResponse> {
    return this.http.get<SummaryResponse>(`${API}&run=summary`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({
        success: false, error: 'Natverksfel',
        senaste_overlamning: null, antal_denna_vecka: 0,
        snitt_produktion_10: 0, pagaende_problem_antal: 0,
        pagaende_problem_lista: []
      } as SummaryResponse))
    );
  }

  getOperators(): Observable<OperatorsResponse> {
    return this.http.get<OperatorsResponse>(`${API}&run=operators`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel', operators: [] } as OperatorsResponse))
    );
  }

  getAktuelltSkift(): Observable<AktuelltSkiftResponse> {
    return this.http.get<AktuelltSkiftResponse>(`${API}&run=aktuellt-skift`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel' } as any))
    );
  }

  getSkiftSammanfattning(): Observable<SkiftSammanfattningResponse> {
    return this.http.get<SkiftSammanfattningResponse>(`${API}&run=skift-sammanfattning`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel' } as any))
    );
  }

  getOppnaProblem(): Observable<OppnaProblemResponse> {
    return this.http.get<OppnaProblemResponse>(`${API}&run=oppna-problem`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel', problem: [], antal: 0 } as OppnaProblemResponse))
    );
  }

  getChecklista(): Observable<ChecklistaResponse> {
    return this.http.get<ChecklistaResponse>(`${API}&run=checklista`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel', checklista: [] } as ChecklistaResponse))
    );
  }

  getHistorik(limit: number = 10): Observable<HistorikResponse> {
    return this.http.get<HistorikResponse>(`${API}&run=historik&limit=${limit}`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Natverksfel', items: [] } as HistorikResponse))
    );
  }

  create(payload: CreatePayload): Observable<CreateResponse> {
    return this.http.post<CreateResponse>(`${API}&run=skapa-overlamning`, payload, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of({ success: false, error: 'Natverksfel' } as CreateResponse))
    );
  }
}
