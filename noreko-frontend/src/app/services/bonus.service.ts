import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

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
  position?: string;
  cycles?: number;
  total_cycles?: number;
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

@Injectable({ providedIn: 'root' })
export class BonusService {
  private readonly baseUrl = '/noreko-backend/api.php?action=bonus';

  constructor(private http: HttpClient) {}

  getDailySummary(): Observable<BonusSummaryResponse> {
    return this.http.get<BonusSummaryResponse>(this.baseUrl + '&run=summary', {
      withCredentials: true
    });
  }

  getOperatorStats(operatorId: string, period?: string, start?: string, end?: string): Observable<OperatorStatsResponse> {
    let params = new HttpParams().set('run', 'operator').set('id', operatorId);
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<OperatorStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  getRanking(period?: string, limit: number = 10, start?: string, end?: string): Observable<RankingResponse> {
    let params = new HttpParams().set('run', 'ranking').set('limit', limit.toString());
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<RankingResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  getTeamStats(period?: string, start?: string, end?: string): Observable<TeamStatsResponse> {
    let params = new HttpParams().set('run', 'team');
    if (period) params = params.set('period', period);
    if (start) params = params.set('start', start);
    if (end) params = params.set('end', end);

    return this.http.get<TeamStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  getKPIDetails(operatorId: string, period?: string): Observable<KPIDetailsResponse> {
    let params = new HttpParams().set('run', 'kpis').set('id', operatorId);
    if (period) params = params.set('period', period);

    return this.http.get<KPIDetailsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  getOperatorHistory(operatorId: string, limit: number = 50): Observable<OperatorHistoryResponse> {
    const params = new HttpParams().set('run', 'history').set('id', operatorId).set('limit', limit.toString());

    return this.http.get<OperatorHistoryResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }
}
