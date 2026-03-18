import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface FeedbackItem {
  id: number;
  datum: string;
  operator_id: number;
  operator_namn: string;
  stamning: number;
  stamning_text: string;
  stamning_color: string;
  kommentar: string | null;
  skapad_at: string;
}

export interface FeedbackListData {
  items: FeedbackItem[];
  total: number;
  page: number;
  per_page: number;
  pages: number;
  days: number;
  from_date: string;
}

export interface FeedbackListResponse {
  success: boolean;
  data: FeedbackListData;
}

export interface FeedbackStatsData {
  total: number;
  snitt_stamning: number | null;
  prev_snitt: number | null;
  trend: 'bättre' | 'sämre' | 'stabil';
  senaste_datum: string | null;
  fordelning: { [key: string]: number };
  mest_aktiv: { operator_id: number; namn: string; antal: number } | null;
  days: number;
  from_date: string;
}

export interface FeedbackStatsResponse {
  success: boolean;
  data: FeedbackStatsData;
}

export interface TrendPunkt {
  arsvecka: string;
  vecka_start: string;
  snitt_stamning: number;
  antal: number;
}

export interface FeedbackTrendData {
  trend: TrendPunkt[];
  avg_total: number | null;
  days: number;
  from_date: string;
}

export interface FeedbackTrendResponse {
  success: boolean;
  data: FeedbackTrendData;
}

export interface OperatorSentimentItem {
  operator_id: number;
  operator_namn: string;
  antal: number;
  snitt_stamning: number;
  senaste_datum: string;
  senaste_kommentar: string | null;
  sentiment_color: string;
  sentiment_label: string;
}

export interface OperatorSentimentData {
  operatorer: OperatorSentimentItem[];
  days: number;
  from_date: string;
}

export interface OperatorSentimentResponse {
  success: boolean;
  data: OperatorSentimentData;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class FeedbackAnalysService {
  private api = `${environment.apiUrl}?action=feedback-analys`;

  constructor(private http: HttpClient) {}

  getFeedbackList(params: {
    days?: number;
    page?: number;
    per_page?: number;
    operator_id?: number | null;
  }): Observable<FeedbackListResponse | null> {
    const { days = 30, page = 1, per_page = 20, operator_id = null } = params;
    let url = `${this.api}&run=feedback-list&days=${days}&page=${page}&per_page=${per_page}`;
    if (operator_id !== null) url += `&operator_id=${operator_id}`;
    return this.http.get<FeedbackListResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getFeedbackStats(days: number = 30): Observable<FeedbackStatsResponse | null> {
    return this.http.get<FeedbackStatsResponse>(
      `${this.api}&run=feedback-stats&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getFeedbackTrend(days: number = 30): Observable<FeedbackTrendResponse | null> {
    return this.http.get<FeedbackTrendResponse>(
      `${this.api}&run=feedback-trend&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getOperatorSentiment(days: number = 30): Observable<OperatorSentimentResponse | null> {
    return this.http.get<OperatorSentimentResponse>(
      `${this.api}&run=operator-sentiment&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
