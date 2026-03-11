import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface NarvaroDayEntry {
  dag: string;
  ibc: number;
  skift: number[];
  snitt_cykel: number;
}

export interface NarvaroOperator {
  operator_id: number;
  operator_name: string;
  days: NarvaroDayEntry[];
  total_ibc: number;
  active_days: number;
}

export interface NarvaroSummary {
  total_operators: number;
  avg_days_per_op: number;
  top_days_operator: string | null;
  top_days_count: number;
  top_ibc_operator: string | null;
  top_ibc_count: number;
}

export interface NarvaroMonthlyResponse {
  success: boolean;
  data: {
    year: number;
    month: number;
    days_in_month: number;
    operators: NarvaroOperator[];
    summary: NarvaroSummary;
  };
}

@Injectable({ providedIn: 'root' })
export class NarvarotrackerService {
  constructor(private http: HttpClient) {}

  getMonthlyOverview(year: number, month: number): Observable<NarvaroMonthlyResponse> {
    return this.http.get<NarvaroMonthlyResponse>(
      `/noreko-backend/api.php?action=narvaro&run=monthly-overview&year=${year}&month=${month}`,
      { withCredentials: true }
    );
  }
}
