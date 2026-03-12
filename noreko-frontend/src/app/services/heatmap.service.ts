import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface HeatmapCell {
  date: string;
  hour: number;
  count: number;
}

export interface HeatmapScale {
  min: number;
  max: number;
  avg: number;
}

export interface HeatmapData {
  matrix: HeatmapCell[];
  scale: HeatmapScale;
  days: number;
  from_date: string;
  to_date: string;
}

export interface HeatmapDataResponse {
  success: boolean;
  data: HeatmapData;
  timestamp: string;
}

export interface HeatmapSummaryData {
  total_ibc: number;
  best_hour: number | null;
  best_hour_avg: number;
  worst_hour: number | null;
  worst_hour_avg: number;
  best_weekday: number | null;
  best_weekday_name: string | null;
  best_weekday_avg: number;
  days: number;
  from_date: string;
  to_date: string;
}

export interface HeatmapSummaryResponse {
  success: boolean;
  data: HeatmapSummaryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class HeatmapService {
  private api = `${environment.apiUrl}?action=heatmap`;

  constructor(private http: HttpClient) {}

  getHeatmapData(days: number): Observable<HeatmapDataResponse | null> {
    return this.http.get<HeatmapDataResponse>(
      `${this.api}&run=heatmap-data&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }

  getSummary(days: number): Observable<HeatmapSummaryResponse | null> {
    return this.http.get<HeatmapSummaryResponse>(
      `${this.api}&run=summary&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
