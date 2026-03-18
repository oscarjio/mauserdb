import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface BatchOverview {
  aktiva_batchar: number;
  snitt_ledtid_h: number;
  snitt_kassation: number;
  basta_batch: string;
  basta_batch_kass: number;
}

export interface BatchOverviewResponse {
  success: boolean;
  data: BatchOverview;
}

export interface ActiveBatch {
  id: number;
  batch_nummer: string;
  planerat_antal: number;
  antal_klara: number;
  antal_kasserade: number;
  status: 'pagaende' | 'klar' | 'pausad';
  status_label: string;
  skapad_datum: string;
  kommentar: string | null;
  snitt_cykeltid_s: number | null;
  uppskattat_kvar_min: number | null;
}

export interface ActiveBatchesResponse {
  success: boolean;
  batchar: ActiveBatch[];
}

export interface BatchIbc {
  id: number;
  ibc_nummer: string | null;
  operator_id: number | null;
  startad: string;
  klar: string | null;
  kasserad: boolean;
  cykeltid_sekunder: number | null;
}

export interface BatchOperator {
  id: number;
  namn: string;
  antal: number;
}

export interface BatchDetailResponse {
  success: boolean;
  batch: {
    id: number;
    batch_nummer: string;
    planerat_antal: number;
    kommentar: string | null;
    status: string;
    status_label: string;
    skapad_datum: string;
    avslutad_datum: string | null;
  };
  antal_klara: number;
  antal_kasserade: number;
  snitt_cykeltid_s: number | null;
  tidsatgang_min: number;
  operatorer: BatchOperator[];
  ibcer: BatchIbc[];
}

export interface HistoryBatch {
  id: number;
  batch_nummer: string;
  planerat_antal: number;
  antal_klara: number;
  antal_kasserade: number;
  kassation_pct: number;
  snitt_cykeltid_s: number | null;
  ledtid_min: number | null;
  skapad_datum: string;
  avslutad_datum: string | null;
  kommentar: string | null;
  status_label: string;
}

export interface BatchHistoryResponse {
  success: boolean;
  batchar: HistoryBatch[];
}

export interface CreateBatchData {
  batch_nummer: string;
  planerat_antal: number;
  kommentar?: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class BatchSparningService {
  private api = `${environment.apiUrl}?action=batchsparning`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<BatchOverviewResponse | null> {
    return this.http.get<BatchOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getActiveBatches(): Observable<ActiveBatchesResponse | null> {
    return this.http.get<ActiveBatchesResponse>(
      `${this.api}&run=active-batches`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getBatchDetail(batchId: number): Observable<BatchDetailResponse | null> {
    return this.http.get<BatchDetailResponse>(
      `${this.api}&run=batch-detail&batch_id=${batchId}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getBatchHistory(from?: string, to?: string, search?: string): Observable<BatchHistoryResponse | null> {
    let url = `${this.api}&run=batch-history`;
    if (from) url += `&from=${from}`;
    if (to) url += `&to=${to}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    return this.http.get<BatchHistoryResponse>(
      url,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      retry(1),
      catchError(() => of(null))
    );
  }

  createBatch(data: CreateBatchData): Observable<any> {
    return this.http.post(
      `${this.api}&run=create-batch`,
      data,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }

  completeBatch(batchId: number): Observable<any> {
    return this.http.post(
      `${this.api}&run=complete-batch`,
      { batch_id: batchId },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }
}
