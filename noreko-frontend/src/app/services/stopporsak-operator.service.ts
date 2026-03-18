import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OperatorRow {
  user_id: number;
  namn: string;
  total_min: number;
  antal_stopp: number;
  vanligast_orsak: string | null;
  hog_stopptid: boolean;
  pct_av_snitt: number;
}

export interface OverviewData {
  period: number;
  from_date: string;
  to_date: string;
  operatorer: OperatorRow[];
  team_snitt_min: number;
  team_snitt_stopp: number;
  total_stopp: number;
  total_min: number;
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface OrsakDetail {
  orsak: string;
  antal: number;
  total_min: number;
  senaste: string | null;
}

export interface OperatorDetailData {
  operator_id: number;
  operator_namn: string;
  period: number;
  from_date: string;
  to_date: string;
  orsaker: OrsakDetail[];
  total_min: number;
  total_antal: number;
}

export interface OperatorDetailResponse {
  success: boolean;
  data: OperatorDetailData;
  timestamp: string;
}

export interface OrsakSummary {
  orsak: string;
  antal: number;
  total_min: number;
  andel_pct: number;
}

export interface ReasonsSummaryData {
  period: number;
  from_date: string;
  to_date: string;
  orsaker: OrsakSummary[];
  total_min: number;
}

export interface ReasonsSummaryResponse {
  success: boolean;
  data: ReasonsSummaryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class StopporsakOperatorService {
  private api = `${environment.apiUrl}?action=stopporsak-operator`;

  constructor(private http: HttpClient) {}

  getOverview(period: number): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getOperatorDetail(operatorId: number, period: number): Observable<OperatorDetailResponse | null> {
    return this.http.get<OperatorDetailResponse>(
      `${this.api}&run=operator-detail&operator_id=${operatorId}&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getReasonsSummary(period: number): Observable<ReasonsSummaryResponse | null> {
    return this.http.get<ReasonsSummaryResponse>(
      `${this.api}&run=reasons-summary&period=${period}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
