import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export type SegmentType = 'running' | 'stopped' | 'unplanned';

export interface TimelineSegment {
  type: SegmentType;
  start: string;
  end: string;
  start_ts: number;
  end_ts: number;
  duration_min: number;
  stop_reason: string | null;
  operator: string | null;
}

export interface TimelineData {
  date: string;
  segments: TimelineSegment[];
  skift_start: string;
  skift_slut: string;
  running_min: number;
  stopped_min: number;
  unplanned_min: number;
}

export interface TimelineDataResponse {
  success: boolean;
  data: TimelineData;
  timestamp: string;
}

export interface TimelineSummaryData {
  date: string;
  drifttid_min: number;
  stopptid_min: number;
  antal_stopp: number;
  langsta_korning_min: number;
  utnyttjandegrad_pct: number;
  plannad_tid_min: number;
}

export interface TimelineSummaryResponse {
  success: boolean;
  data: TimelineSummaryData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class DrifttidsTimelineService {
  private api = `${environment.apiUrl}?action=drifttids-timeline`;

  constructor(private http: HttpClient) {}

  getDayTimeline(date: string): Observable<TimelineDataResponse | null> {
    return this.http.get<TimelineDataResponse>(
      `${this.api}&run=timeline-data&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }

  getDaySummary(date: string): Observable<TimelineSummaryResponse | null> {
    return this.http.get<TimelineSummaryResponse>(
      `${this.api}&run=summary&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
