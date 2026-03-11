import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export interface MalAndring {
  id: number;
  goal_type: string;
  nytt_mal: number;
  gammalt_mal: number | null;
  proc_andring: number | null;
  andrad_av: string;
  andrad_vid: string;
  riktning: 'foerst' | 'upp' | 'ner' | 'oforandrad';
}

export interface GoalHistoryData {
  andringar: MalAndring[];
  aktuellt_mal: number | null;
  antal_andringar: number;
  senaste_andring: string | null;
}

export interface GoalHistoryResponse {
  success: boolean;
  data: GoalHistoryData;
  timestamp: string;
}

export interface ImpactPeriod {
  period_fran: string;
  period_tom: string;
  ibc_per_timme: number;
  malprocent: number;
  antal_dagar: number;
}

export interface GoalImpactItem {
  id: number;
  goal_type: string;
  gammalt_mal: number | null;
  nytt_mal: number;
  proc_malAndring: number | null;
  andrad_av: string;
  andrad_vid: string;
  fore: ImpactPeriod;
  efter: ImpactPeriod;
  diff_ibc_per_h: number | null;
  diff_proc: number | null;
  effekt: 'forbattring' | 'forsämring' | 'oforandrad' | 'ny-start' | 'ingen-data';
}

export interface GoalImpactData {
  impact: GoalImpactItem[];
}

export interface GoalImpactResponse {
  success: boolean;
  data: GoalImpactData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MalhistorikService {
  private api = '../../noreko-backend/api.php?action=malhistorik';

  constructor(private http: HttpClient) {}

  getGoalHistory(): Observable<GoalHistoryResponse | null> {
    return this.http.get<GoalHistoryResponse>(
      `${this.api}&run=goal-history`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getGoalImpact(): Observable<GoalImpactResponse | null> {
    return this.http.get<GoalImpactResponse>(
      `${this.api}&run=goal-impact`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
