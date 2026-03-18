import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ========== Interfaces som matchar BonusController API-svar ==========

export interface BonusSummaryResponse {
  success: boolean;
  data?: {
    date: string;
    total_cycles: number;
    shifts_today: number;
    total_ibc_ok: number;
    total_ibc_ej_ok: number;
    avg_bonus: number;
    max_bonus: number;
    unique_operators: {
      tvattplats: number;
      kontroll: number;
      truck: number;
    };
  };
  error?: string;
}

export interface OperatorStatsResponse {
  success: boolean;
  data?: {
    operator_id: number;
    operator_name?: string | null;
    position: string;
    period: string;
    date_range: { from: string; to: string };
    summary: {
      total_cycles: number;
      total_ibc_ok: number;
      total_ibc_ej_ok: number;
      total_bur_ej_ok: number;
      total_hours: number;
      total_rast_hours: number;
    };
    kpis: {
      effektivitet: number;
      produktivitet: number;
      kvalitet: number;
      bonus_avg: number;
      bonus_max: number;
      bonus_min: number;
    };
    daily_breakdown: DailyBreakdown[];
  };
  error?: string;
}

export interface DailyBreakdown {
  date: string;
  cycles: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  bonus_poang: number;
}

export interface RankingEntry {
  rank: number;
  operator_id: number;
  operator_name?: string | null;
  position?: string;
  cycles?: number;
  total_cycles?: number;
  total_shifts?: number;
  bonus_avg: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  total_ibc_ok: number;
  total_hours: number;
}

export interface RankingResponse {
  success: boolean;
  data?: {
    period: string;
    limit: number;
    rankings: {
      position_1: RankingEntry[];
      position_2: RankingEntry[];
      position_3: RankingEntry[];
      overall: RankingEntry[];
    };
  };
  error?: string;
}

export interface TeamStatsResponse {
  success: boolean;
  data?: {
    period: string;
    aggregate: {
      total_shifts: number;
      total_cycles: number;
      total_ibc_ok: number;
      avg_bonus: number;
      unique_operators: number;
    };
    shifts: ShiftStats[];
  };
  error?: string;
}

export interface ShiftStats {
  shift_number: number;
  shift_start: string;
  shift_end: string;
  operators: number[];
  operator_count: number;
  cycles: number;
  total_ibc_ok: number;
  total_ibc_ej_ok: number;
  total_bur_ej_ok: number;
  total_hours: number;
  kpis: {
    effektivitet: number;
    produktivitet: number;
    kvalitet: number;
    bonus_avg: number;
  };
}

export interface KPIDetailsResponse {
  success: boolean;
  data?: {
    operator_id: number;
    period: string;
    chart_data: {
      labels: string[];
      datasets: {
        label: string;
        data: number[];
        borderColor: string;
        backgroundColor: string;
      }[];
    };
    raw_data: any[];
  };
  error?: string;
}

export interface OperatorHistoryResponse {
  success: boolean;
  data?: {
    operator_id: number;
    count: number;
    history: OperatorHistoryEntry[];
  };
  error?: string;
}

export interface OperatorHistoryEntry {
  datum: string;
  lopnummer: number;
  shift: number;
  position: string;
  produkt: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  runtime: number;
  kpis: {
    effektivitet: number;
    produktivitet: number;
    kvalitet: number;
    bonus: number;
  };
}

export interface WeeklyHistoryEntry {
  yearweek: number;
  year: number;
  week: number;
  label: string;
  shifts: number;
  my_bonus: number;
  my_ibc_per_hour: number;
  my_kvalitet: number;
  team_bonus: number;
  team_ibc_per_hour: number;
  team_kvalitet: number;
}

export interface WeeklyHistoryResponse {
  success: boolean;
  data?: {
    operator_id: number;
    weeks: WeeklyHistoryEntry[];
    my_avg: number;
  };
  error?: string;
}

export interface HallOfFameEntry {
  rank: number;
  badge: 'gold' | 'silver' | 'bronze';
  name: string;
  value: number;
  label: string;
}

export interface HallOfFameResponse {
  success: boolean;
  data?: {
    period_days: number;
    ibc_per_h: HallOfFameEntry[];
    kvalitet_pct: HallOfFameEntry[];
    mest_aktiv: HallOfFameEntry[];
  };
  error?: string;
}

export interface LoneprognosOperator {
  operator_id: number;
  operator_name: string;
  antal_skift: number;
  ibc_ok_manad: number;
  avg_bonus_poang: number;
  tier_label: string;
  tier_key: string;
  bonus_per_skift_sek: number;
  beraknad_bonus_sek: number;
}

export interface LoneprognosResponse {
  success: boolean;
  data?: {
    manadsnamn: string;
    month_start: string;
    today: string;
    days_in_month: number;
    day_of_month: number;
    days_left: number;
    month_pct: number;
    operatorer: LoneprognosOperator[];
  };
  error?: string;
}

export interface RankingPositionResponse {
  success: boolean;
  my_rank: number | null;
  total_operators: number;
  my_ibc_per_h: number | null;
  top_ibc_per_h: number | null;
  avg_ibc_per_h: number | null;
  percentile: number | null;
  trend: 'up' | 'down' | 'same';
  week_label: string;
  error?: string;
}

@Injectable({ providedIn: 'root' })
export class BonusService {
  private readonly baseUrl = `${environment.apiUrl}?action=bonus`;

  constructor(private http: HttpClient) {}

  getDailySummary(): Observable<BonusSummaryResponse | null> {
    return this.http.get<BonusSummaryResponse>(this.baseUrl + '&run=summary', {
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getOperatorStats(operatorId: string, period?: string, start?: string, end?: string): Observable<OperatorStatsResponse | null> {
    let params = new HttpParams().set('run', 'operator').set('id', operatorId);
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<OperatorStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getRanking(period?: string, limit: number = 10, start?: string, end?: string): Observable<RankingResponse | null> {
    let params = new HttpParams().set('run', 'ranking').set('limit', limit.toString());
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<RankingResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getTeamStats(period?: string, start?: string, end?: string): Observable<TeamStatsResponse | null> {
    let params = new HttpParams().set('run', 'team');
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<TeamStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getKPIDetails(operatorId: string, period?: string): Observable<KPIDetailsResponse | null> {
    let params = new HttpParams().set('run', 'kpis').set('id', operatorId);
    if (period) params = params.set('period', period);

    return this.http.get<KPIDetailsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getOperatorHistory(operatorId: string, limit: number = 50): Observable<OperatorHistoryResponse | null> {
    const params = new HttpParams().set('run', 'history').set('id', operatorId).set('limit', limit.toString());

    return this.http.get<OperatorHistoryResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getWeeklyHistory(operatorId: string): Observable<WeeklyHistoryResponse | null> {
    const params = new HttpParams().set('run', 'weekly_history').set('id', operatorId);
    return this.http.get<WeeklyHistoryResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getHallOfFame(): Observable<HallOfFameResponse | null> {
    const params = new HttpParams().set('run', 'hall-of-fame');
    return this.http.get<HallOfFameResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getLoneprognos(): Observable<LoneprognosResponse | null> {
    const params = new HttpParams().set('run', 'loneprognos');
    return this.http.get<LoneprognosResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }
  getWeekTrend(): Observable<any> {
    const params = new HttpParams().set('run', 'week-trend');
    return this.http.get<any>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

  getRankingPosition(): Observable<RankingPositionResponse | null> {
    const params = new HttpParams().set('run', 'ranking-position');
    return this.http.get<RankingPositionResponse>(this.baseUrl, {
      params,
      withCredentials: true
    }).pipe(timeout(10000), retry(1), catchError(() => of(null)));
  }

}
