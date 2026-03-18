import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface FlodeOverview {
  totalt_inkommande: number;
  godkanda: number;
  kasserade: number;
  genomstromning_pct: number;
  flaskhals_station: string;
  dagar_med_data: number;
  antal_skift: number;
  from: string;
  to: string;
  days: number;
}

export interface FlodeOverviewResponse {
  success: boolean;
  data: FlodeOverview;
}

export interface FlodeNode {
  id: string;
  label: string;
  type: string;
}

export interface FlodeLink {
  from: string;
  to: string;
  value: number;
  type: string;
}

export interface FlodeStation {
  id: string;
  name: string;
  order: number;
  inkommande: number;
  godkanda: number;
  kasserade: number;
  genomstromning_pct: number;
}

export interface FlodeSummary {
  total: number;
  godkanda: number;
  kasserade: number;
  genomstromning: number;
}

export interface FlodeData {
  nodes: FlodeNode[];
  links: FlodeLink[];
  stations: FlodeStation[];
  summary: FlodeSummary;
  from: string;
  to: string;
  days: number;
}

export interface FlodeDataResponse {
  success: boolean;
  data: FlodeData;
}

export interface StationDetalj {
  station: string;
  station_id: string;
  order: number;
  inkommande: number;
  godkanda: number;
  kasserade: number;
  genomstromning_pct: number;
  tid_per_ibc_sek: number;
  flaskhals: boolean;
}

export interface StationDetaljerData {
  rows: StationDetalj[];
  from: string;
  to: string;
  days: number;
  total_runtime: number;
  totalt: number;
  totalt_godkanda: number;
  totalt_kasserade: number;
}

export interface StationDetaljerResponse {
  success: boolean;
  data: StationDetaljerData;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsflodeService {
  private api = `${environment.apiUrl}?action=produktionsflode`;

  constructor(private http: HttpClient) {}

  getOverview(days?: number): Observable<FlodeOverviewResponse | null> {
    let url = `${this.api}&run=overview`;
    if (days) url += `&days=${days}`;
    return this.http.get<FlodeOverviewResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getFlodeData(days?: number): Observable<FlodeDataResponse | null> {
    let url = `${this.api}&run=flode-data`;
    if (days) url += `&days=${days}`;
    return this.http.get<FlodeDataResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getStationDetaljer(days?: number): Observable<StationDetaljerResponse | null> {
    let url = `${this.api}&run=station-detaljer`;
    if (days) url += `&days=${days}`;
    return this.http.get<StationDetaljerResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
