import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface RebotlingLiveStatsResponse {
  success: boolean;
  data: {
    rebotlingToday: number;
    rebotlingTarget: number;
    rebotlingThisHour: number;
    hourlyTarget: number;
  };
}

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    lastUpdate: string | null;
  };
}

@Injectable({ providedIn: 'root' })
export class RebotlingService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<RebotlingLiveStatsResponse> {
    return this.http.get<RebotlingLiveStatsResponse>(
      '/noreko-backend/api.php?action=rebotling',
      { withCredentials: true }
    );
  }

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=rebotling&run=status',
      { withCredentials: true }
    );
  }
}



