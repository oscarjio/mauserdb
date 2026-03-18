import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface LeveransOverviewData {
  aktiva_ordrar: number;
  leveransgrad: number;
  forsenade_ordrar: number;
  kapacitetsutnyttjande: number;
  totalt_ordrar: number;
  levererade: number;
  planerad_produktion: number;
  tillganglig_kapacitet: number;
}

export interface LeveransOverviewResponse {
  success: boolean;
  data: LeveransOverviewData;
  timestamp: string;
}

export interface KundorderItem {
  id: number;
  kundnamn: string;
  antal_ibc: number;
  bestallningsdatum: string;
  onskat_leveransdatum: string;
  beraknat_leveransdatum: string;
  status: string;
  display_status: string;
  prioritet: number;
  notering: string | null;
}

export interface OrdrarData {
  ordrar: KundorderItem[];
  total: number;
  filter: { status: string; period: string };
}

export interface OrdrarResponse {
  success: boolean;
  data: OrdrarData;
  timestamp: string;
}

export interface GanttItem {
  id: number;
  kundnamn: string;
  antal_ibc: number;
  start: string;
  slut: string;
  deadline: string;
  status: string;
  prioritet: number;
  forsenad: boolean;
}

export interface KapacitetData {
  dates: string[];
  tillganglig: number[];
  planerad: number[];
  gantt: GanttItem[];
  config: KapacitetConfig;
}

export interface KapacitetResponse {
  success: boolean;
  data: KapacitetData;
  timestamp: string;
}

export interface PrognosItem {
  id: number;
  kundnamn: string;
  antal_ibc: number;
  onskat_leveransdatum: string;
  beraknat_leveransdatum: string;
  dagar_kvar: number;
  forsenad: boolean;
  dagar_forsenad: number;
  prioritet: number;
}

export interface PrognosData {
  prognos: PrognosItem[];
  config: KapacitetConfig;
  beraknad_at: string;
}

export interface PrognosResponse {
  success: boolean;
  data: PrognosData;
  timestamp: string;
}

export interface KapacitetConfig {
  kapacitet_per_dag: number;
  planerade_underhallsdagar: string[];
  buffer_procent: number;
}

export interface KonfigurationResponse {
  success: boolean;
  data: { config: KapacitetConfig };
  timestamp: string;
}

export interface SkapaOrderResponse {
  success: boolean;
  data: { id: number; beraknat_leveransdatum: string; status: string };
  timestamp: string;
}

export interface UppdateraOrderResponse {
  success: boolean;
  data: { updated: boolean };
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class LeveransplaneringService {
  private api = `${environment.apiUrl}?action=leveransplanering`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<LeveransOverviewResponse | null> {
    return this.http.get<LeveransOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getOrdrar(status: string = 'alla', period: string = 'alla'): Observable<OrdrarResponse | null> {
    return this.http.get<OrdrarResponse>(
      `${this.api}&run=ordrar&status=${status}&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getKapacitet(days: number = 30): Observable<KapacitetResponse | null> {
    return this.http.get<KapacitetResponse>(
      `${this.api}&run=kapacitet&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getPrognos(): Observable<PrognosResponse | null> {
    return this.http.get<PrognosResponse>(
      `${this.api}&run=prognos`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getKonfiguration(): Observable<KonfigurationResponse | null> {
    return this.http.get<KonfigurationResponse>(
      `${this.api}&run=konfiguration`,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  skapaOrder(order: any): Observable<SkapaOrderResponse | null> {
    return this.http.post<SkapaOrderResponse>(
      `${this.api}&run=skapa-order`,
      order,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  uppdateraOrder(id: number, status: string): Observable<UppdateraOrderResponse | null> {
    return this.http.post<UppdateraOrderResponse>(
      `${this.api}&run=uppdatera-order`,
      { id, status },
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }
}
