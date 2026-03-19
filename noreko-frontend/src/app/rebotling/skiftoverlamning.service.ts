import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';

const API = '/noreko-backend/api.php?action=skiftoverlamning';

// --- Interfaces ---

export interface SkiftdataResponse {
  success: boolean;
  error?: string;
  skift_datum: string;
  skift_typ: string;
  skift_typ_label: string;
  skift_start: string;
  skift_slut: string;
  produktion_antal: number;
  oee_procent: number;
  stopp_antal: number;
  stopp_minuter: number;
  kassation_procent: number;
}

export interface ProtokollItem {
  id: number;
  skift_datum: string;
  skift_typ: string;
  skift_typ_label: string;
  operator_id: number;
  operator_namn: string;
  produktion_antal: number;
  oee_procent: number;
  stopp_antal: number;
  stopp_minuter: number;
  kassation_procent: number;
  checklista_rengoring: boolean;
  checklista_verktyg: boolean;
  checklista_kemikalier: boolean;
  checklista_avvikelser: boolean;
  checklista_sakerhet: boolean;
  checklista_material: boolean;
  kommentar_hande: string | null;
  kommentar_atgarda: string | null;
  kommentar_ovrigt: string | null;
  skapad: string;
}

export interface HistorikResponse {
  success: boolean;
  error?: string;
  items: ProtokollItem[];
}

export interface DetaljResponse {
  success: boolean;
  error?: string;
  item: ProtokollItem;
}

export interface SparaPayload {
  skift_datum: string;
  skift_typ: string;
  produktion_antal: number;
  oee_procent: number;
  stopp_antal: number;
  stopp_minuter: number;
  kassation_procent: number;
  checklista_rengoring: boolean;
  checklista_verktyg: boolean;
  checklista_kemikalier: boolean;
  checklista_avvikelser: boolean;
  checklista_sakerhet: boolean;
  checklista_material: boolean;
  kommentar_hande: string;
  kommentar_atgarda: string;
  kommentar_ovrigt: string;
}

export interface SparaResponse {
  success: boolean;
  error?: string;
  id?: number;
  message?: string;
}

// --- Service ---

@Injectable({ providedIn: 'root' })
export class SkiftoverlamningProtokollService {
  constructor(private http: HttpClient) {}

  getSkiftdata(): Observable<SkiftdataResponse> {
    return this.http.get<SkiftdataResponse>(`${API}&run=skiftdata`, { withCredentials: true }).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of({ success: false, error: 'Nätverksfel' } as any))
    );
  }

  spara(payload: SparaPayload): Observable<SparaResponse> {
    return this.http.post<SparaResponse>(`${API}&run=spara`, payload, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of({ success: false, error: 'Nätverksfel' } as SparaResponse))
    );
  }

  getHistorik(limit: number = 10): Observable<HistorikResponse> {
    return this.http.get<HistorikResponse>(`${API}&run=protokoll-historik&limit=${limit}`, { withCredentials: true }).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of({ success: false, error: 'Nätverksfel', items: [] } as HistorikResponse))
    );
  }

  getDetalj(id: number): Observable<DetaljResponse> {
    return this.http.get<DetaljResponse>(`${API}&run=protokoll-detalj&id=${id}`, { withCredentials: true }).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of({ success: false, error: 'Nätverksfel' } as any))
    );
  }
}
