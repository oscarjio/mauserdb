import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface HistoriskOverview {
  total_produktion: number;
  total_ok: number;
  total_ej_ok: number;
  snitt_per_dag: number;
  basta_dag: string;
  basta_dag_antal: number;
  kassation_pct: number;
  dagar_med_data: number;
  from: string;
  to: string;
  days: number;
}

export interface HistoriskOverviewResponse {
  success: boolean;
  data: HistoriskOverview;
}

export interface PeriodDataPoint {
  label: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
}

export interface ProduktionPerPeriod {
  granularity: string;
  from: string;
  to: string;
  days: number;
  series: PeriodDataPoint[];
}

export interface ProduktionPerPeriodResponse {
  success: boolean;
  data: ProduktionPerPeriod;
}

export interface PeriodSummary {
  from: string;
  to: string;
  total: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  snitt_per_dag: number;
  kassation_pct: number;
  dagar_med_data: number;
}

export interface JamforelseDiff {
  total: number;
  total_pct: number;
  snitt_diff: number;
  snitt_pct: number;
  kassation_diff: number;
}

export interface Jamforelse {
  nuvarande: PeriodSummary;
  foregaende: PeriodSummary;
  diff: JamforelseDiff;
  trend_direction: string;
  days: number;
}

export interface JamforelseResponse {
  success: boolean;
  data: Jamforelse;
}

export interface DetaljRow {
  date: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
}

export interface DetaljSummor {
  ibc_ok: number;
  ibc_ej_ok: number;
  total: number;
  kassation_pct: number;
}

export interface DetaljTabell {
  rows: DetaljRow[];
  from: string;
  to: string;
  page: number;
  per_page: number;
  total_rows: number;
  total_pages: number;
  sort: string;
  order: string;
  summor: DetaljSummor;
}

export interface DetaljTabellResponse {
  success: boolean;
  data: DetaljTabell;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class HistoriskProduktionService {
  private api = `${environment.apiUrl}?action=historisk-produktion`;

  constructor(private http: HttpClient) {}

  getOverview(days?: number, from?: string, to?: string): Observable<HistoriskOverviewResponse | null> {
    let url = `${this.api}&run=overview`;
    if (from && to) {
      url += `&from=${from}&to=${to}`;
    } else if (days) {
      url += `&days=${days}`;
    }
    return this.http.get<HistoriskOverviewResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getProduktionPerPeriod(days?: number, from?: string, to?: string): Observable<ProduktionPerPeriodResponse | null> {
    let url = `${this.api}&run=produktion-per-period`;
    if (from && to) {
      url += `&from=${from}&to=${to}`;
    } else if (days) {
      url += `&days=${days}`;
    }
    return this.http.get<ProduktionPerPeriodResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getJamforelse(days?: number, from?: string, to?: string): Observable<JamforelseResponse | null> {
    let url = `${this.api}&run=jamforelse`;
    if (from && to) {
      url += `&from=${from}&to=${to}`;
    } else if (days) {
      url += `&days=${days}`;
    }
    return this.http.get<JamforelseResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDetaljTabell(params: {
    days?: number;
    from?: string;
    to?: string;
    page?: number;
    per_page?: number;
    sort?: string;
    order?: string;
  }): Observable<DetaljTabellResponse | null> {
    let url = `${this.api}&run=detalj-tabell`;
    if (params.from && params.to) {
      url += `&from=${params.from}&to=${params.to}`;
    } else if (params.days) {
      url += `&days=${params.days}`;
    }
    if (params.page) url += `&page=${params.page}`;
    if (params.per_page) url += `&per_page=${params.per_page}`;
    if (params.sort) url += `&sort=${params.sort}`;
    if (params.order) url += `&order=${params.order}`;
    return this.http.get<DetaljTabellResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
