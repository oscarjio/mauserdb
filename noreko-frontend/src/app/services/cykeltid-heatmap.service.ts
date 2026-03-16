import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface HeatmapCell {
  hour: number;
  avg_sek: number | null;
  antal: number;
}

export interface HeatmapOperator {
  id: number;
  namn: string;
}

export interface HeatmapData {
  operators: HeatmapOperator[];
  hours: number[];
  matrix: HeatmapCell[][];
  globalMin: number | null;
  globalMax: number | null;
  globalAvg: number | null;
  days: number;
}

export interface HeatmapResponse {
  success: boolean;
  data: HeatmapData;
}

export interface DayPatternItem {
  hour: number;
  avg_sek: number;
  antal: number;
}

export interface DayPatternSummary {
  snabbaste_timme: number;
  snabbaste_sek: number;
  langsammaste_timme: number;
  langsammaste_sek: number;
  global_avg_sek: number;
}

export interface DayPatternData {
  pattern: DayPatternItem[];
  summary: DayPatternSummary | null;
  days: number;
}

export interface DayPatternResponse {
  success: boolean;
  data: DayPatternData;
}

export interface DagMatrixRow {
  dag: string;
  celler: HeatmapCell[];
}

export interface HourAvgItem {
  hour: number;
  avg_sek: number;
  stddev: number;
  antal_dagar: number;
}

export interface OperatorDetailData {
  operator_id: number;
  operator_namn: string;
  hours: number[];
  dag_matrix: DagMatrixRow[];
  hour_avg: HourAvgItem[];
  days: number;
}

export interface OperatorDetailResponse {
  success: boolean;
  data: OperatorDetailData;
}

@Injectable({ providedIn: 'root' })
export class CykeltidHeatmapService {
  private api = `${environment.apiUrl}?action=cykeltid-heatmap`;

  constructor(private http: HttpClient) {}

  getHeatmapData(days: number = 30): Observable<HeatmapResponse | null> {
    return this.http.get<HeatmapResponse>(
      `${this.api}&run=heatmap&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getDayPattern(days: number = 30): Observable<DayPatternResponse | null> {
    return this.http.get<DayPatternResponse>(
      `${this.api}&run=day-pattern&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getOperatorDetail(operatorId: number, days: number = 30): Observable<OperatorDetailResponse | null> {
    return this.http.get<OperatorDetailResponse>(
      `${this.api}&run=operator-detail&operator_id=${operatorId}&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
