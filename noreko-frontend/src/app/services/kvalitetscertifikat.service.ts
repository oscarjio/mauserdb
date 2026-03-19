import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KvalitetOverviewData {
  totala_certifikat: number;
  godkand_procent: number;
  godkanda: number;
  senaste_datum: string | null;
  senaste_batch: string | null;
  snitt_kvalitetspoang: number;
}

export interface KvalitetOverviewResponse {
  success: boolean;
  data: KvalitetOverviewData;
}

export interface Certifikat {
  id: number;
  batch_nummer: string;
  datum: string;
  operator_id: number | null;
  operator_namn: string;
  antal_ibc: number;
  kassation_procent: number;
  cykeltid_snitt: number;
  kvalitetspoang: number;
  status: 'godkand' | 'underkand' | 'ej_bedomd';
  kommentar: string | null;
  bedomd_av: string | null;
  bedomd_datum: string | null;
  skapad_datum: string;
}

export interface OperatorFilter {
  operator_id: number;
  operator_namn: string;
}

export interface ListaResponse {
  success: boolean;
  data: {
    certifikat: Certifikat[];
    total: number;
    operatorer: OperatorFilter[];
  };
}

export interface DetaljResponse {
  success: boolean;
  data: {
    certifikat: Certifikat;
    kriterier: Kriterium[];
  };
}

export interface Kriterium {
  id: number;
  namn: string;
  beskrivning: string;
  min_varde: number | null;
  max_varde: number | null;
  vikt: number;
  aktiv: boolean;
}

export interface KriterierResponse {
  success: boolean;
  data: Kriterium[];
}

export interface StatistikItem {
  id: number;
  batch_nummer: string;
  datum: string;
  kvalitetspoang: number;
  status: string;
  operator_namn: string;
}

export interface StatistikResponse {
  success: boolean;
  data: StatistikItem[];
}

export interface GenereraResponse {
  success: boolean;
  message?: string;
  id?: number;
  kvalitetspoang?: number;
  error?: string;
}

export interface BedomResponse {
  success: boolean;
  message?: string;
  error?: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KvalitetscertifikatService {
  private api = `${environment.apiUrl}?action=kvalitetscertifikat`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<KvalitetOverviewResponse | null> {
    return this.http.get<KvalitetOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getLista(status?: string, period?: string, operatorId?: number, limit: number = 500): Observable<ListaResponse | null> {
    let url = `${this.api}&run=lista&limit=${limit}`;
    if (status) url += `&status=${status}`;
    if (period) url += `&period=${period}`;
    if (operatorId) url += `&operator_id=${operatorId}`;
    return this.http.get<ListaResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetalj(id: number): Observable<DetaljResponse | null> {
    return this.http.get<DetaljResponse>(
      `${this.api}&run=detalj&id=${id}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  generera(data: {
    batch_nummer: string;
    datum: string;
    operator_id?: number;
    operator_namn: string;
    antal_ibc: number;
    kassation_procent: number;
    cykeltid_snitt: number;
  }): Observable<GenereraResponse> {
    return this.http.post<GenereraResponse>(
      `${this.api}&run=generera`,
      data,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => of({ success: false, error: err?.error?.error || 'Okant fel' }))
    );
  }

  bedom(id: number, status: 'godkand' | 'underkand', kommentar: string): Observable<BedomResponse> {
    return this.http.post<BedomResponse>(
      `${this.api}&run=bedom`,
      { id, status, kommentar },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => of({ success: false, error: err?.error?.error || 'Okant fel' }))
    );
  }

  getKriterier(): Observable<KriterierResponse | null> {
    return this.http.get<KriterierResponse>(
      `${this.api}&run=kriterier`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  uppdateraKriterier(items: any[]): Observable<any> {
    return this.http.post(
      `${this.api}&run=uppdatera-kriterier`,
      items,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => of({ success: false, error: err?.error?.error || 'Okant fel' }))
    );
  }

  getStatistik(limit: number = 30): Observable<StatistikResponse | null> {
    return this.http.get<StatistikResponse>(
      `${this.api}&run=statistik&limit=${limit}`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
}
