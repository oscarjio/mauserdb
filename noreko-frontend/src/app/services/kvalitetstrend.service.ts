import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface UtbildningsLarm {
  nummer: number;
  namn: string;
  senast_kval: number | null;
  lag_kvalitet: boolean;
  konsekvent_nedgang: boolean;
  orsak: string;
}

export interface OverviewData {
  period: number;
  snitt_kvalitet_pct: number | null;
  basta_operator: { nummer: number; namn: string; kvalitet_pct: number } | null;
  storst_forbattring: { nummer: number; namn: string; 'forändring_pct': number } | null;
  storst_nedgang: { nummer: number; namn: string; 'forändring_pct': number } | null;
  utbildningslarm: UtbildningsLarm[];
  antal_operatorer: number;
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface OperatorRow {
  nummer: number;
  namn: string;
  senast_kval_pct: number | null;
  snitt_kval_pct: number | null;
  forandring_pct: number | null;
  forandring_pil: 'up' | 'down' | 'flat';
  trend_status: 'förbättras' | 'stabil' | 'försämras';
  utbildningslarm: boolean;
  lag_kvalitet: boolean;
  konsekvent_nedgang: boolean;
  sparkdata: (number | null)[];
  ibc_totalt: number;
}

export interface OperatorsData {
  period: number;
  veckonycklar: string[];
  team_snitt_per_vecka: Record<string, number | null>;
  operatorer: OperatorRow[];
}

export interface OperatorsResponse {
  success: boolean;
  data: OperatorsData;
  timestamp: string;
}

export interface TidslinjeRow {
  vecka_key: string;
  vecka_label: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  kvalitet_pct: number | null;
  team_kvalitet: number | null;
  vs_team: number | null;
}

export interface OperatorDetailData {
  op_id: number;
  op_nummer: number;
  op_namn: string;
  period: number;
  tidslinje: TidslinjeRow[];
  snitt_kval_pct: number | null;
  senast_kval_pct: number | null;
  forandring_pct: number | null;
  forandring_pil: 'up' | 'down' | 'flat';
  utbildningslarm: boolean;
  lag_kvalitet: boolean;
  konsekvent_nedgang: boolean;
  ibc_totalt: number;
}

export interface OperatorDetailResponse {
  success: boolean;
  data: OperatorDetailData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class KvalitetstrendService {
  private api = `${environment.apiUrl}?action=kvalitetstrend`;

  constructor(private http: HttpClient) {}

  getOverview(period: number): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getOperators(period: number): Observable<OperatorsResponse | null> {
    return this.http.get<OperatorsResponse>(
      `${this.api}&run=operators&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getOperatorDetail(opId: number, period: number): Observable<OperatorDetailResponse | null> {
    return this.http.get<OperatorDetailResponse>(
      `${this.api}&run=operator-detail&op_id=${opId}&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
