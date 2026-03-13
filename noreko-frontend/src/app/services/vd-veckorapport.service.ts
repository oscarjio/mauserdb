import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface KpiVarde {
  denna_vecka: number;
  forra_vecka: number;
  diff: number;
  diff_pct: number | null;
  trend: 'upp' | 'ned' | 'stabil';
}

export interface KpiJamforelseData {
  denna_vecka_fran: string;
  denna_vecka_till: string;
  forra_vecka_fran: string;
  forra_vecka_till: string;
  veckonummer: number;
  ar: number;
  jamforelse: {
    oee: KpiVarde;
    produktion: KpiVarde;
    kassation: KpiVarde;
    drifttid_h: KpiVarde;
    [key: string]: KpiVarde;
  };
  daglig_produktion: DagligProd[];
}

export interface DagligProd {
  dag: string;
  ibc: number;
  kassation: number;
}

export interface Anomali {
  datum: string;
  typ: string;
  beskrivning: string;
  allvarlighet: 'positiv' | 'varning' | 'kritisk';
}

export interface TrendInfo {
  slope: number;
  trend: string;
  r2: number;
}

export interface TrenderAnomalierData {
  anomalier: Anomali[];
  trender: {
    produktion: TrendInfo;
    kassation: TrendInfo;
  };
}

export interface OperatorKpi {
  operator_id: number;
  operator_namn: string;
  ibc_ok: number;
  kassationsgrad: number;
  oee: number;
  antal_skift: number;
  rank?: number;
}

export interface TopBottomData {
  top: OperatorKpi[];
  bottom: OperatorKpi[];
  totalt: number;
  period: number;
}

export interface Stopporsak {
  orsak: string;
  antal: number;
  total_min: number;
  medel_min: number;
  andel_pct: number;
}

export interface StopporsakerData {
  stopporsaker: Stopporsak[];
  period: number;
  fran: string;
  till: string;
}

export interface KpiVarden {
  oee: number;
  produktion: number;
  kassation: number;
  drifttid_h: number;
  [key: string]: number;
}

export interface VeckaSammanfattningData {
  ar: number;
  vecka: number;
  fran: string;
  till: string;
  kpi_denna: KpiVarden;
  kpi_forra: KpiVarden;
  daglig: DagligProd[];
  stopporsaker: Stopporsak[];
  operatorer: OperatorKpi[];
  anomalier: Anomali[];
  genererad: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class VdVeckorapportService {
  private api = `${environment.apiUrl}?action=vd-veckorapport`;

  constructor(private http: HttpClient) {}

  getKpiJamforelse(): Observable<ApiResponse<KpiJamforelseData> | null> {
    return this.http.get<ApiResponse<KpiJamforelseData>>(
      `${this.api}&run=kpi-jamforelse`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getTrenderAnomalier(): Observable<ApiResponse<TrenderAnomalierData> | null> {
    return this.http.get<ApiResponse<TrenderAnomalierData>>(
      `${this.api}&run=trender-anomalier`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getTopBottomOperatorer(period = 7): Observable<ApiResponse<TopBottomData> | null> {
    return this.http.get<ApiResponse<TopBottomData>>(
      `${this.api}&run=top-bottom-operatorer&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getStopporsaker(period = 7): Observable<ApiResponse<StopporsakerData> | null> {
    return this.http.get<ApiResponse<StopporsakerData>>(
      `${this.api}&run=stopporsaker&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }

  getVeckaSammanfattning(vecka?: string): Observable<ApiResponse<VeckaSammanfattningData> | null> {
    const param = vecka ? `&vecka=${vecka}` : '';
    return this.http.get<ApiResponse<VeckaSammanfattningData>>(
      `${this.api}&run=vecka-sammanfattning${param}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }
}
