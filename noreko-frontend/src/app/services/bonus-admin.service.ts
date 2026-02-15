import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

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

export interface GenericResponse {
  success: boolean;
  data?: any;
  error?: string;
}

@Injectable({ providedIn: 'root' })
export class BonusAdminService {
  private readonly baseUrl = '/noreko-backend/api.php?action=bonusadmin';

  constructor(private http: HttpClient) {}

  getConfig(): Observable<BonusConfigResponse> {
    return this.http.get<BonusConfigResponse>(this.baseUrl + '&run=get_config', {
      withCredentials: true
    });
  }

  updateWeights(produkt: number, weights: { eff: number; prod: number; qual: number }): Observable<GenericResponse> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=update_weights', {
      produkt,
      weights
    }, { withCredentials: true });
  }

  setTargets(targets: { foodgrade: number; nonun: number; tvattade: number }): Observable<GenericResponse> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=set_targets', {
      targets
    }, { withCredentials: true });
  }

  getPeriods(): Observable<BonusPeriodsResponse> {
    return this.http.get<BonusPeriodsResponse>(this.baseUrl + '&run=get_periods', {
      withCredentials: true
    });
  }

  approveBonuses(period: string): Observable<GenericResponse> {
    return this.http.post<GenericResponse>(this.baseUrl + '&run=approve_bonuses', {
      period
    }, { withCredentials: true });
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
    );
  }

  getSystemStats(): Observable<BonusSystemStatsResponse> {
    return this.http.get<BonusSystemStatsResponse>(this.baseUrl + '&run=get_stats', {
      withCredentials: true
    });
  }
}
