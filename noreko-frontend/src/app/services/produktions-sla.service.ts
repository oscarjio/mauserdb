import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SlaOverview {
  dag_pct: number;
  dag_producerat: number;
  dag_target: number;
  dag_kassation_pct: number;
  dag_kass_target: number;
  vecka_pct: number;
  vecka_producerat: number;
  vecka_target: number;
  vecka_kassation_pct: number;
  streak: number;
  basta_vecka_pct: number;
  basta_vecka_label: string;
}

export interface SlaOverviewResponse {
  success: boolean;
  data: SlaOverview;
}

export interface HourData {
  timme: number;
  label: string;
  antal: number;
  kumulativt: number;
}

export interface DailyProgress {
  date: string;
  target: number;
  producerat: number;
  kassation_pct: number;
  uppfyllnad_pct: number;
  target_per_hour: number;
  hours: HourData[];
}

export interface DailyProgressResponse {
  success: boolean;
  data: DailyProgress;
}

export interface WeekDay {
  date: string;
  dag_namn: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  kassation_pct: number;
  uppfyllnad_pct: number;
  over_mal: boolean;
}

export interface WeeklyProgress {
  week_label: string;
  monday: string;
  sunday: string;
  vecko_target: number;
  dagligt_target: number;
  total_producerat: number;
  uppfyllnad_pct: number;
  days: WeekDay[];
}

export interface WeeklyProgressResponse {
  success: boolean;
  data: WeeklyProgress;
}

export interface HistoryDay {
  date: string;
  ibc_ok: number;
  target: number;
  uppfyllnad_pct: number;
  kassation_pct: number;
  over_mal: boolean;
}

export interface HistoryData {
  period: number;
  from: string;
  to: string;
  snitt_uppfyllnad: number;
  trend: 'uppat' | 'nedat' | 'stabil';
  dagar_over_mal: number;
  total_dagar: number;
  history: HistoryDay[];
}

export interface HistoryResponse {
  success: boolean;
  data: HistoryData;
}

export interface SlaGoal {
  id: number;
  mal_typ: 'dagligt' | 'veckovist';
  mal_typ_label: string;
  target_ibc: number;
  target_kassation_pct: number;
  giltig_from: string;
  giltig_tom: string | null;
  active: boolean;
  created_at: string;
}

export interface GoalsResponse {
  success: boolean;
  goals: SlaGoal[];
}

export interface SetGoalData {
  mal_typ: 'dagligt' | 'veckovist';
  target_ibc: number;
  target_kassation_pct: number;
  giltig_from: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class ProduktionsSlaService {
  private api = `${environment.apiUrl}?action=produktionssla`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<SlaOverviewResponse | null> {
    return this.http.get<SlaOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getDailyProgress(date?: string): Observable<DailyProgressResponse | null> {
    let url = `${this.api}&run=daily-progress`;
    if (date) url += `&date=${date}`;
    return this.http.get<DailyProgressResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getWeeklyProgress(week?: string): Observable<WeeklyProgressResponse | null> {
    let url = `${this.api}&run=weekly-progress`;
    if (week) url += `&week=${week}`;
    return this.http.get<WeeklyProgressResponse>(
      url, { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getHistory(period: number = 30): Observable<HistoryResponse | null> {
    return this.http.get<HistoryResponse>(
      `${this.api}&run=history&period=${period}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  getGoals(): Observable<GoalsResponse | null> {
    return this.http.get<GoalsResponse>(
      `${this.api}&run=goals`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  setGoal(data: SetGoalData): Observable<any> {
    return this.http.post(
      `${this.api}&run=set-goal`,
      data,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }
}
