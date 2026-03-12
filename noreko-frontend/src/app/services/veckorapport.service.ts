import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface WeekInfo {
  year: number;
  week_number: number;
  start_date: string;
  end_date: string;
}

export interface DayData {
  date: string;
  count: number;
}

export interface ProductionData {
  total_ibc: number;
  goal: number;
  fulfillment_pct: number;
  best_day: DayData | null;
  worst_day: DayData | null;
  avg_per_day: number;
  prev_week_total: number;
  change_pct: number;
  daily: Record<string, number>;
}

export interface EfficiencyData {
  avg_ibc_per_hour: number;
  total_runtime_hours: number;
  available_hours: number;
  utilization_pct: number;
  prev_week_ibc_per_hour: number;
  change_pct: number;
}

export interface StopReason {
  reason: string;
  count: number;
  hours: number;
}

export interface StopsData {
  total_count: number;
  total_hours: number;
  top_reasons: StopReason[];
  prev_week_count: number;
  change_pct: number;
}

export interface QualityData {
  scrap_rate_pct: number;
  scrapped_count: number;
  total_produced: number;
  top_scrap_reason: string;
  prev_week_scrap_rate: number;
  change_pct: number;
}

export interface VeckorapportData {
  week_info: WeekInfo;
  production: ProductionData;
  efficiency: EfficiencyData;
  stops: StopsData;
  quality: QualityData;
}

export interface VeckorapportResponse {
  success: boolean;
  data: VeckorapportData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class VeckorapportService {
  private api = `${environment.apiUrl}?action=veckorapport`;

  constructor(private http: HttpClient) {}

  getReport(week?: string): Observable<VeckorapportResponse | null> {
    let url = `${this.api}&run=report`;
    if (week) {
      url += `&week=${encodeURIComponent(week)}`;
    }
    return this.http.get<VeckorapportResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null))
    );
  }
}
