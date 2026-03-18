import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OnboardingOperator {
  operator_number: number;
  namn: string;
  start_datum: string;
  nuvarande_ibc_h: number;
  team_snitt_ibc_h: number;
  pct_av_snitt: number;
  veckor_aktiv: number;
  veckor_till_snitt: number | null;
  is_ny: boolean;
  status: 'gron' | 'gul' | 'rod';
}

export interface OnboardingKpi {
  antal_nya: number;
  snitt_veckor_till_snitt: number | null;
  basta_nykomling_namn: string;
  basta_nykomling_ibc_h: number;
  team_snitt_ibc_h: number;
  antal_operatorer: number;
}

export interface OverviewData {
  months: number;
  operatorer: OnboardingOperator[];
  team_snitt_ibc_h: number;
  kpi: OnboardingKpi;
}

export interface OverviewResponse {
  success: boolean;
  data: OverviewData;
  timestamp: string;
}

export interface WeekData {
  week: number;
  ibc_h: number;
  ibc_ok: number;
  drifttid_min: number;
}

export interface OperatorCurveData {
  operator_number: number;
  operator_namn: string;
  start_datum: string;
  team_snitt_ibc_h: number;
  weeks: WeekData[];
}

export interface OperatorCurveResponse {
  success: boolean;
  data: OperatorCurveData;
  timestamp: string;
}

export interface TeamStatsData {
  team_snitt_ibc_h: number;
  antal_aktiva: number;
}

export interface TeamStatsResponse {
  success: boolean;
  data: TeamStatsData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OperatorOnboardingService {
  private api = `${environment.apiUrl}?action=operator-onboarding`;

  constructor(private http: HttpClient) {}

  getOverview(months: number): Observable<OverviewResponse | null> {
    return this.http.get<OverviewResponse>(
      `${this.api}&run=overview&months=${months}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getOperatorCurve(operatorNumber: number): Observable<OperatorCurveResponse | null> {
    return this.http.get<OperatorCurveResponse>(
      `${this.api}&run=operator-curve&operator_number=${operatorNumber}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getTeamStats(): Observable<TeamStatsResponse | null> {
    return this.http.get<TeamStatsResponse>(
      `${this.api}&run=team-stats`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
