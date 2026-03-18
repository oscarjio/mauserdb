import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OperatorScatterPunkt {
  operator_id: number;
  operator_namn: string;
  antal_ibc: number;
  kassationsgrad: number;
  medel_cykeltid: number;
  oee: number;
  antal_dagar_aktiv: number;
  skift_typ: 'dag' | 'kvall' | 'natt';
}

export interface ScatterData {
  operatorer: OperatorScatterPunkt[];
  medel_cykeltid: number;
  medel_kvalitet_pct: number;
  period: number;
  skift_filter: string | null;
}

export interface DagligDetalj {
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  total_ibc: number;
  kassationsgrad: number;
  cykeltid_sek: number;
  drifttid_min: number;
}

export interface OperatorDetalj {
  operator_id: number;
  operator_namn: string;
  daglig: DagligDetalj[];
  streak: number;
  basta_dag: DagligDetalj | null;
  sammsta_dag: DagligDetalj | null;
}

export interface RankingRad {
  rank: number;
  operator_id: number;
  operator_namn: string;
  antal_ibc: number;
  kassationsgrad: number;
  medel_cykeltid: number;
  oee: number;
  antal_dagar_aktiv: number;
}

export interface RankingData {
  ranking: RankingRad[];
  sort_by: string;
  period: number;
  totalt: number;
}

export interface SkiftData {
  skift: string;
  label: string;
  total_ibc: number;
  kassationsgrad: number;
  medel_cykeltid: number;
  oee: number;
  medel_per_dag: number;
  antal_dagar: number;
  basta_operator: { operator_id: number; operator_namn: string; antal_ibc_ok: number } | null;
}

export interface TeamjamforelseData {
  skift: SkiftData[];
  period: number;
}

export interface VeckaRad {
  vecka: number;
  ar: number;
  label: string;
  vecko_start: string;
  vecko_slut: string;
  total_ibc: number;
  ibc_ok: number;
  kassationsgrad: number;
  medel_cykeltid: number;
  oee: number;
  har_data: boolean;
}

export interface UtvecklingData {
  operator_id: number;
  operator_namn: string;
  veckor: VeckaRad[];
  trend: 'forbattras' | 'forsamras' | 'neutral';
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
  error?: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorsPrestandaService {
  private api = `${environment.apiUrl}?action=operatorsprestanda`;

  constructor(private http: HttpClient) {}

  getScatterData(period: number, skift?: string): Observable<ApiResponse<ScatterData> | null> {
    let url = `${this.api}&run=scatter-data&period=${period}`;
    if (skift && skift !== 'alla') url += `&skift=${skift}`;
    return this.http.get<ApiResponse<ScatterData>>(url, { withCredentials: true })
      .pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getOperatorDetalj(id: number): Observable<ApiResponse<OperatorDetalj> | null> {
    return this.http.get<ApiResponse<OperatorDetalj>>(
      `${this.api}&run=operator-detalj&operator_id=${id}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getRanking(sortBy: string, period: number): Observable<ApiResponse<RankingData> | null> {
    return this.http.get<ApiResponse<RankingData>>(
      `${this.api}&run=ranking&sort_by=${sortBy}&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getTeamjamforelse(period: number): Observable<ApiResponse<TeamjamforelseData> | null> {
    return this.http.get<ApiResponse<TeamjamforelseData>>(
      `${this.api}&run=teamjamforelse&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }

  getUtveckling(id: number): Observable<ApiResponse<UtvecklingData> | null> {
    return this.http.get<ApiResponse<UtvecklingData>>(
      `${this.api}&run=utveckling&operator_id=${id}`,
      { withCredentials: true }
    ).pipe(timeout(30000), retry(1), catchError(() => of(null)));
  }
}
