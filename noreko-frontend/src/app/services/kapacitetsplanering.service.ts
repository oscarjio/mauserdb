import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface FlaskhalsFaktor {
  station: string;
  typ: string;
  antal_idag?: number;
  snitt_alla?: number;
  gap_pct?: number;
  avg_cykeltid_sek?: number;
  forklaring: string;
}

export interface KpiIdagData {
  datum: string;
  faktisk_idag: number;
  teormax_idag: number;
  utnyttjande_pct: number;
  antal_stationer: number;
  drifttid_h: number;
  ledig_kapacitet: number;
  rek_bemanning: number;
}

export interface KpiPeriodData {
  from_date: string;
  to_date: string;
  snitt_per_dag: number;
  teormax_per_dag: number;
  utnyttjande_snitt_pct: number;
  antal_stationer: number;
  optimal_cykeltid_sek: number;
  avg_cykeltid_sek: number;
  ibc_per_timme_optimal: number;
  prognos_vecka: number;
}

export interface KpiData {
  idag: KpiIdagData;
  period: KpiPeriodData;
  flaskhals: FlaskhalsFaktor;
  period_filter: string;
}

export interface DagligKapacitetRad {
  datum: string;
  faktisk: number;
  teor_max: number;
  mal: number | null;
  outnyttjad: number;
  utnyttjande_pct: number;
  drifttid_h: number;
  antal_stationer: number;
}

export interface DagligKapacitetData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  dagdata: DagligKapacitetRad[];
  genomsnitt: number;
}

export interface StationUtnyttjandeRad {
  station: string;
  total_ibc: number;
  ok_ibc: number;
  aktiva_dagar: number;
  teor_max: number;
  teor_per_timme: number;
  utnyttjande_pct: number;
  mal_pct: number;
  kassationsgrad_pct: number;
}

export interface StationUtnyttjandeData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  drifttid_h: number;
  stationer: StationUtnyttjandeRad[];
  antal_stationer: number;
}

export interface StoppOrsakRad {
  namn: string;
  sek: number;
  min: number;
  andel_pct: number;
}

export interface StoppOrsakerData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  planerad_h: number;
  drifttid_h: number;
  stopp_h: number;
  antal_stopp: number;
  avg_stopp_min: number;
  orsaker: StoppOrsakRad[];
}

export interface TidFordelningRad {
  datum: string;
  produktiv_h: number;
  stopp_h: number;
  idle_h: number;
  drifttid_h: number;
  planerad_h: number;
  antal_ibc: number;
}

export interface TidFordelningData {
  period_dagar: number;
  from_date: string;
  to_date: string;
  dagdata: TidFordelningRad[];
}

export interface VeckaRad {
  vecka: number;
  ar: number;
  from_datum: string;
  to_datum: string;
  total_ibc: number;
  max_kapacitet: number;
  utnyttjande_pct: number;
  trend: 'upp' | 'ned' | 'neutral';
  basta_dag: string | null;
  samsta_dag: string | null;
  basta_dag_antal: number;
  samsta_dag_antal: number;
}

export interface VeckoOversiktData {
  antal_veckor: number;
  veckor: VeckaRad[];
}

export interface UtnyttjandegradTrendRad {
  datum: string;
  utnyttjande_pct: number;
  faktisk: number;
  teor_max: number;
}

export interface UtnyttjandegradTrendData {
  period_dagar: number;
  mal_pct: number;
  dagdata: UtnyttjandegradTrendRad[];
}

export interface KapacitetstabellRad {
  station: string;
  teor_kap_per_h: number;
  faktisk_kap_per_h: number;
  utnyttjande_pct: number;
  mal_pct: number;
  flaskhals_faktor: number;
  trend: string;
  total_ibc: number;
  ok_ibc: number;
  aktiva_dagar: number;
}

export interface KapacitetstabellData {
  from_date: string;
  to_date: string;
  dagar: number;
  stationer: KapacitetstabellRad[];
}

export interface BemanningStation {
  station: string;
  operatorer: number;
  extra: boolean;
}

export interface BemanningData {
  orderbehov: number;
  period_filter: string;
  dagar: number;
  ibc_per_op_per_dag: number;
  total_operatorer: number;
  operatorer_per_skift: number;
  antal_skift: number;
  stationer: BemanningStation[];
}

export interface PrognosData {
  timmar: number;
  operatorer: number;
  antal_stationer: number;
  ibc_per_op_per_h: number;
  teor_max_per_h: number;
  teor_max_total: number;
  op_baserad: number;
  prognos_ibc: number;
  begransad_av: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KapacitetsplaneringService {
  private api = `${environment.apiUrl}?action=kapacitetsplanering`;

  constructor(private http: HttpClient) {}

  getKpi(periodFilter?: string): Observable<ApiResponse<KpiData> | null> {
    let url = `${this.api}&run=kpi`;
    if (periodFilter) url += `&period_filter=${periodFilter}`;
    return this.http.get<ApiResponse<KpiData>>(url, { withCredentials: true })
      .pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getDagligKapacitet(period: number): Observable<ApiResponse<DagligKapacitetData> | null> {
    return this.http.get<ApiResponse<DagligKapacitetData>>(
      `${this.api}&run=daglig-kapacitet&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getStationUtnyttjande(period: number, periodFilter?: string): Observable<ApiResponse<StationUtnyttjandeData> | null> {
    let url = `${this.api}&run=station-utnyttjande&period=${period}`;
    if (periodFilter) url += `&period_filter=${periodFilter}`;
    return this.http.get<ApiResponse<StationUtnyttjandeData>>(url, { withCredentials: true })
      .pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getStopporsaker(period: number): Observable<ApiResponse<StoppOrsakerData> | null> {
    return this.http.get<ApiResponse<StoppOrsakerData>>(
      `${this.api}&run=stopporsaker&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getTidFordelning(period: number): Observable<ApiResponse<TidFordelningData> | null> {
    return this.http.get<ApiResponse<TidFordelningData>>(
      `${this.api}&run=tid-fordelning&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getVeckoOversikt(): Observable<ApiResponse<VeckoOversiktData> | null> {
    return this.http.get<ApiResponse<VeckoOversiktData>>(
      `${this.api}&run=vecko-oversikt`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getUtnyttjandegradTrend(period: number): Observable<ApiResponse<UtnyttjandegradTrendData> | null> {
    return this.http.get<ApiResponse<UtnyttjandegradTrendData>>(
      `${this.api}&run=utnyttjandegrad-trend&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getKapacitetstabell(periodFilter?: string): Observable<ApiResponse<KapacitetstabellData> | null> {
    let url = `${this.api}&run=kapacitetstabell`;
    if (periodFilter) url += `&period_filter=${periodFilter}`;
    return this.http.get<ApiResponse<KapacitetstabellData>>(url, { withCredentials: true })
      .pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getBemanning(orderbehov: number, periodFilter?: string): Observable<ApiResponse<BemanningData> | null> {
    let url = `${this.api}&run=bemanning&orderbehov=${orderbehov}`;
    if (periodFilter) url += `&period_filter=${periodFilter}`;
    return this.http.get<ApiResponse<BemanningData>>(url, { withCredentials: true })
      .pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getPrognos(timmar: number, operatorer: number): Observable<ApiResponse<PrognosData> | null> {
    return this.http.get<ApiResponse<PrognosData>>(
      `${this.api}&run=prognos&timmar=${timmar}&operatorer=${operatorer}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
