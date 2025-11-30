import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    lastUpdate: string | null;
  };
}

export interface TvattlinjeLiveStatsResponse {
  success: boolean;
  data: {
    ibcToday: number;
    ibcTarget: number;
    productionPercentage: number;
    utetemperatur: number | null;
  };
}

@Injectable({ providedIn: 'root' })
export class TvattlinjeService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<TvattlinjeLiveStatsResponse> {
    return this.http.get<TvattlinjeLiveStatsResponse>(
      '/noreko-backend/api.php?action=tvattlinje',
      { withCredentials: true }
    );
  }

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=tvattlinje&run=status',
      { withCredentials: true }
    );
  }
}

