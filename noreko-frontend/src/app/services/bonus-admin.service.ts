import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface BonusConfigResponse {
  success: boolean;
  data?: {
    weights_foodgrade: { eff: number; prod: number; qual: number };
    weights_nonun: { eff: number; prod: number; qual: number };
    weights_tvattade: { eff: number; prod: number; qual: number };
    productivity_target_foodgrade: number;
    productivity_target_nonun: number;
    productivity_target_tvattade: number;
    tier_multipliers: { threshold: number; multiplier: number; name: string }[];
    max_bonus: number;
    team_bonus_enabled: boolean;
    safety_bonus_enabled: boolean;
    weekly_bonus_goal?: number;
  };
  error?: string;
}

export interface BonusPeriod {
  period: string;
  total_cycles: number;
  unique_operators: number;
  avg_bonus: number;
  total_ibc_ok: number;
  success_rate: number;
}

export interface BonusPeriodsResponse {
  success: boolean;
  data?: { periods: BonusPeriod[] };
  error?: string;
}

export interface BonusSystemStatsResponse {
  success: boolean;
  data?: {
    total_cycles: number;
    unique_operators: number;
    avg_bonus: number;
    max_bonus: number;
    min_bonus: number;
    high_performers_pct: number;
    trend: number;
  };
  error?: string;
}

export interface OperatorForecastResponse {
  success: boolean;
  data?: {
    operator_id: number;
    operator_name: string;
    shifts_last_7days: number;
    avg_bonus_last_7days: number;
    avg_produktivitet: number;
    hours_per_shift: number;
    tier_multiplier: number;
    projected_bonus: number;
    weekly_goal: number;
    pct_of_goal: number;
  };
  error?: string;
}

export interface GenericResponse {
  success: boolean;
  data?: any;
  error?: string;
}

@Injectable({ providedIn: 'root' })
export class BonusAdminService {
  private readonly baseUrl = `${environment.apiUrl}?action=bonusadmin`;

  constructor(private http: HttpClient) {}

  getConfig(): Observable<BonusConfigResponse | null> {
    return this.http.get<BonusConfigResponse>(this.baseUrl + '&run=get_config', {
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  updateWeights(produkt: number, weights: { eff: number; prod: number; qual: number }): Observable<GenericResponse | null> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=update_weights', {
      produkt,
      weights
    }, { withCredentials: true }).pipe(timeout(10000), catchError(() => of(null)));
  }

  setTargets(targets: { foodgrade: number; nonun: number; tvattade: number }): Observable<GenericResponse | null> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=set_targets', {
      targets
    }, { withCredentials: true }).pipe(timeout(10000), catchError(() => of(null)));
  }

  getPeriods(): Observable<BonusPeriodsResponse | null> {
    return this.http.get<BonusPeriodsResponse>(this.baseUrl + '&run=get_periods', {
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  approveBonuses(period: string): Observable<GenericResponse | null> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=approve_bonuses', {
      period
    }, { withCredentials: true }).pipe(timeout(10000), catchError(() => of(null)));
  }

  exportReport(period: string, format: string = 'csv'): Observable<any> {
    if (format === 'csv') {
      // CSV returns file download
      window.open(`${this.baseUrl}&run=export_report&period=${encodeURIComponent(period)}&format=csv`, '_blank');
      return new Observable(obs => { obs.next({ success: true }); obs.complete(); });
    }
    return this.http.get<GenericResponse>(
      this.baseUrl + '&run=export_report&period=' + encodeURIComponent(period) + '&format=json',
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getSystemStats(): Observable<BonusSystemStatsResponse | null> {
    return this.http.get<BonusSystemStatsResponse>(this.baseUrl + '&run=get_stats', {
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  setWeeklyGoal(weeklyGoal: number): Observable<GenericResponse | null> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=set_weekly_goal', {
      weekly_goal: weeklyGoal
    }, { withCredentials: true }).pipe(timeout(10000), catchError(() => of(null)));
  }

  getOperatorForecast(operatorId: number): Observable<OperatorForecastResponse | null> {
    return this.http.get<OperatorForecastResponse>(
      this.baseUrl + '&run=operator_forecast&id=' + operatorId,
      { withCredentials: true }
    ).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
}
