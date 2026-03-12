import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

const API = '/noreko-backend/api.php?action=skiftoverlamning';

// --- Interfaces ---

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

  create(payload: CreatePayload): Observable<CreateResponse> {
    return this.http.post<CreateResponse>(`${API}&run=create`, payload, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of({ success: false, error: 'Natverksfel' } as CreateResponse))
    );
  }
}
