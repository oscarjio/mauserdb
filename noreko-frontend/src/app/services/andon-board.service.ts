import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface TodayProduction {
  goal: number;
  actual: number;
  percentage: number;
  status: 'green' | 'yellow' | 'red';
}

export interface CurrentRate {
  ibc_per_hour: number;
  trend: 'up' | 'down' | 'stable';
  last_hour_count: number;
}

export interface MachineStatus {
  status: 'running' | 'stopped' | 'unknown';
  since: string;
  last_stop_reason: string | null;
  last_stop_duration_minutes: number | null;
  last_stop_minutes_ago: number | null;
}

export interface Quality {
  scrap_rate_percent: number;
  scrapped_today: number;
  total_today: number;
}

export interface ShiftInfo {
  name: string;
  start: string;
  end: string;
  operator: string | null;
}

export interface AndonBoardStatus {
  success: boolean;
  today_production: TodayProduction;
  current_rate: CurrentRate;
  machine_status: MachineStatus;
  quality: Quality;
  shift: ShiftInfo;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class AndonBoardService {
  private api = `${environment.apiUrl}?action=andon`;

  constructor(private http: HttpClient) {}

  getStatus(): Observable<AndonBoardStatus | null> {
    return this.http.get<AndonBoardStatus>(
      `${this.api}&run=board-status`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }
}
