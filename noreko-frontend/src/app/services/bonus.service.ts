import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

/**
 * Interface för operatörs KPI-data
 */
export interface OperatorDailyStats {
  datum: string;
  cycles: number;
  total_ok: number;
  total_ej_ok: number;
  total_bur_ej_ok: number;
  avg_effektivitet: number;
  avg_produktivitet: number;
  avg_kvalitet: number;
  avg_bonus: number;
  total_runtime: number;
  total_rast: number;
  produkt: number;
}

export interface OperatorTotalStats {
  total_cycles: number;
  total_ok: number;
  total_ej_ok: number;
  total_bur_ej_ok: number;
  total_runtime: number;
  total_rast: number;
  avg_effektivitet: number;
  avg_produktivitet: number;
  avg_kvalitet: number;
  avg_bonus: number;
}

export interface OperatorRanking {
  rank: number | null;
  bonus_poang: number | null;
}

export interface OperatorStatsResponse {
  success: boolean;
  data?: {
    operatorId: string;
    period: {
      start: string;
      end: string;
    };
    dailyStats: OperatorDailyStats[];
    totalStats: OperatorTotalStats;
    ranking: OperatorRanking;
  };
  error?: string;
}

/**
 * Interface för ranking-data
 */
export interface RankingEntry {
  rank: number;
  operator_id: string;
  position: string;
  total_cycles: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  bonus_poang: number;
  total_ok: number;
  total_ej_ok: number;
}

export interface RankingResponse {
  success: boolean;
  data?: {
    period: {
      start: string;
      end: string;
    };
    ranking: RankingEntry[];
  };
  error?: string;
}

/**
 * Interface för team-statistik
 */
export interface PositionStats {
  position: string;
  unique_operators: number;
  total_cycles: number;
  avg_effektivitet: number;
  avg_produktivitet: number;
  avg_kvalitet: number;
  avg_bonus: number;
  total_ok: number;
  total_ej_ok: number;
  total_bur_ej_ok: number;
}

export interface DailyTrend {
  datum: string;
  cycles: number;
  avg_bonus: number;
  total_ok: number;
  total_ej_ok: number;
}

export interface ProductStats {
  produkt: number;
  cycles: number;
  avg_bonus: number;
  total_ok: number;
}

export interface TeamStatsResponse {
  success: boolean;
  data?: {
    period: {
      start: string;
      end: string;
    };
    positionStats: PositionStats[];
    dailyTrend: DailyTrend[];
    productStats: ProductStats[];
  };
  error?: string;
}

/**
 * Interface för operatörs historik
 */
export interface OperatorHistoryEntry {
  datum: string;
  position: string;
  produkt: number;
  cycles: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  bonus_poang: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  runtime: number;
}

export interface OperatorHistoryResponse {
  success: boolean;
  data?: {
    operatorId: string;
    period: {
      start: string;
      end: string;
    };
    history: OperatorHistoryEntry[];
  };
  error?: string;
}

/**
 * Service för bonussystem API
 */
@Injectable({ providedIn: 'root' })
export class BonusService {
  private readonly baseUrl = '/noreko-backend/api.php?action=bonus';

  constructor(private http: HttpClient) {}

  /**
   * Hämta operatörs KPI och statistik
   */
  getOperatorStats(
    operatorId: string,
    startDate?: string,
    endDate?: string,
    position?: 'op1' | 'op2' | 'op3',
    produkt?: number
  ): Observable<OperatorStatsResponse> {
    let params = new HttpParams().set('run', 'operator').set('id', operatorId);

    if (startDate) params = params.set('start', startDate);
    if (endDate) params = params.set('end', endDate);
    if (position) params = params.set('position', position);
    if (produkt) params = params.set('produkt', produkt.toString());

    return this.http.get<OperatorStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  /**
   * Hämta top ranking (top 10 default)
   */
  getRanking(
    startDate?: string,
    endDate?: string,
    position?: string,
    produkt?: number,
    limit: number = 10
  ): Observable<RankingResponse> {
    let params = new HttpParams()
      .set('run', 'ranking')
      .set('limit', limit.toString());

    if (startDate) params = params.set('start', startDate);
    if (endDate) params = params.set('end', endDate);
    if (position) params = params.set('position', position);
    if (produkt) params = params.set('produkt', produkt.toString());

    return this.http.get<RankingResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  /**
   * Hämta team-översikt
   */
  getTeamStats(startDate?: string, endDate?: string): Observable<TeamStatsResponse> {
    let params = new HttpParams().set('run', 'team');

    if (startDate) params = params.set('start', startDate);
    if (endDate) params = params.set('end', endDate);

    return this.http.get<TeamStatsResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }

  /**
   * Hämta operatörs historik
   */
  getOperatorHistory(
    operatorId: string,
    startDate?: string,
    endDate?: string
  ): Observable<OperatorHistoryResponse> {
    let params = new HttpParams().set('run', 'history').set('id', operatorId);

    if (startDate) params = params.set('start', startDate);
    if (endDate) params = params.set('end', endDate);

    return this.http.get<OperatorHistoryResponse>(this.baseUrl, {
      params,
      withCredentials: true
    });
  }
}
